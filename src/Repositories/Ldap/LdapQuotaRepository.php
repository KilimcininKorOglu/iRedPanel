<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Repositories\Mysql\IredadminConnection;
use App\Repositories\Mysql\MysqlQuotaRepository;

/**
 * With the LDAP backend, Dovecot writes used_quota into the iredadmin database
 * (MariaDB), which has the same table layout as the SQL backends.
 */
class LdapQuotaRepository extends MysqlQuotaRepository
{
    /**
     * Without an iredadmin database the used quota is unknown, so the user list shows none.
     */
    public function getDomainUsedQuotas(string $domain): array
    {
        if (IredadminConnection::getInstance()->getPdo() === null) {
            return [];
        }

        return parent::getDomainUsedQuotas($domain);
    }

    protected function pdo(): \PDO
    {
        return IredadminConnection::getInstance()->requirePdo();
    }
}
