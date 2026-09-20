<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Repositories\QuotaRepositoryInterface;

class MysqlQuotaRepository implements QuotaRepositoryInterface
{
    public function getDomainUsedQuotas(string $domain): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT username, COALESCE(bytes, 0) AS bytes, COALESCE(messages, 0) AS messages
             FROM used_quota
             WHERE SUBSTRING_INDEX(username, '@', -1) = :domain"
        );
        $stmt->execute(['domain' => $domain]);

        $quotas = [];
        while ($row = $stmt->fetch()) {
            $quotas[$row['username']] = [
                'bytes' => (int) $row['bytes'],
                'messages' => (int) $row['messages'],
            ];
        }

        return $quotas;
    }

    public function getUsedBytes(string $email): ?int
    {
        $stmt = $this->pdo()->prepare("SELECT bytes FROM used_quota WHERE username = :username");
        $stmt->execute(['username' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : (int) $row['bytes'];
    }

    /**
     * Returns the connection that holds the Dovecot used_quota table (vmail on SQL backends).
     */
    protected function pdo(): \PDO
    {
        return MysqlConnection::getInstance()->getPdo();
    }
}
