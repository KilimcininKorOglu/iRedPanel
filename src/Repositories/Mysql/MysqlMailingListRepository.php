<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Models\MailingList;
use App\Models\PaginatedResult;
use App\Repositories\MailingListRepositoryInterface;

class MysqlMailingListRepository implements MailingListRepositoryInterface
{
    public function getMailingListsPaginated(int $page, int $perPage, ?string $domain = null): PaginatedResult
    {
        $pdo = MysqlConnection::getInstance()->getPdo();
        $offset = ($page - 1) * $perPage;

        $where = "";
        $params = [];
        if ($domain !== null) {
            $where = "WHERE domain = :domain";
            $params['domain'] = $domain;
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM maillists {$where}");
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetch()['total'];

        $stmt = $pdo->prepare(
            "SELECT address, domain, name, transport, accesspolicy, maxmsgsize, active, created
             FROM maillists {$where}
             ORDER BY address
             LIMIT :perPage OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = $this->rowToMailingList($row);
        }

        return new PaginatedResult($items, $totalCount, $page, $perPage);
    }

    public function getMailingList(string $address): ?MailingList
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT address, domain, name, transport, accesspolicy, maxmsgsize, active, created
             FROM maillists WHERE address = :address LIMIT 1"
        );
        $stmt->execute(['address' => $address]);

        $row = $stmt->fetch();
        return $row !== false ? $this->rowToMailingList($row) : null;
    }

    public function createMailingList(string $address, string $domain, string $name,
                                     string $accessPolicy, int $maxMsgSize): bool
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $transport = "mlmmj:{$address}";

        $stmt = $pdo->prepare(
            "INSERT INTO maillists (address, domain, name, transport, accesspolicy, maxmsgsize, active, created)
             VALUES (:address, :domain, :name, :transport, :accesspolicy, :maxmsgsize, 1, NOW())"
        );
        $stmt->execute([
            'address' => $address,
            'domain' => $domain,
            'name' => $name,
            'transport' => $transport,
            'accesspolicy' => $accessPolicy,
            'maxmsgsize' => $maxMsgSize,
        ]);

        return true;
    }

    public function updateMailingList(string $address, string $name, string $accessPolicy,
                                     int $maxMsgSize, bool $active): bool
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "UPDATE maillists SET name = :name, accesspolicy = :accesspolicy,
             maxmsgsize = :maxmsgsize, active = :active
             WHERE address = :address"
        );
        $stmt->execute([
            'name' => $name,
            'accesspolicy' => $accessPolicy,
            'maxmsgsize' => $maxMsgSize,
            'active' => $active ? 1 : 0,
            'address' => $address,
        ]);

        return true;
    }

    public function deleteMailingList(string $address): bool
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM maillist_owners WHERE address = :address")
                ->execute(['address' => $address]);
            $pdo->prepare("DELETE FROM maillists WHERE address = :address")
                ->execute(['address' => $address]);
            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function getOwners(string $address): array
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare("SELECT owner FROM maillist_owners WHERE address = :address ORDER BY owner");
        $stmt->execute(['address' => $address]);

        $owners = [];
        while ($row = $stmt->fetch()) {
            $owners[] = $row['owner'];
        }

        return $owners;
    }

    public function setOwners(string $address, array $owners): bool
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $ml = $this->getMailingList($address);
        $domain = $ml ? $ml->domain : (explode('@', $address, 2)[1] ?? '');

        $pdo->prepare("DELETE FROM maillist_owners WHERE address = :address")
            ->execute(['address' => $address]);

        $stmt = $pdo->prepare(
            "INSERT INTO maillist_owners (address, domain, dest_domain, owner)
             VALUES (:address, :domain, :destDomain, :owner)"
        );

        foreach ($owners as $owner) {
            $owner = trim($owner);
            if ($owner === '') {
                continue;
            }
            $destDomain = str_contains($owner, '@') ? explode('@', $owner, 2)[1] : $domain;
            $stmt->execute([
                'address' => $address,
                'domain' => $domain,
                'destDomain' => $destDomain,
                'owner' => $owner,
            ]);
        }

        return true;
    }

    public function enableDisableMailingList(string $address, bool $active): bool
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $pdo->prepare("UPDATE maillists SET active = :active WHERE address = :address")
            ->execute(['active' => $active ? 1 : 0, 'address' => $address]);

        return true;
    }

    private function rowToMailingList(array $row): MailingList
    {
        return new MailingList(
            address: $row['address'],
            domain: $row['domain'],
            name: $row['name'] ?? '',
            accessPolicy: $row['accesspolicy'] ?? 'public',
            transport: $row['transport'] ?? '',
            maxMsgSize: (int) ($row['maxmsgsize'] ?? 0),
            active: (bool) ($row['active'] ?? true),
            created: $row['created'] ?? null,
        );
    }
}
