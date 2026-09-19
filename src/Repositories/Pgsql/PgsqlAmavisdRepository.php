<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\AmavisdRepositoryInterface;
use App\Services\AmavisdReleaseClient;

class PgsqlAmavisdRepository implements AmavisdRepositoryInterface
{
    public function getQuarantinedMessages(int $page, int $perPage, ?string $domain = null): PaginatedResult
    {
        $conn = AmavisdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            return new PaginatedResult([], 0, $page, $perPage);
        }

        $pdo = $conn->getPdo();
        $offset = ($page - 1) * $perPage;

        $where = '1=1';
        $params = [];
        if ($domain !== null && $domain !== '') {
            $where .= ' AND r.email LIKE :pattern';
            $params['pattern'] = "%@{$domain}";
        }

        $countStmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT m.mail_id) AS total
             FROM msgs m
             JOIN msgrcpt mr ON m.mail_id = mr.mail_id
             JOIN maddr r ON mr.rid = r.id
             JOIN quarantine q ON m.mail_id = q.mail_id
             WHERE {$where}"
        );
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetch()['total'];

        $stmt = $pdo->prepare(
            "SELECT DISTINCT m.mail_id, m.from_addr, m.subject, m.time_iso, m.time_num, m.content,
                    r.email AS recipient, m.spam_level
             FROM msgs m
             JOIN msgrcpt mr ON m.mail_id = mr.mail_id
             JOIN maddr r ON mr.rid = r.id
             JOIN quarantine q ON m.mail_id = q.mail_id
             WHERE {$where}
             ORDER BY m.time_num DESC
             LIMIT :perPage OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return new PaginatedResult(self::byteaAsText($stmt->fetchAll()), $totalCount, $page, $perPage);
    }

    /**
     * PDO returns the bytea columns of the amavisd schema (mail_id, from_addr,
     * subject, email) as streams, and the templates need their text.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function byteaAsText(array $rows): array
    {
        return array_map(
            static fn(array $row): array => array_map(
                static fn(mixed $value): mixed => is_resource($value) ? self::streamText($value) : $value,
                $row,
            ),
            $rows,
        );
    }

    /**
     * @param resource $stream
     */
    private static function streamText($stream): string
    {
        $text = stream_get_contents($stream);
        if ($text === false) {
            throw new \RuntimeException('Cannot read a bytea column from the Amavisd database');
        }
        return $text;
    }

    public function releaseMessage(string $mailId, string $requestedBy): void
    {
        $conn = AmavisdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            throw new \RuntimeException('Amavisd database not available');
        }

        $pdo = $conn->getPdo();

        // mail_id and secret_id are bytea columns in the PostgreSQL schema.
        $stmt = $pdo->prepare(
            "SELECT convert_from(m.secret_id, 'UTF8') AS secret_id FROM msgs m
             WHERE m.mail_id = convert_to(:mailId, 'UTF8')
               AND EXISTS (SELECT 1 FROM quarantine q WHERE q.mail_id = m.mail_id)
             LIMIT 1"
        );
        $stmt->execute(['mailId' => $mailId]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new \RuntimeException("Quarantined message not found: {$mailId}");
        }

        $settings = Settings::getInstance();
        AmavisdReleaseClient::release(
            $settings->amavisdQuarantineHost,
            $settings->amavisdQuarantinePort,
            $mailId,
            $row['secret_id'],
            $requestedBy
        );

        // Amavisd delivered the message, so it is no longer quarantined.
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE msgs SET content = 'C', quar_type = '' WHERE mail_id = convert_to(:mailId, 'UTF8')")
            ->execute(['mailId' => $mailId]);
        $pdo->prepare("DELETE FROM quarantine WHERE mail_id = convert_to(:mailId, 'UTF8')")
            ->execute(['mailId' => $mailId]);
        $pdo->commit();
    }

    public function deleteQuarantinedMessage(string $mailId): void
    {
        $conn = AmavisdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            throw new \RuntimeException('Amavisd database not available');
        }

        $pdo = $conn->getPdo();

        $stmt = $pdo->prepare("DELETE FROM quarantine WHERE mail_id = convert_to(:mailId, 'UTF8')");
        $stmt->execute(['mailId' => $mailId]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException("Quarantined message not found: {$mailId}");
        }
    }

    public function getMailLog(int $page, int $perPage, ?string $email = null): PaginatedResult
    {
        $conn = AmavisdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            return new PaginatedResult([], 0, $page, $perPage);
        }

        $pdo = $conn->getPdo();
        $offset = ($page - 1) * $perPage;

        $where = '1=1';
        $params = [];
        if ($email !== null && $email !== '') {
            $where .= ' AND (m.from_addr LIKE :email OR r.email LIKE :email2)';
            $params['email'] = "%{$email}%";
            $params['email2'] = "%{$email}%";
        }

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) AS total
             FROM msgs m
             JOIN msgrcpt mr ON m.mail_id = mr.mail_id
             JOIN maddr r ON mr.rid = r.id
             WHERE {$where}"
        );
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetch()['total'];

        $stmt = $pdo->prepare(
            "SELECT m.mail_id, m.from_addr, m.subject, m.time_iso, m.time_num, m.content,
                    r.email AS recipient, m.spam_level
             FROM msgs m
             JOIN msgrcpt mr ON m.mail_id = mr.mail_id
             JOIN maddr r ON mr.rid = r.id
             WHERE {$where}
             ORDER BY m.time_num DESC
             LIMIT :perPage OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return new PaginatedResult(self::byteaAsText($stmt->fetchAll()), $totalCount, $page, $perPage);
    }

    public function cleanupQuarantined(int $olderThanDays): int
    {
        $conn = AmavisdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            return 0;
        }

        $pdo = $conn->getPdo();

        $stmt = $pdo->prepare(
            "DELETE FROM quarantine
             WHERE mail_id IN (
                 SELECT q.mail_id FROM quarantine q
                 JOIN msgs m ON q.mail_id = m.mail_id
                 WHERE m.time_num < EXTRACT(EPOCH FROM (NOW() - INTERVAL '1 day' * :days))
             )"
        );
        $stmt->execute(['days' => $olderThanDays]);

        return $stmt->rowCount();
    }

    public function cleanupMailLog(int $olderThanDays): int
    {
        $conn = AmavisdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            return 0;
        }

        $pdo = $conn->getPdo();

        $stmt = $pdo->prepare(
            "DELETE FROM msgs WHERE time_num < EXTRACT(EPOCH FROM (NOW() - INTERVAL '1 day' * :days))"
        );
        $stmt->execute(['days' => $olderThanDays]);

        return $stmt->rowCount();
    }
}
