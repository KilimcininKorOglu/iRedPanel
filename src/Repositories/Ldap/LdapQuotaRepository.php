<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Repositories\QuotaRepositoryInterface;

/**
 * LDAP backend does not have access to the Dovecot used_quota table.
 * Returns empty data. A future enhancement could add a separate DB connection
 * for Dovecot quota via IREDPANEL_DOVECOT_QUOTA_DB_* env vars.
 */
class LdapQuotaRepository implements QuotaRepositoryInterface
{
    public function getDomainUsedQuotas(string $domain): array
    {
        return [];
    }
}
