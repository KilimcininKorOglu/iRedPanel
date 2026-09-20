<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Repositories\QuotaRepositoryInterface;

class PgsqlQuotaRepository implements QuotaRepositoryInterface
{
    public function getDomainUsedQuotas(string $domain): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT username, COALESCE(bytes, 0) AS bytes, COALESCE(messages, 0) AS messages
             FROM used_quota
             WHERE SPLIT_PART(username, '@', 2) = :domain"
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
        $stmt = PgsqlConnection::getInstance()->getPdo()
            ->prepare("SELECT bytes FROM used_quota WHERE username = :username");
        $stmt->execute(['username' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : (int) $row['bytes'];
    }
}
