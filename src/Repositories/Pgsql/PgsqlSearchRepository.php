<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Repositories\SearchRepositoryInterface;
use App\Utils\SqlLike;

class PgsqlSearchRepository implements SearchRepositoryInterface
{
    /** Standalone admins plus mailboxes with admin rights, as the admin list shows them. */
    private const ADMINS_TABLE = '(SELECT username, name, active FROM admin
        UNION SELECT username, name, active FROM mailbox WHERE isadmin = 1 OR isglobaladmin = 1) AS admins';

    /**
     * The searched tables, keyed by the result group the page reads. `type` is the
     * account type of the filter chips, `global` marks a table with no domain column.
     */
    private const SEARCH_TABLES = [
        'domains' => ['type' => 'domain', 'table' => 'domain', 'columns' => 'domain, description, active', 'search' => ['domain', 'description']],
        'users' => ['type' => 'user', 'table' => 'mailbox', 'columns' => 'username, name, domain, active', 'search' => ['username', 'name']],
        'aliases' => ['type' => 'alias', 'table' => 'alias', 'columns' => 'address, name, domain, active', 'search' => ['address', 'name']],
        'mailingLists' => ['type' => 'ml', 'table' => 'maillists', 'columns' => 'address, name, domain, active', 'search' => ['address', 'name']],
        'admins' => ['type' => 'admin', 'table' => null, 'columns' => 'username, name, active', 'search' => ['username', 'name'], 'global' => true],
    ];

    public function search(string $query, array $accountTypes = [], array $statusFilter = [], array $managedDomains = []): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();
        $likeQuery = '%' . SqlLike::escape($query) . '%';
        [$domainFilter, $domainParams] = self::domainFilter($managedDomains);

        $results = [];
        foreach (self::SEARCH_TABLES as $group => $t) {
            $isGlobal = $t['global'] ?? false;
            $results[$group] = [];
            if (!$this->wanted($t['type'], $accountTypes) || ($isGlobal && $managedDomains !== [])) {
                continue;
            }

            $results[$group] = $this->searchTable(
                $pdo,
                $t['table'] ?? self::ADMINS_TABLE,
                $t['columns'],
                $t['search'],
                $likeQuery,
                $statusFilter,
                $isGlobal ? '' : $domainFilter,
                $isGlobal ? [] : $domainParams
            );
        }

        return $results;
    }

    /**
     * An empty filter list means every account type.
     *
     * @param list<string> $accountTypes
     */
    private function wanted(string $type, array $accountTypes): bool
    {
        return $accountTypes === [] || in_array($type, $accountTypes, true);
    }

    /**
     * The domain scope of a domain admin as an SQL fragment plus its parameters.
     *
     * @param list<string> $managedDomains
     * @return array{string, array<string, string>}
     */
    private static function domainFilter(array $managedDomains): array
    {
        if ($managedDomains === []) {
            return ['', []];
        }

        $placeholders = [];
        $params = [];
        foreach ($managedDomains as $i => $d) {
            $placeholders[] = ":md{$i}";
            $params["md{$i}"] = $d;
        }

        return [' AND domain IN (' . implode(',', $placeholders) . ')', $params];
    }

    /**
     * Searches one table. Each search column gets its own placeholder, because
     * native prepared statements do not accept a repeated named parameter.
     */
    private function searchTable(\PDO $pdo, string $table, string $columns, array $searchCols, string $like, array $statusFilter, string $domainFilter, array $domainParams): array
    {
        $conditions = [];
        $params = $domainParams;
        foreach ($searchCols as $i => $col) {
            $conditions[] = "{$col} ILIKE :q{$i} " . SqlLike::ESCAPE;
            $params["q{$i}"] = $like;
        }
        $where = '(' . implode(' OR ', $conditions) . ')' . $domainFilter;

        if (in_array('active', $statusFilter, true) && !in_array('disabled', $statusFilter, true)) {
            $where .= ' AND active = 1';
        } elseif (in_array('disabled', $statusFilter, true) && !in_array('active', $statusFilter, true)) {
            $where .= ' AND active = 0';
        }

        $stmt = $pdo->prepare("SELECT {$columns} FROM {$table} WHERE {$where} ORDER BY {$searchCols[0]} LIMIT 50");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
