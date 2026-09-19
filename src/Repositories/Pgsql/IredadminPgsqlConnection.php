<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Exceptions\BackendConnectionException;
use App\Models\Settings;

/**
 * PostgreSQL PDO connection singleton for the iredadmin database (activity logging).
 */
class IredadminPgsqlConnection
{
    private static ?self $instance = null;
    private ?\PDO $pdo = null;

    private function __construct()
    {
        $settings = Settings::getInstance();

        if (empty($settings->iredadminDbHost)) {
            return;
        }

        $port = $settings->iredadminDbPort ?: 5432;
        $dsn = "pgsql:host={$settings->iredadminDbHost};port={$port};dbname={$settings->iredadminDbName}";

        try {
            $this->pdo = new \PDO($dsn, $settings->iredadminDbUser, $settings->iredadminDbPassword, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            error_log("IredAdmin PgSQL connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): ?\PDO
    {
        return $this->pdo;
    }

    /**
     * @throws BackendConnectionException when the iredadmin database is not configured or not reachable
     */
    public function requirePdo(): \PDO
    {
        if ($this->pdo === null) {
            throw new BackendConnectionException('iredadmin database not available');
        }
        return $this->pdo;
    }

    public function isAvailable(): bool
    {
        return $this->pdo !== null;
    }
}
