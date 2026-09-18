<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Repositories\IredapdRepositoryInterface;
use App\Utils\IredapdAccount;

class PgsqlIredapdRepository implements IredapdRepositoryInterface
{
    public function getThrottleSettings(string $account): array
    {
        $conn = IredapdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            return [];
        }

        $pdo = $conn->getPdo();

        $stmt = $pdo->prepare(
            "SELECT id, account, kind, priority, period, max_msgs, max_quota, msg_size
             FROM throttle
             WHERE account = :account
             ORDER BY priority DESC"
        );
        $stmt->execute(['account' => $account]);

        return $stmt->fetchAll();
    }

    public function setThrottleSettings(string $account, string $kind, int $period, int $maxMsgs, int $maxQuota, int $msgSize): void
    {
        $conn = IredapdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            throw new \RuntimeException('iRedAPD database not available');
        }

        $pdo = $conn->getPdo();

        // Check if entry exists
        $stmt = $pdo->prepare(
            "SELECT id FROM throttle WHERE account = :account AND kind = :kind LIMIT 1"
        );
        $stmt->execute(['account' => $account, 'kind' => $kind]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare(
                "UPDATE throttle SET priority = :priority, period = :period, max_msgs = :maxMsgs,
                 max_quota = :maxQuota, msg_size = :msgSize
                 WHERE account = :account AND kind = :kind"
            );
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO throttle (account, kind, priority, period, max_msgs, max_quota, msg_size)
                 VALUES (:account, :kind, :priority, :period, :maxMsgs, :maxQuota, :msgSize)"
            );
        }

        $stmt->execute([
            'account' => $account,
            'kind' => $kind,
            'priority' => IredapdAccount::priority($account),
            'period' => $period,
            'maxMsgs' => $maxMsgs,
            'maxQuota' => $maxQuota,
            'msgSize' => $msgSize,
        ]);
    }

    public function getGreylistSettings(string $account): array
    {
        $conn = IredapdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            return [];
        }

        $pdo = $conn->getPdo();

        $stmt = $pdo->prepare(
            "SELECT id, account, sender, comment, active
             FROM greylisting
             WHERE account = :account"
        );
        $stmt->execute(['account' => $account]);

        return $stmt->fetchAll();
    }

    public function setGreylistEnabled(string $account, bool $enabled): void
    {
        $conn = IredapdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            throw new \RuntimeException('iRedAPD database not available');
        }

        $pdo = $conn->getPdo();
        // iRedAPD takes the matching row with the highest priority, so the
        // account row must outrank the global '@.' row (priority 0).
        $priority = IredapdAccount::priority($account);

        $stmt = $pdo->prepare(
            "SELECT id FROM greylisting WHERE account = :account AND sender = '@.' LIMIT 1"
        );
        $stmt->execute(['account' => $account]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare(
                "UPDATE greylisting SET active = :active, priority = :priority WHERE account = :account AND sender = '@.'"
            );
            $stmt->execute(['active' => $enabled ? 1 : 0, 'priority' => $priority, 'account' => $account]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO greylisting (account, priority, sender, sender_priority, active, comment)
                 VALUES (:account, :priority, '@.', 0, :active, '')"
            );
            $stmt->execute(['account' => $account, 'priority' => $priority, 'active' => $enabled ? 1 : 0]);
        }
    }

    /**
     * @return string[]
     */
    public function getWhitelistedSenders(string $account): array
    {
        $conn = IredapdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            return [];
        }

        $pdo = $conn->getPdo();

        $stmt = $pdo->prepare(
            "SELECT sender FROM greylisting_whitelists
             WHERE account = :account
             ORDER BY sender"
        );
        $stmt->execute(['account' => $account]);

        $senders = [];
        while ($row = $stmt->fetch()) {
            $senders[] = $row['sender'];
        }

        return $senders;
    }

    public function setWhitelistedSenders(string $account, array $senders): void
    {
        $this->replaceAll(function (\PDO $pdo) use ($account, $senders): void {
            $pdo->prepare("DELETE FROM greylisting_whitelists WHERE account = :account")
                ->execute(['account' => $account]);

            $stmt = $pdo->prepare(
                "INSERT INTO greylisting_whitelists (account, sender, comment)
                 VALUES (:account, :sender, '')"
            );
            foreach ($senders as $sender) {
                $sender = trim($sender);
                if ($sender !== '') {
                    $stmt->execute(['account' => $account, 'sender' => $sender]);
                }
            }
        });
    }

    public function getGreylistTrackingPaginated(int $page, int $perPage): \App\Models\PaginatedResult
    {
        $conn = IredapdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            return new \App\Models\PaginatedResult([], 0, $page, $perPage);
        }

        $pdo = $conn->getPdo();

        try {
            $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM greylisting_tracking WHERE passed = 1");
            $totalCount = (int) $countStmt->fetch()['total'];

            $offset = ($page - 1) * $perPage;

            $stmt = $pdo->prepare(
                "SELECT sender, recipient, client_address, init_time, record_expired, passed, blocked_count
                 FROM greylisting_tracking
                 WHERE passed = 1
                 ORDER BY init_time DESC
                 LIMIT :perPage OFFSET :offset"
            );
            $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $items = [];
            while ($row = $stmt->fetch()) {
                $items[] = $row;
            }

            return new \App\Models\PaginatedResult($items, $totalCount, $page, $perPage);
        } catch (\PDOException $e) {
            return new \App\Models\PaginatedResult([], 0, $page, $perPage);
        }
    }

    public function getWblistRdns(): array
    {
        $conn = IredapdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            return ['whitelists' => [], 'blacklists' => []];
        }

        $pdo = $conn->getPdo();
        $whitelists = [];
        $blacklists = [];

        try {
            $stmt = $pdo->query("SELECT rdns, wb FROM wblist_rdns ORDER BY rdns");
            while ($row = $stmt->fetch()) {
                if ($row['wb'] === 'W') {
                    $whitelists[] = $row['rdns'];
                } else {
                    $blacklists[] = $row['rdns'];
                }
            }
        } catch (\PDOException $e) {
            // Table may not exist
        }

        return ['whitelists' => $whitelists, 'blacklists' => $blacklists];
    }

    public function setWblistRdns(array $whitelists, array $blacklists): void
    {
        $this->replaceAll(function (\PDO $pdo) use ($whitelists, $blacklists): void {
            $pdo->exec("DELETE FROM wblist_rdns");

            $stmt = $pdo->prepare("INSERT INTO wblist_rdns (rdns, wb) VALUES (:rdns, :wb)");
            foreach (['W' => $whitelists, 'B' => $blacklists] as $wb => $names) {
                foreach ($names as $rdns) {
                    $rdns = trim($rdns);
                    if ($rdns !== '') {
                        $stmt->execute(['rdns' => strtolower($rdns), 'wb' => $wb]);
                    }
                }
            }
        });
    }

    public function getSenderScoreWhitelist(): array
    {
        $conn = IredapdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            return [];
        }

        $pdo = $conn->getPdo();
        $ips = [];

        try {
            $stmt = $pdo->query("SELECT client_address FROM senderscore_cache WHERE time = 4102444799 ORDER BY client_address");
            while ($row = $stmt->fetch()) {
                $ips[] = $row['client_address'];
            }
        } catch (\PDOException $e) {
            // Table may not exist
        }

        return $ips;
    }

    public function setSenderScoreWhitelist(array $ips): void
    {
        $this->replaceAll(function (\PDO $pdo) use ($ips): void {
            $pdo->exec("DELETE FROM senderscore_cache WHERE time = 4102444799");

            $stmt = $pdo->prepare(
                "INSERT INTO senderscore_cache (client_address, score, time) VALUES (:ip, 100, 4102444799)"
            );
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $stmt->execute(['ip' => $ip]);
                }
            }
        });
    }

    /**
     * Runs a delete-then-insert list update in one transaction, so a failed
     * insert leaves the stored list unchanged.
     *
     * @param callable(\PDO): void $work
     */
    private function replaceAll(callable $work): void
    {
        $conn = IredapdPgsqlConnection::getInstance();
        if (!$conn->isAvailable()) {
            throw new \RuntimeException('iRedAPD database not available');
        }

        $pdo = $conn->getPdo();
        $pdo->beginTransaction();
        try {
            $work($pdo);
            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
