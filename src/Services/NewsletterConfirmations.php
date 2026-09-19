<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BackendConnectionException;
use App\Models\Settings;
use App\Repositories\Mysql\IredadminConnection;
use App\Repositories\Pgsql\IredadminPgsqlConnection;

/**
 * Pending newsletter subscribe/unsubscribe requests in the iredadmin table
 * `newsletter_subunsub_confirms`.
 */
class NewsletterConfirmations
{
    /** A repeated request within this time reuses the pending token and sends no mail. */
    public const RESEND_INTERVAL_SECONDS = 300;

    /**
     * Returns a new token for the request, or null when the same request is
     * still pending and was made less than RESEND_INTERVAL_SECONDS ago.
     */
    public static function create(string $mlid, string $mail, string $subscriber, string $kind, int $expireHours): ?string
    {
        $pdo = self::pdo();
        $now = time();

        $stmt = $pdo->prepare(
            "SELECT expired FROM newsletter_subunsub_confirms
             WHERE mlid = :mlid AND subscriber = :subscriber AND kind = :kind"
        );
        $stmt->execute(['mlid' => $mlid, 'subscriber' => $subscriber, 'kind' => $kind]);
        $expired = $stmt->fetchColumn();
        if ($expired !== false && self::isRecent((int) $expired, $expireHours, $now)) {
            return null;
        }

        $token = bin2hex(random_bytes(16));
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "DELETE FROM newsletter_subunsub_confirms WHERE mlid = :mlid AND subscriber = :subscriber AND kind = :kind"
            )->execute(['mlid' => $mlid, 'subscriber' => $subscriber, 'kind' => $kind]);
            $pdo->prepare(
                "INSERT INTO newsletter_subunsub_confirms (mlid, mail, subscriber, kind, token, expired)
                 VALUES (:mlid, :mail, :subscriber, :kind, :token, :expired)"
            )->execute([
                'mlid' => $mlid,
                'mail' => $mail,
                'subscriber' => $subscriber,
                'kind' => $kind,
                'token' => $token,
                'expired' => $now + $expireHours * 3600,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $token;
    }

    /**
     * Whether a pending request with this expiry time was made recently.
     */
    public static function isRecent(int $expired, int $expireHours, int $now): bool
    {
        $createdAt = $expired - $expireHours * 3600;

        return $expired > $now && $now - $createdAt < self::RESEND_INTERVAL_SECONDS;
    }

    /**
     * @return array{id: int, mail: string, subscriber: string}|null
     */
    public static function find(string $mlid, string $token, string $kind): ?array
    {
        $stmt = self::pdo()->prepare(
            "SELECT id, mail, subscriber FROM newsletter_subunsub_confirms
             WHERE mlid = :mlid AND token = :token AND kind = :kind AND expired > :now"
        );
        $stmt->execute(['mlid' => $mlid, 'token' => $token, 'kind' => $kind, 'now' => time()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : [
            'id' => (int) $row['id'],
            'mail' => (string) $row['mail'],
            'subscriber' => (string) $row['subscriber'],
        ];
    }

    public static function delete(int $id): void
    {
        self::pdo()->prepare("DELETE FROM newsletter_subunsub_confirms WHERE id = :id")->execute(['id' => $id]);
    }

    /**
     * Drops a request whose confirmation mail could not be sent, so the next
     * attempt is not held back by RESEND_INTERVAL_SECONDS.
     */
    public static function deleteToken(string $mlid, string $token): void
    {
        self::pdo()->prepare("DELETE FROM newsletter_subunsub_confirms WHERE mlid = :mlid AND token = :token")
            ->execute(['mlid' => $mlid, 'token' => $token]);
    }

    /**
     * @throws BackendConnectionException when the iredadmin database is not reachable
     */
    private static function pdo(): \PDO
    {
        $pdo = Settings::getInstance()->backend === 'pgsql'
            ? IredadminPgsqlConnection::getInstance()->getPdo()
            : IredadminConnection::getInstance()->getPdo();
        if ($pdo === null) {
            throw new BackendConnectionException('iredadmin database not available');
        }

        return $pdo;
    }
}
