<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Repositories\Mysql\IredadminConnection;
use App\Repositories\Mysql\MysqlDeletedMailboxRepository;

/**
 * With the LDAP backend, iRedMail keeps deleted_mailboxes in the iredadmin
 * database (MariaDB), which has the same table layout as the SQL backends.
 */
class LdapDeletedMailboxRepository extends MysqlDeletedMailboxRepository
{
    protected function pdo(): \PDO
    {
        return IredadminConnection::getInstance()->requirePdo();
    }
}
