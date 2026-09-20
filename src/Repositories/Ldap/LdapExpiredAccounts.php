<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Repositories\ExpiredAccountRepositoryInterface;
use App\Utils\ExpiryDate;
use App\Utils\LdapUtils;

/**
 * The expired accounts of the LDAP tree. The search reads every entry that carries
 * an expiry date, and the date itself is compared in PHP.
 */
final class LdapExpiredAccounts implements ExpiredAccountRepositoryInterface
{
    public function expiredMailboxes(?int $now = null): array
    {
        return $this->addresses(LdapUtils::domainsBase(), 'mailUser', 'mail', $now);
    }

    public function expiredDomains(?int $now = null): array
    {
        return $this->addresses(LdapUtils::domainsBase(), 'mailDomain', 'domainName', $now);
    }

    public function expiredAdmins(?int $now = null): array
    {
        return $this->addresses(LdapUtils::adminsBase(), 'mailAdmin', 'mail', $now);
    }

    /**
     * @return list<string>
     */
    private function addresses(string $baseDn, string $objectClass, string $nameAttr, ?int $now): array
    {
        // The iRedMail schema gives expiredDate an EQUALITY rule and no ORDERING rule, so
        // OpenLDAP answers a "<=" filter with no entry at all. The date is compared in PHP.
        $filter = "(&(objectClass={$objectClass})(accountStatus=active)(expiredDate=*))";
        $entries = LdapUtils::searchEntries(LdapConnection::getInstance()->getConn(), $baseDn, $filter, [$nameAttr, 'expiredDate']);

        $names = [];
        foreach ($entries as $entry) {
            $expired = ExpiryDate::fromLdap(LdapUtils::allValues($entry, 'expiredDate')[0] ?? '');
            $name = LdapUtils::allValues($entry, $nameAttr)[0] ?? '';
            if ($name !== '' && ExpiryDate::isExpired($expired, $now)) {
                $names[] = strtolower($name);
            }
        }
        sort($names);

        return $names;
    }

}
