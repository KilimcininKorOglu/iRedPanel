<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Repositories\WhiteBlacklistRepositoryInterface;
use App\Utils\AmavisdAddress;

class PgsqlWhiteBlacklistRepository implements WhiteBlacklistRepositoryInterface
{
    /**
     * [account column, address column] per table. `wblist` stores the local recipient in
     * `rid` (users.id) and the sender in `sid` (mailaddr.id); `outbound_wblist` stores the
     * external recipient in `rid` (mailaddr.id) and the local sender in `sid` (users.id).
     */
    private const COLUMNS = [
        'wblist' => ['rid', 'sid'],
        'outbound_wblist' => ['sid', 'rid'],
    ];

    public function getInboundList(string $account): array
    {
        return $this->getList($account, 'wblist');
    }

    public function addInboundEntry(string $account, string $sender, string $wb): bool
    {
        return $this->addEntry($account, $sender, $wb, 'wblist');
    }

    public function removeInboundEntry(string $account, string $sender): bool
    {
        return $this->removeEntry($account, $sender, 'wblist');
    }

    public function getOutboundList(string $account): array
    {
        return $this->getList($account, 'outbound_wblist');
    }

    public function addOutboundEntry(string $account, string $recipient, string $wb): bool
    {
        return $this->addEntry($account, $recipient, $wb, 'outbound_wblist');
    }

    public function removeOutboundEntry(string $account, string $recipient): bool
    {
        return $this->removeEntry($account, $recipient, 'outbound_wblist');
    }

    public function getOrCreateUserId(string $email): int
    {
        return $this->getOrCreateAddressId('users', $email);
    }

    public function getOrCreateMailaddrId(string $email): int
    {
        return $this->getOrCreateAddressId('mailaddr', $email);
    }

    /**
     * Returns the row ID of an address in users or mailaddr, with the
     * priority that iRedAdmin gives its address format.
     */
    private function getOrCreateAddressId(string $table, string $email): int
    {
        $pdo = AmavisdPgsqlConnection::getInstance()->getPdo();
        $priority = AmavisdAddress::priority($email);

        $stmt = $pdo->prepare("SELECT id, priority FROM {$table} WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        if ($row === false) {
            $stmt = $pdo->prepare("INSERT INTO {$table} (email, priority) VALUES (:email, :priority) RETURNING id");
            $stmt->execute(['email' => $email, 'priority' => $priority]);
            return (int) $stmt->fetchColumn();
        }
        if ((int) $row['priority'] !== $priority) {
            // Earlier panel versions wrote priorities that differ from iRedAdmin.
            $pdo->prepare("UPDATE {$table} SET priority = :priority WHERE id = :id")
                ->execute(['priority' => $priority, 'id' => $row['id']]);
        }
        return (int) $row['id'];
    }

    private function getList(string $account, string $table): array
    {
        $pdo = AmavisdPgsqlConnection::getInstance()->getPdo();

        $userId = $this->getUserId($account);
        if ($userId === null) {
            return [];
        }
        [$accountColumn, $addressColumn] = self::COLUMNS[$table];

        // mailaddr.email is bytea, which PDO returns as a stream.
        $stmt = $pdo->prepare(
            "SELECT convert_from(m.email, 'UTF8') AS sender, w.wb
             FROM {$table} w
             JOIN mailaddr m ON w.{$addressColumn} = m.id
             WHERE w.{$accountColumn} = :userId
             ORDER BY m.email"
        );
        $stmt->execute(['userId' => $userId]);

        $results = [];
        while ($row = $stmt->fetch()) {
            $results[] = [
                'sender' => $row['sender'],
                'wb' => $row['wb'],
            ];
        }

        return $results;
    }

    private function addEntry(string $account, string $sender, string $wb, string $table): bool
    {
        $pdo = AmavisdPgsqlConnection::getInstance()->getPdo();

        [$accountColumn, $addressColumn] = self::COLUMNS[$table];
        $ids = [$accountColumn => $this->getOrCreateUserId($account), $addressColumn => $this->getOrCreateMailaddrId($sender)];

        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE rid = :rid AND sid = :sid LIMIT 1");
        $stmt->execute($ids);

        if ($stmt->fetch() !== false) {
            $pdo->prepare("UPDATE {$table} SET wb = :wb WHERE rid = :rid AND sid = :sid")
                ->execute($ids + ['wb' => $wb]);
        } else {
            $pdo->prepare("INSERT INTO {$table} (rid, sid, wb) VALUES (:rid, :sid, :wb)")
                ->execute($ids + ['wb' => $wb]);
        }

        return true;
    }

    private function removeEntry(string $account, string $sender, string $table): bool
    {
        $pdo = AmavisdPgsqlConnection::getInstance()->getPdo();

        // users.email and mailaddr.email are unique, so each subquery returns at most one ID.
        [$accountColumn, $addressColumn] = self::COLUMNS[$table];
        $stmt = $pdo->prepare(
            "DELETE FROM {$table}
             WHERE {$accountColumn} = (SELECT id FROM users WHERE email = :account)
               AND {$addressColumn} = (SELECT id FROM mailaddr WHERE email = :sender)"
        );
        $stmt->execute(['account' => $account, 'sender' => $sender]);

        return $stmt->rowCount() > 0;
    }

    private function getUserId(string $email): ?int
    {
        $pdo = AmavisdPgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row !== false ? (int) $row['id'] : null;
    }

}
