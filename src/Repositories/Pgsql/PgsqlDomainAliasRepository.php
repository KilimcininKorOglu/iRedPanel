<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Models\DomainAlias;
use App\Models\PaginatedResult;
use App\Repositories\DomainAliasRepositoryInterface;

class PgsqlDomainAliasRepository implements DomainAliasRepositoryInterface
{
    public function getAliasesForDomain(string $domain): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT alias_domain, target_domain, active, created, modified
             FROM alias_domain
             WHERE target_domain = :domain
             ORDER BY alias_domain"
        );
        $stmt->execute(['domain' => $domain]);

        $aliases = [];
        while ($row = $stmt->fetch()) {
            $aliases[] = DomainAlias::fromMysqlRow($row);
        }

        return $aliases;
    }

    public function getAllAliasesPaginated(int $page, int $perPage): PaginatedResult
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM alias_domain");
        $totalCount = (int) $countStmt->fetch()['total'];

        $stmt = $pdo->prepare(
            "SELECT alias_domain, target_domain, active, created, modified
             FROM alias_domain
             ORDER BY alias_domain
             LIMIT :perPage OFFSET :offset"
        );
        $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = DomainAlias::fromMysqlRow($row);
        }

        return new PaginatedResult($items, $totalCount, $page, $perPage);
    }

    public function getAlias(string $aliasDomain): ?DomainAlias
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT alias_domain, target_domain, active, created, modified
             FROM alias_domain
             WHERE alias_domain = :aliasDomain
             LIMIT 1"
        );
        $stmt->execute(['aliasDomain' => $aliasDomain]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return DomainAlias::fromMysqlRow($row);
    }

    public function createAlias(DomainAlias $alias): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "INSERT INTO alias_domain (alias_domain, target_domain, active, created)
             VALUES (:aliasDomain, :targetDomain, :active, NOW())"
        );
        $stmt->execute([
            'aliasDomain' => $alias->aliasDomain,
            'targetDomain' => $alias->targetDomain,
            'active' => $alias->active ? 1 : 0,
        ]);
    }

    public function deleteAlias(string $aliasDomain): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare("DELETE FROM alias_domain WHERE alias_domain = :aliasDomain");
        $stmt->execute(['aliasDomain' => $aliasDomain]);
    }

    public function enableDisableAlias(string $aliasDomain, bool $active): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "UPDATE alias_domain SET active = :active, modified = NOW() WHERE alias_domain = :aliasDomain"
        );
        $stmt->execute(['active' => $active ? 1 : 0, 'aliasDomain' => $aliasDomain]);
    }

    public function supportsStatus(): bool
    {
        return true;
    }
}
