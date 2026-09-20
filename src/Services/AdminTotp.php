<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BackendConnectionException;
use App\Repositories\IredadminPdo;
use App\Utils\SecretBox;
use App\Utils\Totp;

/**
 * The two-factor secret and the recovery codes of an admin, in the iredadmin
 * table `panel_admin_totp`. The secret is stored with SecretBox, because the
 * panel must read it back to check a code; the recovery codes are stored as
 * hashes (see RecoveryCodes), because they are only ever compared.
 */
final class AdminTotp
{
    /** A setup that a code confirmed: a login of this admin asks for a code. */
    public const STATE_ENABLED = 'enabled';

    /** A started setup that no code confirmed yet. */
    public const STATE_PENDING = 'pending';

    /** No setup at all. */
    public const STATE_DISABLED = 'disabled';

    /** Portable across MySQL, PostgreSQL and SQLite: no auto-increment and no engine clause. */
    private const SCHEMA = 'CREATE TABLE IF NOT EXISTS panel_admin_totp (
            admin VARCHAR(255) NOT NULL,
            secret TEXT NOT NULL,
            recovery_codes TEXT NOT NULL,
            confirmed INT NOT NULL,
            created BIGINT NOT NULL,
            PRIMARY KEY (admin)
        )';

    /**
     * The table is created when it is missing, so every entry point (web, CLI)
     * finds it without a separate migration step.
     */
    public function __construct(private readonly \PDO $pdo, private readonly SecretBox $box)
    {
        $this->pdo->exec(self::SCHEMA);
    }

    /**
     * @throws BackendConnectionException when the iredadmin database is not reachable
     */
    public static function forBackend(): self
    {
        return new self(IredadminPdo::get(), SecretBox::fromSettings());
    }

    /**
     * The setup state of the admin and how many recovery codes are left.
     *
     * @return array{state: string, remainingCodes: int} state is one of the STATE_* constants
     */
    public function status(string $admin): array
    {
        $row = $this->row($admin);
        if ($row === null) {
            return ['state' => self::STATE_DISABLED, 'remainingCodes' => 0];
        }

        return [
            'state' => (int) $row['confirmed'] === 1 ? self::STATE_ENABLED : self::STATE_PENDING,
            'remainingCodes' => count(RecoveryCodes::decode((string) $row['recovery_codes'])),
        ];
    }

    /**
     * Stores a new, unconfirmed secret and replaces any earlier setup of the admin.
     *
     * @param list<string> $recoveryCodes the plain codes that the admin sees once
     */
    public function start(string $admin, string $secret, array $recoveryCodes, int $now): void
    {
        $this->delete($admin);
        $this->pdo->prepare(
            'INSERT INTO panel_admin_totp (admin, secret, recovery_codes, confirmed, created)
             VALUES (:admin, :secret, :codes, 0, :created)'
        )->execute([
            'admin' => $admin,
            'secret' => $this->box->encrypt($secret),
            'codes' => RecoveryCodes::encode($recoveryCodes),
            'created' => $now,
        ]);
    }

    public function confirm(string $admin): void
    {
        $this->pdo->prepare('UPDATE panel_admin_totp SET confirmed = 1 WHERE admin = :admin')
            ->execute(['admin' => $admin]);
    }

    public function delete(string $admin): void
    {
        $this->pdo->prepare('DELETE FROM panel_admin_totp WHERE admin = :admin')->execute(['admin' => $admin]);
    }

    /**
     * The stored secret of the admin, confirmed or not, or null when there is none.
     */
    public function secret(string $admin): ?string
    {
        $row = $this->row($admin);

        return $row === null ? null : $this->box->decrypt((string) $row['secret']);
    }

    /**
     * Whether the code is the current one-time password of the admin.
     */
    public function verifyTotp(string $admin, string $code, int $now): bool
    {
        $secret = $this->secret($admin);

        return $secret !== null && Totp::verify($secret, $code, $now);
    }

    /**
     * Uses up a recovery code of the admin. Every code works once.
     */
    public function consumeRecoveryCode(string $admin, string $code): bool
    {
        $row = $this->row($admin);
        if ($row === null) {
            return false;
        }

        $left = RecoveryCodes::remove(RecoveryCodes::decode((string) $row['recovery_codes']), $code);
        if ($left === null) {
            return false;
        }
        $this->writeCodes($admin, json_encode($left, JSON_THROW_ON_ERROR));

        return true;
    }

    /**
     * @param list<string> $recoveryCodes the plain codes that the admin sees once
     */
    public function replaceRecoveryCodes(string $admin, array $recoveryCodes): void
    {
        $this->writeCodes($admin, RecoveryCodes::encode($recoveryCodes));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(string $admin): ?array
    {
        $stmt = $this->pdo->prepare('SELECT secret, recovery_codes, confirmed FROM panel_admin_totp WHERE admin = :admin');
        $stmt->execute(['admin' => $admin]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function writeCodes(string $admin, string $stored): void
    {
        $this->pdo->prepare('UPDATE panel_admin_totp SET recovery_codes = :codes WHERE admin = :admin')
            ->execute(['admin' => $admin, 'codes' => $stored]);
    }
}
