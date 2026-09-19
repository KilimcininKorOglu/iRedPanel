<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\Domain;
use App\Models\LdapAccountSetting;
use App\Models\LdapConnection;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\DomainRepositoryInterface;
use App\Utils\LdapUtils;

class LdapDomainRepository implements DomainRepositoryInterface
{
    private const DOMAIN_ATTRS = ['domainName', 'accountStatus', 'domainCurrentUserNumber'];
    private const DOMAIN_DETAIL_ATTRS = ['domainName', 'accountStatus', 'domainCurrentUserNumber', 'cn', 'description', 'mtaTransport', 'disclaimer', 'accountSetting', 'domainBackupMX'];

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
                $allDomains[] = self::toDomain($conn, $entries[$i]);
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

        return self::toDomain($conn, ldap_get_entries($conn, $result)[0]);
    }

    /**
     * Builds a Domain from an ldap_get_entries() entry. iRedMail does not maintain
     * domainCurrentUserNumber, so the mailboxes are counted and their quota summed.
     */
    private static function toDomain(\LDAP\Connection $conn, array $entry): Domain
    {
        $domain = Domain::fromLdapEntry(LdapUtils::normalizeEntry($entry, self::DOMAIN_DETAIL_ATTRS));
        LdapAccountSetting::applyTo($domain, LdapUtils::allValues($entry, 'accountSetting'));
        self::applyMailboxTotals($conn, $domain);

        return $domain;
    }

    /**
     * Sets the mailbox count and the allocated quota in MB, which the SQL backends read
     * with COUNT(*) and SUM(quota) over the mailbox table. The catch-all entry is excluded.
     */
    private static function applyMailboxTotals(\LDAP\Connection $conn, Domain $domain): void
    {
        $safeDomain = ldap_escape($domain->domainName, '', LDAP_ESCAPE_FILTER);
        $mailboxes = LdapUtils::searchEntries(
            $conn,
            'ou=Users,' . LdapUtils::getDomainDn($domain->domainName),
            "(&(objectClass=mailUser)(!(mail=@{$safeDomain})))",
            ['mailQuota']
        );

        $domain->currentUserCount = count($mailboxes);
        $domain->currentQuotaUsed = intdiv(array_sum(array_map(
            static fn (array $mailbox): int => (int) (LdapUtils::allValues($mailbox, 'mailQuota')[0] ?? 0),
            $mailboxes
        )), 1048576);
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
        if ($domain->backupMx) {
            $entry['domainBackupMX'] = 'yes';
        }
        $accountSetting = LdapAccountSetting::valuesFor($domain, []);
        if ($accountSetting !== []) {
            $entry['accountSetting'] = $accountSetting;
        }

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
            LdapUtils::modReplace('mtaTransport', $domain->transport ?: 'dovecot'),
            LdapUtils::modReplace('domainBackupMX', $domain->backupMx ? 'yes' : null),
        ];

        if (!LdapUtils::modifyBatch($conn, $dn, $mods)) {
            throw new \RuntimeException('LDAP domain update failed: ' . ldap_error($conn));
        }

        $stored = @ldap_read($conn, $dn, '(objectClass=mailDomain)', ['accountSetting']);
        if ($stored === false) {
            throw new \RuntimeException('LDAP domain read failed: ' . ldap_error($conn));
        }
        $current = LdapUtils::allValues(ldap_get_entries($conn, $stored)[0], 'accountSetting');
        if (!ldap_mod_replace($conn, $dn, ['accountSetting' => LdapAccountSetting::valuesFor($domain, $current)])) {
            throw new \RuntimeException('LDAP domain limit update failed: ' . ldap_error($conn));
        }

        // A remove-all fails with "No such attribute" when no disclaimer is set; an empty replace does not.
        $disclaimer = $domain->disclaimer !== '' ? [$domain->disclaimer] : [];
        if (!ldap_mod_replace($conn, $dn, ['disclaimer' => $disclaimer])) {
            throw new \RuntimeException('LDAP domain disclaimer update failed: ' . ldap_error($conn));
        }
    }

    /**
     * Deletes the domain subtree. As on the SQL backends, the maildir of every mailbox is
     * recorded for deferred deletion and the iredadmin rows of the mailboxes are removed.
     * When another domain lists this domain as its alias domain, that alias domain and its
     * shadow addresses are removed first.
     */
    public function deleteDomain(string $domainName, string $adminEmail): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getDomainDn($domainName);

        $aliasRepo = new LdapDomainAliasRepository();
        if ($aliasRepo->getAlias($domainName) !== null) {
            $aliasRepo->deleteAlias($domainName);
        }

        $mailboxes = LdapUtils::searchEntries($conn, $dn, '(&(objectClass=mailUser)(!(mail=@*)))', ['mail', 'homeDirectory']);
        self::deleteRecursive($conn, $dn);

        foreach ($mailboxes as $entry) {
            LdapUserRepository::deleteIredadminRows(
                LdapUtils::allValues($entry, 'mail')[0] ?? '',
                $domainName,
                LdapUtils::allValues($entry, 'homeDirectory')[0] ?? '',
                $adminEmail
            );
        }
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
        if ($result === false) {
            throw new \RuntimeException("LDAP list below '{$dn}' failed: " . ldap_error($conn));
        }
        $entries = ldap_get_entries($conn, $result);
        for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
            self::deleteRecursive($conn, $entries[$i]['dn']);
        }

        if (!@ldap_delete($conn, $dn)) {
            throw new \RuntimeException("LDAP delete failed for '{$dn}': " . ldap_error($conn));
        }
    }

    public function supportsDomainQuota(): bool
    {
        return false;
    }
}
