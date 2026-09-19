<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\Domain;
use App\Models\LdapConnection;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\DomainRepositoryInterface;
use App\Utils\LdapUtils;

class LdapDomainRepository implements DomainRepositoryInterface
{
    private const DOMAIN_ATTRS = ['domainName', 'accountStatus', 'domainCurrentUserNumber'];
    private const DOMAIN_DETAIL_ATTRS = ['domainName', 'accountStatus', 'domainCurrentUserNumber', 'cn', 'description', 'mtaTransport', 'disclaimer'];

    public function getDomains(): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();

        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            '(objectClass=mailDomain)',
            self::DOMAIN_ATTRS
        );

        $domainInfo = [];
        if ($result !== false) {
            $entries = ldap_get_entries($conn, $result);
            for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
                $domainInfo[] = LdapUtils::normalizeEntry($entries[$i], self::DOMAIN_ATTRS);
            }
        }

        return $domainInfo;
    }

    public function getDomainsPaginated(int $page, int $perPage, ?bool $activeOnly = null): PaginatedResult
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();

        $filter = '(objectClass=mailDomain)';
        if ($activeOnly === true) {
            $filter = '(&(objectClass=mailDomain)(accountStatus=active))';
        } elseif ($activeOnly === false) {
            $filter = '(&(objectClass=mailDomain)(accountStatus=disabled))';
        }

        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            $filter,
            self::DOMAIN_DETAIL_ATTRS
        );

        $allDomains = [];
        if ($result !== false) {
            $entries = ldap_get_entries($conn, $result);
            for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
                $normalized = LdapUtils::normalizeEntry($entries[$i], self::DOMAIN_DETAIL_ATTRS);
                $allDomains[] = Domain::fromLdapEntry($normalized);
            }
        }

        usort($allDomains, fn(Domain $a, Domain $b) => strcmp($a->domainName, $b->domainName));

        $totalCount = count($allDomains);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($allDomains, $offset, $perPage);

        return new PaginatedResult($items, $totalCount, $page, $perPage);
    }

    public function getDomain(string $domainName): ?Domain
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getDomainDn($domainName);

        $result = @ldap_read($conn, $dn, '(objectClass=mailDomain)', self::DOMAIN_DETAIL_ATTRS);

        if ($result === false || ldap_count_entries($conn, $result) === 0) {
            return null;
        }

        $entries = ldap_get_entries($conn, $result);
        $normalized = LdapUtils::normalizeEntry($entries[0], self::DOMAIN_DETAIL_ATTRS);

        return Domain::fromLdapEntry($normalized);
    }

    public function createDomain(Domain $domain): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getDomainDn($domain->domainName);

        $entry = [
            'objectClass' => ['mailDomain'],
            'domainName' => $domain->domainName,
            'accountStatus' => $domain->active ? 'active' : 'disabled',
            'cn' => $domain->description ?: $domain->domainName,
            'mtaTransport' => $domain->transport ?: 'dovecot',
            'enabledService' => 'mail',
        ];

        if (!@ldap_add($conn, $dn, $entry)) {
            throw new \RuntimeException('LDAP domain creation failed: ' . ldap_error($conn));
        }

        // Create sub-OUs
        $subOus = ['Users', 'Groups', 'Aliases', 'Externals'];
        foreach ($subOus as $ou) {
            $ouDn = "ou={$ou},{$dn}";
            $ouEntry = [
                'objectClass' => ['organizationalUnit', 'top'],
                'ou' => $ou,
            ];
            if (!@ldap_add($conn, $ouDn, $ouEntry)) {
                error_log("Warning: failed to create OU '{$ouDn}': " . ldap_error($conn));
            }
        }
    }

    public function updateDomain(Domain $domain): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getDomainDn($domain->domainName);

        $mods = [
            LdapUtils::modReplace('cn', $domain->description ?: null),
            LdapUtils::modReplace('accountStatus', $domain->active ? 'active' : 'disabled'),
        ];

        if (!ldap_modify_batch($conn, $dn, $mods)) {
            throw new \RuntimeException('LDAP domain update failed: ' . ldap_error($conn));
        }

        // A remove-all fails with "No such attribute" when no disclaimer is set; an empty replace does not.
        $disclaimer = $domain->disclaimer !== '' ? [$domain->disclaimer] : [];
        if (!ldap_mod_replace($conn, $dn, ['disclaimer' => $disclaimer])) {
            throw new \RuntimeException('LDAP domain disclaimer update failed: ' . ldap_error($conn));
        }
    }

    public function deleteDomain(string $domainName, string $adminEmail): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();
        $dn = LdapUtils::getDomainDn($domainName);
        $safeDomain = ldap_escape($domainName, '', LDAP_ESCAPE_FILTER);

        // Remove domainAliasName references from other domains pointing to this domain
        $result = @ldap_search($conn, $settings->ldapRootDn, "(domainAliasName={$safeDomain})", ['dn', 'domainAliasName']);
        if ($result !== false) {
            $entries = ldap_get_entries($conn, $result);
            for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
                @ldap_mod_del($conn, $entries[$i]['dn'], ['domainAliasName' => $domainName]);
            }
        }

        // Recursively delete all entries under the domain DN
        self::deleteRecursive($conn, $dn);
    }

    public function enableDisableDomain(string $domainName, bool $active): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getDomainDn($domainName);

        if (!@ldap_mod_replace($conn, $dn, ['accountStatus' => $active ? 'active' : 'disabled'])) {
            throw new \RuntimeException('LDAP domain status update failed: ' . ldap_error($conn));
        }
    }

    public function getDomainQuotaUsage(string $domainName): int
    {
        // LDAP backend has no access to the Dovecot used_quota table
        return 0;
    }

    /**
     * Recursively deletes an LDAP entry and all its children (leaf-first).
     */
    private static function deleteRecursive(\LDAP\Connection $conn, string $dn): void
    {
        $result = @ldap_list($conn, $dn, '(objectClass=*)', ['dn']);
        if ($result !== false) {
            $entries = ldap_get_entries($conn, $result);
            for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
                self::deleteRecursive($conn, $entries[$i]['dn']);
            }
        }

        if (!@ldap_delete($conn, $dn)) {
            throw new \RuntimeException("LDAP delete failed for '{$dn}': " . ldap_error($conn));
        }
    }
}
