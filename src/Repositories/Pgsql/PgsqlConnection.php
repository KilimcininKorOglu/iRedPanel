<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Models\Settings;

class PgsqlConnectionException extends \App\Exceptions\BackendConnectionException {}

/**
 * PostgreSQL/PDO connection singleton for iRedMail vmail database.
 */
class PgsqlConnection
{
    private static ?self $instance = null;
    private \PDO $pdo;

    private function __construct()
    {
        $settings = Settings::getInstance();
        $dsn = "pgsql:host={$settings->pgsqlHost};port={$settings->pgsqlPort};dbname={$settings->pgsqlDatabase}";

        try {
            $this->pdo = new \PDO($dsn, $settings->pgsqlUser, $settings->pgsqlPassword, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            throw new PgsqlConnectionException("Failed to connect to PostgreSQL: " . $e->getMessage(), $e->getCode(), $e);
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
