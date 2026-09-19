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

    public function search(string $query, array $accountTypes = [], array $statusFilter = [], array $managedDomains = []): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();
        $likeQuery = '%' . SqlLike::escape($query) . '%';
        $searchAll = empty($accountTypes);

        $domainFilter = '';
        $domainParams = [];
        if (!empty($managedDomains)) {
            $placeholders = [];
            foreach ($managedDomains as $i => $d) {
                $key = "md{$i}";
                $placeholders[] = ":{$key}";
                $domainParams[$key] = $d;
            }
            $domainFilter = ' AND domain IN (' . implode(',', $placeholders) . ')';
        }

        $results = [
            'domains' => [],
            'users' => [],
            'aliases' => [],
            'mailingLists' => [],
            'admins' => [],
        ];

        if ($searchAll || in_array('domain', $accountTypes, true)) {
            $results['domains'] = $this->searchTable($pdo, 'domain', 'domain, description, active', ['domain', 'description'], $likeQuery, $statusFilter, $domainFilter, $domainParams);
        }

        if ($searchAll || in_array('user', $accountTypes, true)) {
            $results['users'] = $this->searchTable($pdo, 'mailbox', 'username, name, domain, active', ['username', 'name'], $likeQuery, $statusFilter, $domainFilter, $domainParams);
        }

        if ($searchAll || in_array('alias', $accountTypes, true)) {
            $results['aliases'] = $this->searchTable($pdo, 'alias', 'address, name, domain, active', ['address', 'name'], $likeQuery, $statusFilter, $domainFilter, $domainParams);
        }

        if ($searchAll || in_array('ml', $accountTypes, true)) {
            $results['mailingLists'] = $this->searchTable($pdo, 'maillists', 'address, name, domain, active', ['address', 'name'], $likeQuery, $statusFilter, $domainFilter, $domainParams);
        }

        if (($searchAll || in_array('admin', $accountTypes, true)) && empty($managedDomains)) {
            $results['admins'] = $this->searchTable($pdo, self::ADMINS_TABLE, 'username, name, active', ['username', 'name'], $likeQuery, $statusFilter, '', []);
        }

        return $results;
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
