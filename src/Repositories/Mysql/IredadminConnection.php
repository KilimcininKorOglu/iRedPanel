<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Exceptions\BackendConnectionException;
use App\Models\Settings;

/**
 * PDO connection singleton for the iredadmin database (activity logging).
 * This is separate from MysqlConnection which connects to the vmail database.
 */
class IredadminConnection
{
    private static ?self $instance = null;
    private ?\PDO $pdo = null;

    private function __construct()
    {
        $settings = Settings::getInstance();

        if (empty($settings->iredadminDbHost)) {
            return; // Logging not configured
        }

        $dsn = "mysql:host={$settings->iredadminDbHost};port={$settings->iredadminDbPort};dbname={$settings->iredadminDbName};charset=utf8mb4";

        try {
            $this->pdo = new \PDO($dsn, $settings->iredadminDbUser, $settings->iredadminDbPassword, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                // rowCount() counts matched rows, so an UPDATE that changes nothing is not "not found"
                \PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ]);
        } catch (\PDOException $e) {
            error_log("IredAdmin DB connection failed: " . $e->getMessage());
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
