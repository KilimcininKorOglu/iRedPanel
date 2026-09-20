<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Models\Settings;

class MysqlConnectionException extends \App\Exceptions\BackendConnectionException {}

/**
 * MySQL/PDO connection singleton for iRedMail vmail database.
 */
class MysqlConnection
{
    private static ?self $instance = null;
    private \PDO $pdo;

    private function __construct()
    {
        $settings = Settings::getInstance();
        $dsn = "mysql:host={$settings->mysqlHost};port={$settings->mysqlPort};dbname={$settings->mysqlDatabase};charset=utf8mb4";

        try {
            $this->pdo = new \PDO($dsn, $settings->mysqlUser, $settings->mysqlPassword, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                // rowCount() counts matched rows, so an UPDATE that changes nothing is not "not found"
                \PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ]);
        } catch (\PDOException $e) {
            throw new MysqlConnectionException("Failed to connect to MySQL: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public static function getInstance(): self
    {
        self::$instance ??= new self();
        return self::$instance;
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
