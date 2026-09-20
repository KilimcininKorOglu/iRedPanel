<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Models\Settings;

/**
 * PostgreSQL PDO connection singleton for the Amavisd database.
 */
class AmavisdPgsqlConnection
{
    private static ?self $instance = null;
    private ?\PDO $pdo = null;

    private function __construct()
    {
        $settings = Settings::getInstance();

        if (!$settings->amavisdEnabled || empty($settings->amavisdDbHost)) {
            return;
        }

        $port = $settings->amavisdDbPort ?: 5432;
        $dsn = "pgsql:host={$settings->amavisdDbHost};port={$port};dbname={$settings->amavisdDbName}";

        try {
            $this->pdo = new \PDO($dsn, $settings->amavisdDbUser, $settings->amavisdDbPassword, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            error_log("Amavisd PgSQL connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        self::$instance ??= new self();
        return self::$instance;
    }

    public function getPdo(): ?\PDO
    {
        return $this->pdo;
    }

    public function isAvailable(): bool
    {
        return $this->pdo !== null;
    }
}
