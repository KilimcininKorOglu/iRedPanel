<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BackendConnectionException;
use App\Repositories\IredadminPdo;

/**
 * Pending password reset requests in the iredadmin table `panel_password_resets`.
 * The table holds only the SHA-256 hash of a token, so a database read cannot
 * produce a usable reset link.
 */
final class PasswordResets
{
    /** A reset link stops working after this many hours. */
    public const EXPIRE_HOURS = 2;

    /** A repeated request within this time keeps the pending token and sends no mail. */
    public const RESEND_INTERVAL_SECONDS = 300;

    /** Portable across MySQL, PostgreSQL and SQLite: no auto-increment and no engine clause. */
    private const SCHEMA = 'CREATE TABLE IF NOT EXISTS panel_password_resets (
            token_hash VARCHAR(64) NOT NULL,
            email VARCHAR(255) NOT NULL,
            created BIGINT NOT NULL,
            expired BIGINT NOT NULL,
            PRIMARY KEY (token_hash)
        )';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @throws BackendConnectionException when the iredadmin database is not reachable
     */
    public static function forBackend(): self
    {
        return new self(IredadminPdo::get());
    }

    public function ensureTableExists(): void
    {
        $this->pdo->exec(self::SCHEMA);
    }

    /**
     * Returns a new token for the mailbox, or null when a request of the same
     * mailbox is still pending and was made less than RESEND_INTERVAL_SECONDS ago.
     */
    public function create(string $email, int $now): ?string
    {
        $this->deleteExpired($now);
        if ($this->hasRecent($email, $now)) {
            return null;
        }

        $this->deleteForEmail($email);
        $token = bin2hex(random_bytes(32));
        $this->pdo->prepare(
            'INSERT INTO panel_password_resets (token_hash, email, created, expired)
             VALUES (:hash, :email, :created, :expired)'
        )->execute([
            'hash' => self::hash($token),
            'email' => $email,
            'created' => $now,
            'expired' => $now + self::EXPIRE_HOURS * 3600,
        ]);

        return $token;
    }

    /**
     * The mailbox address of a token that has not expired, or null.
     */
    public function find(string $token, int $now): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT email FROM panel_password_resets WHERE token_hash = :hash AND expired > :now'
        );
        $stmt->execute(['hash' => self::hash($token), 'now' => $now]);
        $email = $stmt->fetchColumn();

        return $email === false ? null : (string) $email;
    }

    public function deleteForEmail(string $email): void
    {
        $this->pdo->prepare('DELETE FROM panel_password_resets WHERE email = :email')->execute(['email' => $email]);
    }

    public function deleteExpired(int $now): void
    {
        $this->pdo->prepare('DELETE FROM panel_password_resets WHERE expired <= :now')->execute(['now' => $now]);
    }

    private function hasRecent(string $email, int $now): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT created FROM panel_password_resets WHERE email = :email ORDER BY created DESC'
        );
        $stmt->execute(['email' => $email]);
        $created = $stmt->fetchColumn();

        return $created !== false && $now - (int) $created < self::RESEND_INTERVAL_SECONDS;
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
