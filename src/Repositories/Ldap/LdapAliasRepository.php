<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\Alias;
use App\Models\LdapConnection;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\AliasRepositoryInterface;
use App\Utils\LdapUtils;

class LdapAliasRepository implements AliasRepositoryInterface
{
    public function getAliasesPaginated(int $page, int $perPage, ?string $domain = null): PaginatedResult
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();

        if ($domain !== null) {
            $baseDn = "ou=Groups,domainName=" . ldap_escape($domain, '', LDAP_ESCAPE_DN) . ",o=domains,{$settings->ldapRootDn}";
        } else {
            $baseDn = "o=domains,{$settings->ldapRootDn}";
        }

        $filter = '(&(objectClass=mailList)(mail=*))';
        $attrs = ['mail', 'cn', 'accountStatus', 'accessPolicy'];

        $result = @ldap_search($conn, $baseDn, $filter, $attrs);
        if ($result === false) {
            return new PaginatedResult([], 0, $page, $perPage);
        }

        $entries = ldap_get_entries($conn, $result);
        $totalCount = $entries['count'] ?? 0;

        $items = [];
        for ($i = 0; $i < $totalCount; $i++) {
            $entry = $entries[$i];
            $email = $entry['mail'][0] ?? '';
            $aliasDomain = str_contains($email, '@') ? explode('@', $email, 2)[1] : '';

            $items[] = new Alias(
                address: $email,
                domain: $aliasDomain,
                name: $entry['cn'][0] ?? '',
                accessPolicy: $entry['accesspolicy'][0] ?? 'public',
                islist: true,
                active: ($entry['accountstatus'][0] ?? 'active') === 'active',
            );
        }

        usort($items, fn(Alias $a, Alias $b) => strcmp($a->address, $b->address));

        $offset = ($page - 1) * $perPage;
        $pageItems = array_slice($items, $offset, $perPage);

        return new PaginatedResult($pageItems, $totalCount, $page, $perPage);
    }

    public function getAlias(string $address): ?Alias
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getAliasGroupDn($address);

        $attrs = ['mail', 'cn', 'accountStatus', 'accessPolicy'];
        $result = @ldap_read($conn, $dn, '(objectClass=mailList)', $attrs);
        if ($result === false) {
            return null;
        }

        $entries = ldap_get_entries($conn, $result);
        if (($entries['count'] ?? 0) === 0) {
            return null;
        }

        $entry = $entries[0];
        $email = $entry['mail'][0] ?? $address;
        $domain = str_contains($email, '@') ? explode('@', $email, 2)[1] : '';

        return new Alias(
            address: $email,
            domain: $domain,
            name: $entry['cn'][0] ?? '',
            accessPolicy: $entry['accesspolicy'][0] ?? 'public',
            islist: true,
            active: ($entry['accountstatus'][0] ?? 'active') === 'active',
        );
    }

    public function createAlias(string $address, string $domain, string $name, array $members, string $accessPolicy): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getAliasGroupDn($address);

        $entry = [
            'objectClass' => ['mailList'],
            'mail' => $address,
            'accountStatus' => 'active',
            'accessPolicy' => $accessPolicy,
        ];

        if ($name !== '') {
            $entry['cn'] = $name;
        }

        if (!empty($members)) {
            $entry['hasMember'] = $members;
        }

        return @ldap_add($conn, $dn, $entry);
    }

    public function updateAlias(string $address, string $name, array $members, string $accessPolicy, bool $active): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getAliasGroupDn($address);

        $modifications = [
            LdapUtils::modReplace('accessPolicy', $accessPolicy),
            LdapUtils::modReplace('accountStatus', $active ? 'active' : 'disabled'),
            LdapUtils::modReplace('cn', $name !== '' ? $name : null),
        ];

        if (!empty($members)) {
            $modifications[] = [
                'attrib' => 'hasMember',
                'modtype' => LDAP_MODIFY_BATCH_REPLACE,
                'values' => $members,
            ];
        } else {
            $modifications[] = [
                'attrib' => 'hasMember',
                'modtype' => LDAP_MODIFY_BATCH_REMOVE_ALL,
            ];
        }

        return LdapUtils::modifyBatch($conn, $dn, $modifications);
    }

    public function deleteAlias(string $address): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getAliasGroupDn($address);

        return @ldap_delete($conn, $dn);
    }

    public function getAliasMembers(string $address): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getAliasGroupDn($address);

        $result = @ldap_read($conn, $dn, '(objectClass=mailList)', ['hasMember']);
        if ($result === false) {
            return [];
        }

        $entries = ldap_get_entries($conn, $result);
        if (($entries['count'] ?? 0) === 0) {
            return [];
        }

        $members = [];
        $count = $entries[0]['hasmember']['count'] ?? 0;
        for ($i = 0; $i < $count; $i++) {
            $members[] = $entries[0]['hasmember'][$i];
        }

        sort($members);
        return $members;
    }

    public function addAliasMember(string $address, string $member): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getAliasGroupDn($address);

        return @ldap_mod_add($conn, $dn, ['hasMember' => [$member]]);
    }

    public function removeAliasMember(string $address, string $member): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getAliasGroupDn($address);

        return @ldap_mod_del($conn, $dn, ['hasMember' => [$member]]);
    }

    public function getModerators(string $address): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getAliasGroupDn($address);

        $result = @ldap_read($conn, $dn, '(objectClass=mailList)', ['listAllowedUser']);
        if ($result === false) {
            return [];
        }

        $entries = ldap_get_entries($conn, $result);
        if (($entries['count'] ?? 0) === 0) {
            return [];
        }

        $moderators = [];
        $count = $entries[0]['listalloweduser']['count'] ?? 0;
        for ($i = 0; $i < $count; $i++) {
            $moderators[] = $entries[0]['listalloweduser'][$i];
        }

        sort($moderators);
        return $moderators;
    }

    public function setModerators(string $address, array $moderators): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getAliasGroupDn($address);

        $moderators = array_filter(array_map('trim', $moderators));

        if (!empty($moderators)) {
            $modification = [
                'attrib' => 'listAllowedUser',
                'modtype' => LDAP_MODIFY_BATCH_REPLACE,
                'values' => array_values($moderators),
            ];
        } else {
            $modification = [
                'attrib' => 'listAllowedUser',
                'modtype' => LDAP_MODIFY_BATCH_REMOVE_ALL,
            ];
        }

        return LdapUtils::modifyBatch($conn, $dn, [$modification]);
    }

    public function getUserAliases(string $email): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $userDn = LdapUtils::getEmailDn($email);

        $result = @ldap_read($conn, $userDn, '(objectClass=*)', ['shadowAddress']);
        if ($result === false) {
            return [];
        }

        $entries = ldap_get_entries($conn, $result);
        if (($entries['count'] ?? 0) === 0) {
            return [];
        }

        $aliases = [];
        $count = $entries[0]['shadowaddress']['count'] ?? 0;
        for ($i = 0; $i < $count; $i++) {
            $aliases[] = $entries[0]['shadowaddress'][$i];
        }

        sort($aliases);
        return $aliases;
    }

    public function addUserAlias(string $email, string $aliasAddress): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $userDn = LdapUtils::getEmailDn($email);

        return @ldap_mod_add($conn, $userDn, ['shadowAddress' => [$aliasAddress]]);
    }

    public function removeUserAlias(string $email, string $aliasAddress): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $userDn = LdapUtils::getEmailDn($email);

        return @ldap_mod_del($conn, $userDn, ['shadowAddress' => [$aliasAddress]]);
    }

    public function isAddressInUse(string $address): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $escaped = ldap_escape($address, '', LDAP_ESCAPE_FILTER);

        $result = @ldap_search(
            $conn,
            Settings::getInstance()->ldapRootDn,
            "(|(mail={$escaped})(shadowAddress={$escaped}))",
            ['mail'],
            0,
            1
        );
        if ($result === false) {
            throw new \RuntimeException('LDAP search failed: ' . ldap_error($conn));
        }

        return (ldap_count_entries($conn, $result) ?: 0) > 0;
    }

    public function getCatchall(string $domain): ?string
    {
        $conn = LdapConnection::getInstance()->getConn();
        $domainDn = LdapUtils::getDomainDn($domain);

        $result = @ldap_read($conn, $domainDn, '(objectClass=*)', ['catchallAddress']);
        if ($result === false) {
            return null;
        }

        $entries = ldap_get_entries($conn, $result);
        if (($entries['count'] ?? 0) === 0) {
            return null;
        }

        return $entries[0]['catchalladdress'][0] ?? null;
    }

    public function setCatchall(string $domain, ?string $targetEmail): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $domainDn = LdapUtils::getDomainDn($domain);

        $modification = LdapUtils::modReplace('catchallAddress', $targetEmail);
        return LdapUtils::modifyBatch($conn, $domainDn, [$modification]);
    }

    public function enableDisableAlias(string $address, bool $active): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getAliasGroupDn($address);

        $modification = LdapUtils::modReplace('accountStatus', $active ? 'active' : 'disabled');
        return LdapUtils::modifyBatch($conn, $dn, [$modification]);
    }

    private function getAliasGroupDn(string $address): string
    {
        $settings = Settings::getInstance();
        $domain = explode('@', $address, 2)[1] ?? '';
        $safeDomain = ldap_escape($domain, '', LDAP_ESCAPE_DN);
        $safeAddress = ldap_escape($address, '', LDAP_ESCAPE_DN);

        return "mail={$safeAddress},ou=Groups,domainName={$safeDomain},o=domains,{$settings->ldapRootDn}";
    }

    public function countAliasesForDomain(string $domain): int
    {
        $conn = LdapConnection::getInstance();
        $settings = \App\Models\Settings::getInstance();
        $safeDomain = ldap_escape($domain, '', LDAP_ESCAPE_FILTER);
        $baseDn = "ou=Groups,domainName={$safeDomain},o=domains,{$settings->ldapRootDn}";

        $result = @ldap_search($conn->getConnection(), $baseDn, '(objectClass=mailList)', ['dn']);
        if ($result === false) {
            return 0;
        }
        return ldap_count_entries($conn->getConnection(), $result);
    }
}
