<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Exceptions\BackendConnectionException;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\AccountMatch;
use App\Repositories\AmavisdAccountSettings;
use App\Repositories\AmavisdRepositoryInterface;
use App\Services\AmavisdReleaseClient;
use App\Utils\SqlLike;

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
            // The columns are bytea; convert_to keeps a backslash in the input a plain byte.
            $where .= " AND r.email LIKE convert_to(:pattern, 'UTF8') " . SqlLike::ESCAPE;
            $params['pattern'] = '%@' . SqlLike::escape($domain);
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
            $where .= " AND (m.from_addr LIKE convert_to(:email, 'UTF8') " . SqlLike::ESCAPE
                . " OR r.email LIKE convert_to(:email2, 'UTF8') " . SqlLike::ESCAPE . ')';
            $params['email'] = '%' . SqlLike::escape($email) . '%';
            $params['email2'] = $params['email'];
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

    public function getQuarantinedMailIds(?int $since = null): array
    {
        // mail_id is bytea; convert_from gives the text.
        $stmt = $this->amavisdPdo()->prepare(
            "SELECT convert_from(m.mail_id, 'UTF8') FROM msgs m
             WHERE EXISTS (SELECT 1 FROM quarantine q WHERE q.mail_id = m.mail_id) AND m.time_num >= :since
             ORDER BY m.time_num DESC"
        );
        $stmt->execute(['since' => $since ?? 0]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getQuarantinedForRecipient(string $email, int $since, int $limit = 100): array
    {
        $stmt = $this->amavisdPdo()->prepare(
            "SELECT m.mail_id, m.subject, m.from_addr, m.spam_level, m.time_num
             FROM msgs m
             JOIN msgrcpt mr ON m.mail_id = mr.mail_id
             JOIN maddr a ON a.id = mr.rid
             WHERE a.email = convert_to(:email, 'UTF8') AND m.quar_type = 'Q' AND m.time_num > :since
             ORDER BY m.time_num DESC
             LIMIT :lim"
        );
        $stmt->bindValue('email', $email);
        $stmt->bindValue('since', $since, \PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return self::byteaAsText($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function getQuarantinedMailText(string $mailId): string
    {
        $stmt = $this->amavisdPdo()->prepare(
            "SELECT mail_text FROM quarantine WHERE mail_id = convert_to(:mailId, 'UTF8') ORDER BY chunk_ind"
        );
        $stmt->execute(['mailId' => $mailId]);

        return implode('', array_map(
            static fn(mixed $chunk): string => is_resource($chunk) ? self::streamText($chunk) : (string) $chunk,
            $stmt->fetchAll(\PDO::FETCH_COLUMN),
        ));
    }

    public function deleteAccountSettings(array $accounts): void
    {
        $this->accountSettings()->delete(AccountMatch::accounts($accounts));
    }

    public function deleteDomainSettings(string $domain): void
    {
        $this->accountSettings()->delete(AccountMatch::domain($domain));
    }

    public function renameAccountSettings(string $oldAccount, string $newAccount): void
    {
        $this->accountSettings()->rename($oldAccount, $newAccount);
    }

    private function accountSettings(): AmavisdAccountSettings
    {
        // users.email is bytea in the PostgreSQL schema.
        return new AmavisdAccountSettings($this->amavisdPdo(), "convert_to(%s, 'UTF8')");
    }

    private function amavisdPdo(): \PDO
    {
        return AmavisdPgsqlConnection::getInstance()->getPdo()
            ?? throw new BackendConnectionException('Amavisd database not available');
    }
}
