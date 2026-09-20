<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\BackendConnectionException;
use App\Models\Settings;
use App\Repositories\Mysql\IredadminConnection;
use App\Repositories\Pgsql\IredadminPgsqlConnection;

/**
 * The PDO of the iredadmin database of the selected backend. The LDAP backend
 * keeps its panel tables in MySQL, so only PostgreSQL uses the other connection.
 */
final class IredadminPdo
{
    /**
     * @throws BackendConnectionException when the iredadmin database is not reachable
     */
    public static function get(): \PDO
    {
        $pdo = Settings::getInstance()->backend === 'pgsql'
            ? IredadminPgsqlConnection::getInstance()->getPdo()
            : IredadminConnection::getInstance()->getPdo();
        if ($pdo === null) {
            throw new BackendConnectionException('iredadmin database not available');
        }

        return $pdo;
    }
}
