<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\DomainAlias;
use App\Models\LdapConnection;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\DomainAliasRepositoryInterface;
use App\Utils\LdapUtils;

class LdapDomainAliasRepository implements DomainAliasRepositoryInterface
{
    /** Mailboxes, aliases and mailing lists of a domain; the catch-all entry `mail=@<domain>` has no local part. */
    private const ACCOUNT_FILTER = '(&(|(objectClass=mailUser)(objectClass=mailAlias)(objectClass=mailList))(!(mail=@*)))';

    public function getAliasesForDomain(string $domain): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getDomainDn($domain);

        $result = @ldap_read($conn, $dn, '(objectClass=mailDomain)', ['domainAliasName']);
        if ($result === false) {
            return [];
        }

        $entries = ldap_get_entries($conn, $result);
        if (($entries['count'] ?? 0) === 0) {
            return [];
        }

        $aliases = [];
        $aliasNames = $entries[0]['domainaliasname'] ?? [];
        $count = (int) ($aliasNames['count'] ?? 0);
        for ($i = 0; $i < $count; $i++) {
            $aliases[] = new DomainAlias(
                aliasDomain: $aliasNames[$i],
                targetDomain: $domain,
                active: true,
            );
        }

        return $aliases;
    }

    public function getAllAliasesPaginated(int $page, int $perPage): PaginatedResult
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();

        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            '(&(objectClass=mailDomain)(domainAliasName=*))',
            ['domainName', 'domainAliasName']
        );

        $allAliases = [];
        if ($result !== false) {
            $entries = ldap_get_entries($conn, $result);
            for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
                $targetDomain = $entries[$i]['domainname'][0] ?? '';
                $aliasNames = $entries[$i]['domainaliasname'] ?? [];
                $count = (int) ($aliasNames['count'] ?? 0);
                for ($j = 0; $j < $count; $j++) {
                    $allAliases[] = new DomainAlias(
                        aliasDomain: $aliasNames[$j],
                        targetDomain: $targetDomain,
                        active: true,
                    );
                }
            }
        }

        usort($allAliases, fn(DomainAlias $a, DomainAlias $b) => strcmp($a->aliasDomain, $b->aliasDomain));

        $totalCount = count($allAliases);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($allAliases, $offset, $perPage);

        return new PaginatedResult($items, $totalCount, $page, $perPage);
    }

    public function getAlias(string $aliasDomain): ?DomainAlias
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();
        $safeAlias = ldap_escape($aliasDomain, '', LDAP_ESCAPE_FILTER);

        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            "(&(objectClass=mailDomain)(domainAliasName={$safeAlias}))",
            ['domainName']
        );

        if ($result === false || ldap_count_entries($conn, $result) === 0) {
            return null;
        }

        $entries = ldap_get_entries($conn, $result);
        $targetDomain = $entries[0]['domainname'][0] ?? '';

        return new DomainAlias(
            aliasDomain: $aliasDomain,
            targetDomain: $targetDomain,
            active: true,
        );
    }

    /**
     * Postfix accepts an alias domain only when the target domain has
     * `enabledService: domainalias`, and resolves `user@<alias domain>` through the
     * shadowAddress of each account, so every account of the domain gets one.
     */
    public function createAlias(DomainAlias $alias): void
    {
        if (!$alias->active) {
            throw new \DomainException('The LDAP backend has no inactive alias domains');
        }
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getDomainDn($alias->targetDomain);

        if (!@ldap_mod_add($conn, $dn, ['domainAliasName' => [$alias->aliasDomain]])) {
            throw new \RuntimeException('LDAP domain alias creation failed: ' . ldap_error($conn));
        }
        LdapUtils::addValues($conn, $dn, 'enabledService', ['domainalias']);

        foreach (LdapUtils::searchEntries($conn, $dn, self::ACCOUNT_FILTER, ['mail']) as $entry) {
            $local = explode('@', LdapUtils::allValues($entry, 'mail')[0] ?? '', 2)[0];
            LdapUtils::addValues($conn, $entry['dn'], 'shadowAddress', ["{$local}@{$alias->aliasDomain}"]);
        }
    }

    public function deleteAlias(string $aliasDomain): void
    {
        $alias = $this->getAlias($aliasDomain);
        if ($alias === null) {
            throw new \RuntimeException("Domain alias '{$aliasDomain}' not found");
        }

        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getDomainDn($alias->targetDomain);
        $suffix = '@' . strtolower($aliasDomain);
        $filter = '(shadowAddress=*' . ldap_escape($suffix, '', LDAP_ESCAPE_FILTER) . ')';

        foreach (LdapUtils::searchEntries($conn, $dn, $filter, ['shadowAddress']) as $entry) {
            $values = array_filter(
                LdapUtils::allValues($entry, 'shadowAddress'),
                static fn (string $address): bool => str_ends_with(strtolower($address), $suffix)
            );
            LdapUtils::deleteValues($conn, $entry['dn'], 'shadowAddress', array_values($values));
        }

        LdapUtils::deleteValues($conn, $dn, 'domainAliasName', [$aliasDomain]);
        if ($this->getAliasesForDomain($alias->targetDomain) === []) {
            LdapUtils::deleteValues($conn, $dn, 'enabledService', ['domainalias']);
        }
    }

    /**
     * An alias domain in `domainAliasName` is always active; it has no status of its own.
     */
    public function enableDisableAlias(string $aliasDomain, bool $active): void
    {
        throw new \LogicException('The LDAP backend has no status for an alias domain');
    }

    public function supportsStatus(): bool
    {
        return false;
    }
}
