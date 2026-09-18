<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Models\PaginatedResult;
use App\Repositories\LastLoginRepositoryInterface;

class PgsqlLastLoginRepository implements LastLoginRepositoryInterface
{
    public function getLastLogin(string $username): ?array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT username, domain, imap, pop3, lda
             FROM last_login WHERE username = :username LIMIT 1"
        );
        $stmt->execute(['username' => $username]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->formatRow($row);
    }

    public function getLastLoginsPaginated(int $page, int $perPage, ?string $domain = null): PaginatedResult
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $where = "";
        $params = [];
        if ($domain !== null) {
            $where = "WHERE domain = :domain";
            $params['domain'] = $domain;
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM last_login {$where}");
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT username, domain, imap, pop3, lda
             FROM last_login {$where}
             ORDER BY GREATEST(COALESCE(imap, 0), COALESCE(pop3, 0), COALESCE(lda, 0)) DESC
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
            $items[] = $this->formatRow($row);
        }

        return new PaginatedResult($items, $totalCount, $page, $perPage);
    }

    private function formatRow(array $row): array
    {
        return [
            'username' => $row['username'],
            'domain' => $row['domain'] ?? '',
            'imap' => $this->timestampToDate($row['imap'] ?? null),
            'pop3' => $this->timestampToDate($row['pop3'] ?? null),
            'lda' => $this->timestampToDate($row['lda'] ?? null),
        ];
    }

    private function timestampToDate(int|string|null $timestamp): ?string
    {
        if ($timestamp === null || $timestamp === '' || (int) $timestamp === 0) {
            return null;
        }
        return date('Y-m-d H:i:s', (int) $timestamp);
    }
}
