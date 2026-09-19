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

    /** LDAP result code "No such object". */
    private const NO_SUCH_OBJECT = 32;

    public function getAliasesPaginated(int $page, int $perPage, ?string $domain = null): PaginatedResult
    {
        $baseDn = $domain !== null
            ? self::aliasesOu($domain)
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
        // Result code 20 ("Type or value exists"): the address is already a member.
        self::changeValue(self::aliasDn($address), 'mailForwardingAddress', $member, true, 20);

        return true;
    }

    public function removeAliasMember(string $address, string $member): bool
    {
        // Result code 16 ("No such attribute"): the address is no member.
        self::changeValue(self::aliasDn($address), 'mailForwardingAddress', $member, false, 16);

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
        self::changeValue(LdapUtils::getEmailDn($email), 'shadowAddress', $aliasAddress, true, null);

        return true;
    }

    public function removeUserAlias(string $email, string $aliasAddress): bool
    {
        self::changeValue(LdapUtils::getEmailDn($email), 'shadowAddress', $aliasAddress, false, null);

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
        return count(self::searchEntries(self::aliasesOu($domain), '(objectClass=mailAlias)', ['mail']))
            + count(self::searchEntries('ou=Groups,' . LdapUtils::getDomainDn($domain), '(objectClass=mailList)', ['mail']));
    }

    private static function aliasesOu(string $domain): string
    {
        return 'ou=Aliases,' . LdapUtils::getDomainDn($domain);
    }

    private static function aliasDn(string $address): string
    {
        $domain = explode('@', $address, 2)[1] ?? '';

        return 'mail=' . ldap_escape($address, '', LDAP_ESCAPE_DN) . ',' . self::aliasesOu($domain);
    }

    /**
     * @param string[] $attrs
     * @return array<int, array<string, mixed>> the entries; none when the base DN does not exist
     */
    private static function searchEntries(string $baseDn, string $filter, array $attrs): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $result = @ldap_search($conn, $baseDn, $filter, $attrs);
        if ($result === false) {
            if (ldap_errno($conn) === self::NO_SUCH_OBJECT) {
                return [];
            }
            throw new \RuntimeException('LDAP alias search failed: ' . ldap_error($conn));
        }

        $entries = ldap_get_entries($conn, $result);
        unset($entries['count']);

        return array_values($entries);
    }

    /**
     * @param string[] $attrs
     * @return array<string, mixed>|null null when the alias does not exist
     */
    private static function readAlias(string $address, array $attrs): ?array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $result = @ldap_read($conn, self::aliasDn($address), '(objectClass=mailAlias)', $attrs);
        if ($result === false) {
            if (ldap_errno($conn) === self::NO_SUCH_OBJECT) {
                return null;
            }
            throw new \RuntimeException("LDAP alias read failed for '{$address}': " . ldap_error($conn));
        }

        return ldap_get_entries($conn, $result)[0] ?? null;
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
     * An empty value list deletes the attribute.
     *
     * @param array<string, string[]> $values
     */
    private static function replaceAttributes(string $address, array $values): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        if (!@ldap_mod_replace($conn, self::aliasDn($address), $values)) {
            throw new \RuntimeException("LDAP alias update failed for '{$address}': " . ldap_error($conn));
        }
    }

    /**
     * Adds or removes one attribute value. A failure with $ignoredCode counts as done.
     */
    private static function changeValue(string $dn, string $attr, string $value, bool $add, ?int $ignoredCode): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $done = $add
            ? @ldap_mod_add($conn, $dn, [$attr => [$value]])
            : @ldap_mod_del($conn, $dn, [$attr => [$value]]);
        if (!$done && ldap_errno($conn) !== $ignoredCode) {
            throw new \RuntimeException("LDAP update of {$attr} failed for '{$dn}': " . ldap_error($conn));
        }
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
