<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Repositories\Mysql\IredadminConnection;
use App\Repositories\Mysql\MysqlLastLoginRepository;

/**
 * With the LDAP backend, Dovecot writes last_login into the iredadmin database
 * (MariaDB), which has the same table layout as the SQL backends.
 */
class LdapLastLoginRepository extends MysqlLastLoginRepository
{
    protected function pdo(): \PDO
    {
        return IredadminConnection::getInstance()->requirePdo();
    }
}
