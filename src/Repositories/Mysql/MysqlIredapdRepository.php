<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Exceptions\BackendConnectionException;
use App\Models\PaginatedResult;
use App\Repositories\IredapdRepositoryInterface;
use App\Utils\IredapdAccount;

class MysqlIredapdRepository implements IredapdRepositoryInterface
{
    public function getThrottleSettings(string $account): array
    {
        $stmt = $this->pdo()->prepare(
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
        $pdo = $this->pdo();

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
        $stmt = $this->pdo()->prepare(
            "SELECT id, account, sender, comment, active
             FROM greylisting
             WHERE account = :account"
        );
        $stmt->execute(['account' => $account]);

        return $stmt->fetchAll();
    }

    public function setGreylistEnabled(string $account, bool $enabled): void
    {
        $pdo = $this->pdo();
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
        $stmt = $this->pdo()->prepare(
            "SELECT sender FROM greylisting_whitelists
             WHERE account = :account
             ORDER BY sender"
        );
        $stmt->execute(['account' => $account]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
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

    public function getGreylistTrackingPaginated(int $page, int $perPage): PaginatedResult
    {
        $pdo = $this->pdo();

        $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM greylisting_tracking WHERE passed = 1");
        $totalCount = (int) $countStmt->fetch()['total'];

        $stmt = $pdo->prepare(
            "SELECT sender, recipient, client_address, init_time, record_expired, passed, blocked_count
             FROM greylisting_tracking
             WHERE passed = 1
             ORDER BY init_time DESC
             LIMIT :perPage OFFSET :offset"
        );
        $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        return new PaginatedResult($stmt->fetchAll(), $totalCount, $page, $perPage);
    }

    public function getWblistRdns(): array
    {
        $whitelists = [];
        $blacklists = [];

        $stmt = $this->pdo()->query("SELECT rdns, wb FROM wblist_rdns ORDER BY rdns");
        while ($row = $stmt->fetch()) {
            if ($row['wb'] === 'W') {
                $whitelists[] = $row['rdns'];
            } else {
                $blacklists[] = $row['rdns'];
            }
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
        $stmt = $this->pdo()->query(
            "SELECT client_address FROM senderscore_cache WHERE time = 4102444799 ORDER BY client_address"
        );

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function setSenderScoreWhitelist(array $ips): void
    {
        $this->replaceAll(function (\PDO $pdo) use ($ips): void {
            $pdo->exec("DELETE FROM senderscore_cache WHERE time = 4102444799");

            // iRedAPD may already cache the IP with its looked-up score.
            $stmt = $pdo->prepare(
                "INSERT INTO senderscore_cache (client_address, score, time) VALUES (:ip, 100, 4102444799)
                 ON DUPLICATE KEY UPDATE score = 100, time = 4102444799"
            );
            foreach ($ips as $ip) {
                $stmt->execute(['ip' => $ip]);
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
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $work($pdo);
            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @throws BackendConnectionException when the connection failed, so a page
     *                                    never shows an empty list for a database it cannot read
     */
    private function pdo(): \PDO
    {
        $pdo = IredapdConnection::getInstance()->getPdo();
        if ($pdo === null) {
            throw new BackendConnectionException('iRedAPD database not available');
        }
        return $pdo;
    }
}
