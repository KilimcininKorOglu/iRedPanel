<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Exceptions\BackendConnectionException;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\AccountMatch;
use App\Repositories\AmavisdAccountSettings;
use App\Repositories\AmavisdRecipientMail;
use App\Repositories\AmavisdRepositoryInterface;
use App\Services\AmavisdReleaseClient;
use App\Utils\SqlLike;

class MysqlAmavisdRepository implements AmavisdRepositoryInterface
{
    public function getQuarantinedMessages(int $page, int $perPage, ?string $domain = null): PaginatedResult
    {
        $conn = AmavisdConnection::getInstance();
        if (!$conn->isAvailable()) {
            return new PaginatedResult([], 0, $page, $perPage);
        }

        $pdo = $conn->getPdo();
        $offset = ($page - 1) * $perPage;

        $where = '1=1';
        $params = [];
        if ($domain !== null && $domain !== '') {
            $where .= ' AND r.email LIKE :pattern ' . SqlLike::ESCAPE;
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

        $items = $stmt->fetchAll();

        return new PaginatedResult($items, $totalCount, $page, $perPage);
    }

    public function releaseMessage(string $mailId, string $requestedBy): void
    {
        $conn = AmavisdConnection::getInstance();
        if (!$conn->isAvailable()) {
            throw new \RuntimeException('Amavisd database not available');
        }

        $pdo = $conn->getPdo();

        $stmt = $pdo->prepare(
            "SELECT m.secret_id FROM msgs m
             WHERE m.mail_id = :mailId AND EXISTS (SELECT 1 FROM quarantine q WHERE q.mail_id = m.mail_id)
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
        $pdo->prepare("UPDATE msgs SET content = 'C', quar_type = '' WHERE mail_id = :mailId")
            ->execute(['mailId' => $mailId]);
        $pdo->prepare("DELETE FROM quarantine WHERE mail_id = :mailId")->execute(['mailId' => $mailId]);
        $pdo->commit();
    }

    public function deleteQuarantinedMessage(string $mailId): void
    {
        $conn = AmavisdConnection::getInstance();
        if (!$conn->isAvailable()) {
            throw new \RuntimeException('Amavisd database not available');
        }

        $pdo = $conn->getPdo();

        $stmt = $pdo->prepare("DELETE FROM quarantine WHERE mail_id = :mailId");
        $stmt->execute(['mailId' => $mailId]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException("Quarantined message not found: {$mailId}");
        }
    }

    public function getMailLog(int $page, int $perPage, ?string $email = null, ?string $domain = null): PaginatedResult
    {
        $conn = AmavisdConnection::getInstance();
        if (!$conn->isAvailable()) {
            return new PaginatedResult([], 0, $page, $perPage);
        }

        $pdo = $conn->getPdo();
        $offset = ($page - 1) * $perPage;

        $where = '1=1';
        $params = [];
        if ($email !== null && $email !== '') {
            $where .= ' AND (m.from_addr LIKE :email ' . SqlLike::ESCAPE . ' OR r.email LIKE :email2 ' . SqlLike::ESCAPE . ')';
            $params['email'] = '%' . SqlLike::escape($email) . '%';
            $params['email2'] = $params['email'];
        }
        if ($domain !== null && $domain !== '') {
            $where .= " AND (s.email LIKE :domainPattern " . SqlLike::ESCAPE . " OR r.email LIKE :domainPattern2 " . SqlLike::ESCAPE . ')';
            $params['domainPattern'] = '%@' . SqlLike::escape($domain);
            $params['domainPattern2'] = $params['domainPattern'];
        }

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) AS total
             FROM msgs m
             JOIN msgrcpt mr ON m.mail_id = mr.mail_id
             JOIN maddr r ON mr.rid = r.id
             LEFT JOIN maddr s ON m.sid = s.id
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
             LEFT JOIN maddr s ON m.sid = s.id
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

        $items = $stmt->fetchAll();

        return new PaginatedResult($items, $totalCount, $page, $perPage);
    }

    public function cleanupQuarantined(int $olderThanDays): int
    {
        $conn = AmavisdConnection::getInstance();
        if (!$conn->isAvailable()) {
            return 0;
        }

        $pdo = $conn->getPdo();

        $stmt = $pdo->prepare(
            "DELETE q FROM quarantine q
             JOIN msgs m ON q.mail_id = m.mail_id
             WHERE m.time_num < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL :days DAY))"
        );
        $stmt->execute(['days' => $olderThanDays]);

        return $stmt->rowCount();
    }

    public function cleanupMailLog(int $olderThanDays): int
    {
        $conn = AmavisdConnection::getInstance();
        if (!$conn->isAvailable()) {
            return 0;
        }

        $pdo = $conn->getPdo();

        $stmt = $pdo->prepare(
            "DELETE FROM msgs WHERE time_num < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL :days DAY))"
        );
        $stmt->execute(['days' => $olderThanDays]);

        return $stmt->rowCount();
    }

    public function getQuarantinedMailIds(?int $since = null): array
    {
        $stmt = $this->amavisdPdo()->prepare(
            "SELECT m.mail_id FROM msgs m
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
             WHERE a.email = :email AND m.quar_type = 'Q' AND m.time_num > :since
             ORDER BY m.time_num DESC
             LIMIT :lim"
        );
        $stmt->bindValue('email', $email);
        $stmt->bindValue('since', $since, \PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getQuarantinedMailText(string $mailId): string
    {
        $stmt = $this->amavisdPdo()->prepare(
            "SELECT mail_text FROM quarantine WHERE mail_id = :mailId ORDER BY chunk_ind"
        );
        $stmt->execute(['mailId' => $mailId]);

        return implode('', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function getQuarantinedForUser(string $email, int $page, int $perPage): PaginatedResult
    {
        return $this->recipientMail()->quarantined($email, $page, $perPage);
    }

    public function getReceivedMail(string $email, int $page, int $perPage): PaginatedResult
    {
        return $this->recipientMail()->received($email, $page, $perPage);
    }

    public function getSentMail(string $email, int $page, int $perPage): PaginatedResult
    {
        return $this->recipientMail()->sent($email, $page, $perPage);
    }

    public function releaseForRecipient(string $mailId, string $email): void
    {
        $settings = Settings::getInstance();
        $this->recipientMail()->release($mailId, $email, static fn (string $secretId) => AmavisdReleaseClient::release(
            $settings->amavisdQuarantineHost,
            $settings->amavisdQuarantinePort,
            $mailId,
            $secretId,
            $email,
            $email,
        ));
    }

    public function deleteForRecipient(string $mailId, string $email): void
    {
        $this->recipientMail()->delete($mailId, $email);
    }

    public function pendingQuarantineRecipients(string $mailId, string $domain): array
    {
        return $this->recipientMail()->pendingRecipients($mailId, $domain);
    }

    public function quarantinedAddresses(string $mailId): array
    {
        return $this->recipientMail()->addresses($mailId);
    }

    private function recipientMail(): AmavisdRecipientMail
    {
        return new AmavisdRecipientMail($this->amavisdPdo());
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
        return new AmavisdAccountSettings($this->amavisdPdo());
    }

    private function amavisdPdo(): \PDO
    {
        return AmavisdConnection::getInstance()->getPdo()
            ?? throw new BackendConnectionException('Amavisd database not available');
    }
}
