<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Models\Alias;
use App\Models\PaginatedResult;
use App\Repositories\AliasRepositoryInterface;

/**
 * Mail aliases in the iRedMail SQL layout: one `alias` row per alias, one
 * `forwardings` row with `is_list = 1` per member, and the catch-all as
 * `forwardings` rows whose `address` is the bare domain name.
 */
class PgsqlAliasRepository implements AliasRepositoryInterface
{
    public function getAliasesPaginated(int $page, int $perPage, ?string $domain = null): PaginatedResult
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();
        $offset = ($page - 1) * $perPage;

        $where = "";
        $params = [];
        if ($domain !== null) {
            $where = "WHERE domain = :domain";
            $params['domain'] = $domain;
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM alias {$where}");
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetch()['total'];

        $stmt = $pdo->prepare(
            "SELECT address, domain, name, accesspolicy, active, created, modified
             FROM alias {$where}
             ORDER BY address
             LIMIT :perPage OFFSET :offset"
        );
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = $this->rowToAlias($row);
        }

        return new PaginatedResult($items, $totalCount, $page, $perPage);
    }

    public function getAlias(string $address): ?Alias
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT address, domain, name, accesspolicy, active, created, modified
             FROM alias
             WHERE address = :address
             LIMIT 1"
        );
        $stmt->execute(['address' => $address]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->rowToAlias($row);
    }

    public function createAlias(string $address, string $domain, string $name, array $members, string $accessPolicy): bool
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO alias (address, domain, name, accesspolicy, active, created)
                 VALUES (:address, :domain, :name, :accesspolicy, 1, NOW())"
            )->execute([
                'address' => $address,
                'domain' => $domain,
                'name' => $name,
                'accesspolicy' => $accessPolicy,
            ]);

            $this->insertMembers($address, $domain, $members, true);

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function updateAlias(string $address, string $name, array $members, string $accessPolicy, bool $active): bool
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE alias SET name = :name, accesspolicy = :accesspolicy,
                 active = :active, modified = NOW()
                 WHERE address = :address"
            )->execute([
                'name' => $name,
                'accesspolicy' => $accessPolicy,
                'active' => $active ? 1 : 0,
                'address' => $address,
            ]);

            $pdo->prepare("DELETE FROM forwardings WHERE address = :address AND is_list = 1")
                ->execute(['address' => $address]);

            $this->insertMembers($address, explode('@', $address, 2)[1], $members, $active);

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function deleteAlias(string $address): bool
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM forwardings WHERE address = :address AND is_list = 1")
                ->execute(['address' => $address]);
            $pdo->prepare("DELETE FROM moderators WHERE address = :address")
                ->execute(['address' => $address]);
            $pdo->prepare("DELETE FROM alias WHERE address = :address")
                ->execute(['address' => $address]);

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function getAliasMembers(string $address): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        // No active filter: a disabled alias keeps its members.
        $stmt = $pdo->prepare(
            "SELECT forwarding FROM forwardings
             WHERE address = :address AND is_list = 1
             ORDER BY forwarding"
        );
        $stmt->execute(['address' => $address]);

        $members = [];
        while ($row = $stmt->fetch()) {
            $members[] = $row['forwarding'];
        }

        return $members;
    }

    public function addAliasMember(string $address, string $member): bool
    {
        $alias = $this->getAlias($address);
        if ($alias === null) {
            throw new \RuntimeException("Alias not found: {$address}");
        }

        $this->insertMembers($address, $alias->domain, [$member], $alias->active);
        return true;
    }

    public function removeAliasMember(string $address, string $member): bool
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $pdo->prepare(
            "DELETE FROM forwardings WHERE address = :address AND forwarding = :forwarding AND is_list = 1"
        )->execute(['address' => $address, 'forwarding' => $member]);

        return true;
    }

    public function getModerators(string $address): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT moderator FROM moderators WHERE address = :address ORDER BY moderator"
        );
        $stmt->execute(['address' => $address]);

        $moderators = [];
        while ($row = $stmt->fetch()) {
            $moderators[] = $row['moderator'];
        }

        return $moderators;
    }

    public function setModerators(string $address, array $moderators): bool
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $domain = explode('@', $address, 2)[1];

        $pdo->prepare("DELETE FROM moderators WHERE address = :address")
            ->execute(['address' => $address]);

        $stmt = $pdo->prepare(
            "INSERT INTO moderators (address, moderator, domain, dest_domain)
             VALUES (:address, :moderator, :domain, :destDomain)"
        );

        foreach ($moderators as $moderator) {
            $moderator = trim($moderator);
            if ($moderator === '') {
                continue;
            }
            $destDomain = str_contains($moderator, '@') ? explode('@', $moderator, 2)[1] : $domain;
            $stmt->execute([
                'address' => $address,
                'moderator' => $moderator,
                'domain' => $domain,
                'destDomain' => $destDomain,
            ]);
        }

        return true;
    }

    public function getUserAliases(string $email): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT address FROM forwardings
             WHERE forwarding = :email AND is_alias = 1 AND active = 1
             ORDER BY address"
        );
        $stmt->execute(['email' => $email]);

        $aliases = [];
        while ($row = $stmt->fetch()) {
            $aliases[] = $row['address'];
        }

        return $aliases;
    }

    public function addUserAlias(string $email, string $aliasAddress): bool
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $domain = explode('@', $email, 2)[1];
        $aliasDomain = str_contains($aliasAddress, '@') ? explode('@', $aliasAddress, 2)[1] : $domain;

        $stmt = $pdo->prepare(
            "INSERT INTO forwardings (address, forwarding, domain, dest_domain, is_alias, active)
             VALUES (:address, :forwarding, :domain, :destDomain, 1, 1)
             ON CONFLICT DO NOTHING"
        );
        $stmt->execute([
            'address' => $aliasAddress,
            'forwarding' => $email,
            'domain' => $aliasDomain,
            'destDomain' => $domain,
        ]);

        return true;
    }

    public function removeUserAlias(string $email, string $aliasAddress): bool
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "DELETE FROM forwardings WHERE address = :address AND forwarding = :email AND is_alias = 1"
        );
        $stmt->execute(['address' => $aliasAddress, 'email' => $email]);

        return true;
    }

    public function isAddressInUse(string $address): bool
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        // forwardings.address covers mailbox forwardings, alias members, lists and per-user aliases.
        $stmt = $pdo->prepare(
            "SELECT 1 FROM mailbox WHERE username = :mailbox
             UNION SELECT 1 FROM alias WHERE address = :alias
             UNION SELECT 1 FROM maillists WHERE address = :list
             UNION SELECT 1 FROM forwardings WHERE address = :forwarding"
        );
        $stmt->execute(['mailbox' => $address, 'alias' => $address, 'list' => $address, 'forwarding' => $address]);

        return $stmt->fetch() !== false;
    }

    public function getCatchall(string $domain): ?string
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT forwarding FROM forwardings WHERE address = :domain ORDER BY forwarding LIMIT 1"
        );
        $stmt->execute(['domain' => $domain]);

        $row = $stmt->fetch();
        return $row !== false ? $row['forwarding'] : null;
    }

    public function setCatchall(string $domain, ?string $targetEmail): bool
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM forwardings WHERE address = :domain")
                ->execute(['domain' => $domain]);

            if ($targetEmail !== null && $targetEmail !== '') {
                $destDomain = str_contains($targetEmail, '@') ? explode('@', $targetEmail, 2)[1] : $domain;
                $pdo->prepare(
                    "INSERT INTO forwardings (address, forwarding, domain, dest_domain, active)
                     VALUES (:address, :forwarding, :domain, :destDomain, 1)"
                )->execute([
                    'address' => $domain,
                    'forwarding' => $targetEmail,
                    'domain' => $domain,
                    'destDomain' => $destDomain,
                ]);
            }

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function enableDisableAlias(string $address, bool $active): bool
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        // Postfix reads forwardings.active, so the member rows follow the alias status.
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE alias SET active = :active, modified = NOW() WHERE address = :address")
                ->execute(['active' => $active ? 1 : 0, 'address' => $address]);
            $pdo->prepare("UPDATE forwardings SET active = :active WHERE address = :address AND is_list = 1")
                ->execute(['active' => $active ? 1 : 0, 'address' => $address]);

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function countAliasesForDomain(string $domain): int
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            "SELECT (SELECT COUNT(*) FROM alias WHERE domain = :aliasDomain)
                  + (SELECT COUNT(*) FROM maillists WHERE domain = :listDomain) AS cnt"
        );
        $stmt->execute(['aliasDomain' => $domain, 'listDomain' => $domain]);
        return (int) $stmt->fetch()['cnt'];
    }

    /**
     * @param string[] $members
     */
    private function insertMembers(string $address, string $domain, array $members, bool $active): void
    {
        $stmt = PgsqlConnection::getInstance()->getPdo()->prepare(
            "INSERT INTO forwardings (address, forwarding, domain, dest_domain, is_list, active)
             VALUES (:address, :forwarding, :domain, :destDomain, 1, :active)
             ON CONFLICT DO NOTHING"
        );

        foreach ($members as $member) {
            $member = trim($member);
            if ($member === '') {
                continue;
            }
            $stmt->execute([
                'address' => $address,
                'forwarding' => $member,
                'domain' => $domain,
                'destDomain' => str_contains($member, '@') ? explode('@', $member, 2)[1] : $domain,
                'active' => $active ? 1 : 0,
            ]);
        }
    }

    private function rowToAlias(array $row): Alias
    {
        return new Alias(
            address: $row['address'],
            domain: $row['domain'],
            name: $row['name'] ?? '',
            accessPolicy: $row['accesspolicy'] ?? 'public',
            active: (bool) ($row['active'] ?? true),
            created: $row['created'] ?? null,
            modified: $row['modified'] ?? null,
        );
    }
}
