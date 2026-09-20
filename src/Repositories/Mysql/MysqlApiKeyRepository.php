<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Models\ApiKey;
use App\Repositories\ApiKeyRepositoryInterface;

class MysqlApiKeyRepository implements ApiKeyRepositoryInterface
{
    public function findByKey(string $apiKey): ?ApiKey
    {
        $pdo = IredadminConnection::getInstance()->getPdo();
        if ($pdo === null) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT id, api_key, label, role, domains, read_only, active, created_at
                 FROM panel_api_keys
                 WHERE api_key = :key AND active = 1
                 LIMIT 1"
            );
            $stmt->execute(['key' => $apiKey]);
            $row = $stmt->fetch();

            return $row ? ApiKey::fromRow($row) : null;
        } catch (\PDOException) {
            return null;
        }
    }

    public function ensureTableExists(): void
    {
        $pdo = IredadminConnection::getInstance()->getPdo();
        if ($pdo === null) {
            return;
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS panel_api_keys (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    api_key VARCHAR(128) NOT NULL UNIQUE,
                    label VARCHAR(255) NOT NULL DEFAULT '',
                    role ENUM('global', 'domain') NOT NULL DEFAULT 'global',
                    domains TEXT DEFAULT NULL,
                    read_only TINYINT(1) NOT NULL DEFAULT 0,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (\PDOException $e) {
            error_log("Failed to create panel_api_keys table: " . $e->getMessage());
        }
    }
}
