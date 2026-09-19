<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\Alias;
use App\Models\LdapConnection;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\AliasRepositoryInterface;
use App\Utils\LdapUtils;

/**
 * Mail aliases in the iRedMail LDAP layout: a `mailAlias` entry `mail=<address>,ou=Aliases`
 * under the domain, with `enabledService: mail` and `deliver`, the members in
 * `mailForwardingAddress` and the moderators in `listModerator`. Postfix
 * virtual_alias_maps.cf reads these entries.
 */
class LdapAliasRepository implements AliasRepositoryInterface
{
    private const ALIAS_ATTRS = ['mail', 'cn', 'accountStatus', 'accessPolicy'];

    public function getAliasesPaginated(int $page, int $perPage, ?string $domain = null): PaginatedResult
    {
        $baseDn = $domain !== null
            ? 'ou=Aliases,' . LdapUtils::getDomainDn($domain)
            : 'o=domains,' . Settings::getInstance()->ldapRootDn;

        $items = array_map(
            static fn (array $entry): Alias => self::toAlias($entry),
            self::searchEntries($baseDn, '(objectClass=mailAlias)', self::ALIAS_ATTRS)
        );
        usort($items, static fn (Alias $a, Alias $b): int => strcmp($a->address, $b->address));

        return new PaginatedResult(array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $page, $perPage);
    }

    public function getAlias(string $address): ?Alias
    {
        $entry = self::readAlias($address, self::ALIAS_ATTRS);

        return $entry === null ? null : self::toAlias($entry);
    }

    public function createAlias(string $address, string $domain, string $name, array $members, string $accessPolicy): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $entry = [
            'objectClass' => ['mailAlias'],
            'mail' => $address,
            'accountStatus' => 'active',
            'enabledService' => ['mail', 'deliver'],
            'accessPolicy' => $accessPolicy,
        ];
        if ($name !== '') {
            $entry['cn'] = $name;
        }
        $members = self::cleanAddresses($members);
        if ($members !== []) {
            $entry['mailForwardingAddress'] = $members;
        }
        $shadowAddresses = LdapUtils::aliasDomainAddresses($conn, $address);
        if ($shadowAddresses !== []) {
            $entry['shadowAddress'] = $shadowAddresses;
        }

        if (!@ldap_add($conn, self::aliasDn($address), $entry)) {
            throw new \RuntimeException("LDAP alias creation failed for '{$address}': " . ldap_error($conn));
        }

        return true;
    }

    public function updateAlias(string $address, string $name, array $members, string $accessPolicy, bool $active): bool
    {
        self::replaceAttributes($address, [
            'accessPolicy' => [$accessPolicy],
            'accountStatus' => [$active ? 'active' : 'disabled'],
            'cn' => $name !== '' ? [$name] : [],
            'mailForwardingAddress' => self::cleanAddresses($members),
        ]);

        return true;
    }

    public function deleteAlias(string $address): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        if (!@ldap_delete($conn, self::aliasDn($address))) {
            throw new \RuntimeException("LDAP alias deletion failed for '{$address}': " . ldap_error($conn));
        }

        return true;
    }

    public function getAliasMembers(string $address): array
    {
        return self::sortedValues($address, 'mailForwardingAddress');
    }

    public function addAliasMember(string $address, string $member): bool
    {
        LdapUtils::addValues(LdapConnection::getInstance()->getConn(), self::aliasDn($address), 'mailForwardingAddress', [$member]);

        return true;
    }

    public function removeAliasMember(string $address, string $member): bool
    {
        LdapUtils::deleteValues(LdapConnection::getInstance()->getConn(), self::aliasDn($address), 'mailForwardingAddress', [$member]);

        return true;
    }

    public function getModerators(string $address): array
    {
        return self::sortedValues($address, 'listModerator');
    }

    public function setModerators(string $address, array $moderators): bool
    {
        self::replaceAttributes($address, ['listModerator' => self::cleanAddresses($moderators)]);

        return true;
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
        LdapUtils::addValues(LdapConnection::getInstance()->getConn(), LdapUtils::getEmailDn($email), 'shadowAddress', [$aliasAddress]);

        return true;
    }

    public function removeUserAlias(string $email, string $aliasAddress): bool
    {
        LdapUtils::deleteValues(LdapConnection::getInstance()->getConn(), LdapUtils::getEmailDn($email), 'shadowAddress', [$aliasAddress]);

        return true;
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

        $result = @ldap_read($conn, self::catchallDn($domain), '(objectClass=mailUser)', ['mailForwardingAddress']);
        if ($result === false) {
            return null;
        }

        return ldap_get_entries($conn, $result)[0]['mailforwardingaddress'][0] ?? null;
    }

    /**
     * iRedMail's Postfix catchall_maps.cf reads mailForwardingAddress of the active mailUser
     * entry `mail=@<domain>`. The entry has no enabledService=mail, so it is no mailbox.
     *
     * @throws \RuntimeException when the directory rejects the change
     */
    public function setCatchall(string $domain, ?string $targetEmail): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = self::catchallDn($domain);
        $exists = @ldap_read($conn, $dn, '(objectClass=*)', ['mail']) !== false;

        if ($targetEmail === null || $targetEmail === '') {
            $done = !$exists || @ldap_delete($conn, $dn);
        } elseif ($exists) {
            $done = @ldap_mod_replace($conn, $dn, ['mailForwardingAddress' => [$targetEmail]]);
        } else {
            $done = @ldap_add($conn, $dn, [
                'objectClass' => ['inetOrgPerson', 'mailUser'],
                'mail' => "@{$domain}",
                'uid' => "@{$domain}",
                'cn' => 'catch-all',
                'sn' => 'catch-all',
                'accountStatus' => 'active',
                'mailForwardingAddress' => [$targetEmail],
            ]);
        }

        if (!$done) {
            throw new \RuntimeException('LDAP catch-all update failed: ' . ldap_error($conn));
        }

        return true;
    }

    private static function catchallDn(string $domain): string
    {
        return LdapUtils::getEmailDn("@{$domain}");
    }

    public function enableDisableAlias(string $address, bool $active): bool
    {
        self::replaceAttributes($address, ['accountStatus' => [$active ? 'active' : 'disabled']]);

        return true;
    }

    public function countAliasesForDomain(string $domain): int
    {
        return count(self::searchEntries('ou=Aliases,' . LdapUtils::getDomainDn($domain), '(objectClass=mailAlias)', ['mail']))
            + count(self::searchEntries('ou=Groups,' . LdapUtils::getDomainDn($domain), '(objectClass=mailList)', ['mail']));
    }

    private static function aliasDn(string $address): string
    {
        return LdapUtils::accountDn($address, 'Aliases');
    }

    /**
     * @param string[] $attrs
     * @return array<int, array<string, mixed>>
     */
    private static function searchEntries(string $baseDn, string $filter, array $attrs): array
    {
        return LdapUtils::searchEntries(LdapConnection::getInstance()->getConn(), $baseDn, $filter, $attrs);
    }

    /**
     * @param string[] $attrs
     * @return array<string, mixed>|null null when the alias does not exist
     */
    private static function readAlias(string $address, array $attrs): ?array
    {
        return LdapUtils::readEntry(LdapConnection::getInstance()->getConn(), self::aliasDn($address), '(objectClass=mailAlias)', $attrs);
    }

    /**
     * @return string[]
     */
    private static function sortedValues(string $address, string $attr): array
    {
        $values = LdapUtils::allValues(self::readAlias($address, [$attr]) ?? [], $attr);
        sort($values);

        return $values;
    }

    /**
     * @param array<string, string[]> $values
     */
    private static function replaceAttributes(string $address, array $values): void
    {
        LdapUtils::replaceValues(LdapConnection::getInstance()->getConn(), self::aliasDn($address), $values);
    }

    /**
     * @param string[] $addresses
     * @return string[]
     */
    private static function cleanAddresses(array $addresses): array
    {
        return array_values(array_unique(array_filter(array_map('trim', $addresses), static fn (string $a): bool => $a !== '')));
    }

    private static function toAlias(array $entry): Alias
    {
        $address = LdapUtils::allValues($entry, 'mail')[0] ?? '';

        return new Alias(
            address: $address,
            domain: explode('@', $address, 2)[1] ?? '',
            name: LdapUtils::allValues($entry, 'cn')[0] ?? '',
            accessPolicy: LdapUtils::allValues($entry, 'accessPolicy')[0] ?? 'public',
            active: (LdapUtils::allValues($entry, 'accountStatus')[0] ?? 'active') === 'active',
        );
    }
}
