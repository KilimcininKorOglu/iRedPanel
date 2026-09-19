<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Models\SpamPolicy;
use App\Repositories\SpamPolicyRepositoryInterface;
use App\Utils\AmavisdAddress;

class MysqlSpamPolicyRepository implements SpamPolicyRepositoryInterface
{
    public function getPolicy(string $account): ?SpamPolicy
    {
        $pdo = AmavisdConnection::getInstance()->getPdo();

        // As iRedAdmin does, an account's own policy is the one named after it.
        // users.policy_id is not usable: it defaults to 1 (the global policy)
        // for rows that white/blacklist entries created.
        $stmt = $pdo->prepare("SELECT * FROM policy WHERE policy_name = :account LIMIT 1");
        $stmt->execute(['account' => $account]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return SpamPolicy::fromRow($row);
    }

    public function createOrUpdatePolicy(string $account, SpamPolicy $policy): bool
    {
        $pdo = AmavisdConnection::getInstance()->getPdo();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id FROM policy WHERE policy_name = :account LIMIT 1");
            $stmt->execute(['account' => $account]);
            $policyId = $stmt->fetchColumn();

            if ($policyId !== false) {
                $policyId = (int) $policyId;
                $this->updatePolicyRow($pdo, $policyId, $policy);
            } else {
                $policyId = $this->insertPolicyRow($pdo, $policy, $account);
            }
            $this->linkUser($pdo, $account, $policyId);

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function deletePolicy(string $account): bool
    {
        $pdo = AmavisdConnection::getInstance()->getPdo();

        // As iRedAdmin does: the account's own policy carries its name, and the
        // users row stays because white/blacklist entries refer to it.
        // users.policy_id is NOT NULL, so it keeps the old ID, and Amavisd
        // falls through to the next lookup.
        $pdo->prepare("DELETE FROM policy WHERE policy_name = :account")->execute(['account' => $account]);

        return true;
    }

    public function listPolicies(?string $domain = null): array
    {
        $pdo = AmavisdConnection::getInstance()->getPdo();

        $where = "";
        $params = [];
        if ($domain !== null) {
            $where = "WHERE policy_name = :domain OR policy_name LIKE :pattern";
            $params = ['domain' => '@' . $domain, 'pattern' => '%@' . $domain];
        }

        $stmt = $pdo->prepare("SELECT * FROM policy {$where} ORDER BY policy_name");
        $stmt->execute($params);

        $results = [];
        while ($row = $stmt->fetch()) {
            $results[] = [
                'account' => $row['policy_name'],
                'policy' => SpamPolicy::fromRow($row),
            ];
        }

        return $results;
    }

    /**
     * Points the account's users row, which Amavisd looks up, at its policy.
     */
    private function linkUser(\PDO $pdo, string $account, int $policyId): void
    {
        $stmt = $pdo->prepare("UPDATE users SET policy_id = :pid WHERE email = :account");
        $stmt->execute(['pid' => $policyId, 'account' => $account]);

        $exists = $pdo->prepare("SELECT 1 FROM users WHERE email = :account");
        $exists->execute(['account' => $account]);
        if ($exists->fetchColumn() === false) {
            $pdo->prepare("INSERT INTO users (email, priority, policy_id) VALUES (:email, :priority, :pid)")
                ->execute(['email' => $account, 'priority' => AmavisdAddress::priority($account), 'pid' => $policyId]);
        }
    }

    private function insertPolicyRow(\PDO $pdo, SpamPolicy $policy, string $account): int
    {
        // getPolicy() finds the policy by this name, so it is always the account.
        $columns = ['policy_name' => $account] + $policy->columns();
        $names = implode(', ', array_keys($columns));
        $placeholders = implode(', ', array_map(static fn (string $column): string => ':' . $column, array_keys($columns)));

        $pdo->prepare("INSERT INTO policy ({$names}) VALUES ({$placeholders})")->execute($columns);

        return (int) $pdo->lastInsertId();
    }

    private function updatePolicyRow(\PDO $pdo, int $policyId, SpamPolicy $policy): void
    {
        $columns = $policy->columns();
        $assignments = implode(', ', array_map(static fn (string $column): string => "{$column} = :{$column}", array_keys($columns)));

        $pdo->prepare("UPDATE policy SET {$assignments} WHERE id = :id")->execute($columns + ['id' => $policyId]);
    }
}
