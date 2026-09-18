<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Models\Settings;

/**
 * PDO connection singleton for the iRedAPD database.
 */
class IredapdConnection
{
    private static ?self $instance = null;
    private ?\PDO $pdo = null;

    private function __construct()
    {
        $settings = Settings::getInstance();

        if (!$settings->iredapdEnabled || empty($settings->iredapdDbHost)) {
            return;
        }

        $dsn = "mysql:host={$settings->iredapdDbHost};port={$settings->iredapdDbPort};dbname={$settings->iredapdDbName};charset=utf8mb4";

        try {
            $this->pdo = new \PDO($dsn, $settings->iredapdDbUser, $settings->iredapdDbPassword, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                // rowCount() counts matched rows, so an UPDATE that changes nothing is not "not found"
                \PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ]);
        } catch (\PDOException $e) {
            error_log("iRedAPD DB connection failed: " . $e->getMessage());
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

    public function isAvailable(): bool
    {
        return $this->pdo !== null;
    }
}
