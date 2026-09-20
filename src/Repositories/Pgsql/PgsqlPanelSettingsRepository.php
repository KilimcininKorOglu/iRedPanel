<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Repositories\PanelSettingsRepositoryInterface;

class PgsqlPanelSettingsRepository implements PanelSettingsRepositoryInterface
{
    public function getAll(): array
    {
        $pdo = IredadminPgsqlConnection::getInstance()->getPdo();
        if ($pdo === null) {
            return [];
        }

        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM panel_settings");
            $result = [];
            while ($row = $stmt->fetch()) {
                $result[$row['setting_key']] = $row['setting_value'];
            }
            return $result;
        } catch (\PDOException) {
            return [];
        }
    }

    public function get(string $key): ?string
    {
        $pdo = IredadminPgsqlConnection::getInstance()->getPdo();
        if ($pdo === null) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM panel_settings WHERE setting_key = :key LIMIT 1");
            $stmt->execute(['key' => $key]);
            $row = $stmt->fetch();
            return $row ? $row['setting_value'] : null;
        } catch (\PDOException) {
            return null;
        }
    }

    public function set(string $key, string $value, string $updatedBy = ''): void
    {
        $pdo = IredadminPgsqlConnection::getInstance()->getPdo();
        if ($pdo === null) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO panel_settings (setting_key, setting_value, updated_by)
                 VALUES (:key, :value, :updatedBy)
                 ON CONFLICT (setting_key) DO UPDATE
                 SET setting_value = EXCLUDED.setting_value,
                     updated_by = EXCLUDED.updated_by,
                     updated_at = CURRENT_TIMESTAMP"
            );
            $stmt->execute([
                'key' => $key,
                'value' => $value,
                'updatedBy' => $updatedBy,
            ]);
        } catch (\PDOException $e) {
            error_log("Failed to set panel setting '{$key}': " . $e->getMessage());
        }
    }

    public function setMany(array $settings, string $updatedBy = ''): void
    {
        $pdo = IredadminPgsqlConnection::getInstance()->getPdo();
        if ($pdo === null) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO panel_settings (setting_key, setting_value, updated_by)
                 VALUES (:key, :value, :updatedBy)
                 ON CONFLICT (setting_key) DO UPDATE
                 SET setting_value = EXCLUDED.setting_value,
                     updated_by = EXCLUDED.updated_by,
                     updated_at = CURRENT_TIMESTAMP"
            );
            foreach ($settings as $key => $value) {
                $stmt->execute([
                    'key' => $key,
                    'value' => $value,
                    'updatedBy' => $updatedBy,
                ]);
            }
        } catch (\PDOException $e) {
            error_log("Failed to set panel settings: " . $e->getMessage());
        }
    }

    public function delete(string $key): void
    {
        $pdo = IredadminPgsqlConnection::getInstance()->getPdo();
        if ($pdo === null) {
            return;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM panel_settings WHERE setting_key = :key");
            $stmt->execute(['key' => $key]);
        } catch (\PDOException $e) {
            error_log("Failed to delete panel setting '{$key}': " . $e->getMessage());
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
                "CREATE TABLE IF NOT EXISTS panel_settings (
                    setting_key VARCHAR(100) PRIMARY KEY,
                    setting_value TEXT NOT NULL DEFAULT '',
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_by VARCHAR(255) NOT NULL DEFAULT ''
                )"
            );
        } catch (\PDOException $e) {
            error_log("Failed to create panel_settings table: " . $e->getMessage());
        }
    }
}
