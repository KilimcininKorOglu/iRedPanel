<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Models\Settings;

/**
 * PDO connection singleton for the Amavisd database.
 */
class AmavisdConnection
{
    private static ?self $instance = null;
    private ?\PDO $pdo = null;

    private function __construct()
    {
        $settings = Settings::getInstance();

        if (!$settings->amavisdEnabled || empty($settings->amavisdDbHost)) {
            return;
        }

        $dsn = "mysql:host={$settings->amavisdDbHost};port={$settings->amavisdDbPort};dbname={$settings->amavisdDbName};charset=utf8mb4";

        try {
            $this->pdo = new \PDO($dsn, $settings->amavisdDbUser, $settings->amavisdDbPassword, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                // rowCount() counts matched rows, so an UPDATE that changes nothing is not "not found"
                \PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ]);
        } catch (\PDOException $e) {
            error_log("Amavisd DB connection failed: " . $e->getMessage());
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
