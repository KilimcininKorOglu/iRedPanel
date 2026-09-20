<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Models\ApiKey;
use App\Repositories\ApiKeyRepositoryInterface;

class PgsqlApiKeyRepository implements ApiKeyRepositoryInterface
{
    public function findByKey(string $apiKey): ?ApiKey
    {
        $pdo = IredadminPgsqlConnection::getInstance()->getPdo();
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
        $pdo = IredadminPgsqlConnection::getInstance()->getPdo();
        if ($pdo === null) {
            return;
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS panel_api_keys (
                    id SERIAL PRIMARY KEY,
                    api_key VARCHAR(128) NOT NULL UNIQUE,
                    label VARCHAR(255) NOT NULL DEFAULT '',
                    role VARCHAR(10) NOT NULL DEFAULT 'global' CHECK (role IN ('global', 'domain')),
                    domains TEXT DEFAULT NULL,
                    read_only SMALLINT NOT NULL DEFAULT 0,
                    active SMALLINT NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )"
            );
        } catch (\PDOException $e) {
            error_log("Failed to create panel_api_keys table: " . $e->getMessage());
        }
    }
}
