<?php

declare(strict_types=1);

namespace App\Utils;

use App\Models\Settings;

class LdapUtils
{
    /** LDAP result code "No such attribute". */
    private const NO_SUCH_ATTRIBUTE = 16;

    /** LDAP result code "Type or value exists". */
    private const TYPE_OR_VALUE_EXISTS = 20;

    /** LDAP result code "No such object". */
    private const NO_SUCH_OBJECT = 32;

    /** Attributes that store a mail address and must follow a rename. */
    private const ADDRESS_ATTRS = [
        'mailForwardingAddress', 'listModerator', 'listOwner', 'listAllowedUser',
        'userSenderBccAddress', 'userRecipientBccAddress', 'domainSenderBccAddress', 'domainRecipientBccAddress',
        'domainAdmin',
    ];

    /**
     * Builds LDAP DN for an email-based user.
     * Example: mail=user@example.com,ou=Users,domainName=example.com,o=domains,dc=example,dc=com
     */
    public static function getEmailDn(string $email): string
    {
        if (!str_contains($email, '@')) {
            throw new \InvalidArgumentException("Invalid email format: missing @ in '{$email}'");
        }

        $settings = Settings::getInstance();
        $parts = explode('@', $email);
        $domain = $parts[1];

        $safeDomain = ldap_escape($domain, '', LDAP_ESCAPE_DN);
        $safeEmail = ldap_escape($email, '', LDAP_ESCAPE_DN);

        return "mail={$safeEmail},ou=Users,domainName={$safeDomain},o=domains,{$settings->ldapRootDn}";
    }

    /**
     * Builds LDAP DN for a domain.
     * Example: domainName=example.com,o=domains,dc=example,dc=com
     */
    public static function getDomainDn(string $domain): string
    {
        $settings = Settings::getInstance();
        $safeDomain = ldap_escape($domain, '', LDAP_ESCAPE_DN);
        return "domainName={$safeDomain},o=domains,{$settings->ldapRootDn}";
    }

    /**
     * Returns the DN of an account entry `mail=<address>,ou=<ou>` under the domain of the address.
     */
    public static function accountDn(string $address, string $ou): string
    {
        $address = strtolower($address);
        $domain = explode('@', $address, 2)[1] ?? '';

        return 'mail=' . ldap_escape($address, '', LDAP_ESCAPE_DN) . ",ou={$ou}," . self::getDomainDn($domain);
    }

    /**
     * Moves the account entry `mail=<old>,ou=<ou>` to `mail=<new>` in the same domain. The
     * addresses in the alias domains and every reference to the old address follow it.
     *
     * @param array<string, list<string>> $attributes further attributes to replace with mail
     * @throws \RuntimeException when the directory refuses the change
     */
    public static function renameAccountEntry(\LDAP\Connection $conn, string $oldAddress, string $newAddress, string $ou, array $attributes = []): void
    {
        $newDn = self::accountDn($newAddress, $ou);
        [$newRdn, $parentDn] = explode(',', $newDn, 2);
        if (!@ldap_rename($conn, self::accountDn($oldAddress, $ou), $newRdn, $parentDn, true)
            || !@ldap_mod_replace($conn, $newDn, ['mail' => [strtolower($newAddress)]] + $attributes)) {
            throw new \RuntimeException("LDAP rename failed for '{$oldAddress}': " . ldap_error($conn));
        }

        self::deleteValues($conn, $newDn, 'shadowAddress', self::aliasDomainAddresses($conn, $oldAddress));
        self::addValues($conn, $newDn, 'shadowAddress', self::aliasDomainAddresses($conn, $newAddress));
        self::replaceAddressReferences($conn, $oldAddress, $newAddress);
    }

    /**
     * Returns a ldap_modify_batch() compatible entry for MOD_REPLACE.
     * If value is null or empty string, returns a REMOVE_ALL entry.
     */
    public static function modReplace(string $attr, mixed $value): array
    {
        if ($value === null || $value === '') {
            return [
                'attrib' => $attr,
                'modtype' => LDAP_MODIFY_BATCH_REMOVE_ALL,
            ];
        }

        return [
            'attrib' => $attr,
            'modtype' => LDAP_MODIFY_BATCH_REPLACE,
            'values' => [(string) $value],
        ];
    }

    /**
     * Runs ldap_modify_batch() without the REMOVE_ALL entries of attributes that the entry
     * does not have, because LDAP rejects such a removal with "No such attribute".
     * Read ldap_error() when it returns false.
     */
    public static function modifyBatch(\LDAP\Connection $conn, string $dn, array $mods): bool
    {
        $isRemoveAll = static fn (array $mod): bool => $mod['modtype'] === LDAP_MODIFY_BATCH_REMOVE_ALL;
        $removals = array_column(array_filter($mods, $isRemoveAll), 'attrib');
        if ($removals !== []) {
            $present = self::presentAttributes($conn, $dn, $removals);
            $mods = array_values(array_filter(
                $mods,
                static fn (array $mod): bool => !$isRemoveAll($mod) || in_array(strtolower($mod['attrib']), $present, true)
            ));
        }

        return $mods === [] || @ldap_modify_batch($conn, $dn, $mods);
    }

    /**
     * @param string[] $attrs
     * @return string[] the lowercased names of $attrs that the entry has; all of them when the
     *                  entry cannot be read, so that the modify reports the real error
     */
    private static function presentAttributes(\LDAP\Connection $conn, string $dn, array $attrs): array
    {
        $lowered = array_map('strtolower', $attrs);
        $result = @ldap_read($conn, $dn, '(objectClass=*)', $attrs);
        if ($result === false) {
            return $lowered;
        }
        $entry = ldap_get_entries($conn, $result)[0] ?? [];

        return array_values(array_filter($lowered, static fn (string $attr): bool => isset($entry[$attr])));
    }

    /**
     * Returns every value of a multi-valued attribute of an ldap_get_entries() entry.
     *
     * @return string[]
     */
    public static function allValues(array $entry, string $attr): array
    {
        $values = $entry[strtolower($attr)] ?? ['count' => 0];
        unset($values['count']);

        return array_values($values);
    }

    /**
     * Searches below $baseDn. A missing base DN gives no entries; any other error throws.
     *
     * @param string[] $attrs
     * @return array<int, array<string, mixed>>
     */
    public static function searchEntries(\LDAP\Connection $conn, string $baseDn, string $filter, array $attrs): array
    {
        $result = @ldap_search($conn, $baseDn, $filter, $attrs);
        if ($result === false) {
            if (ldap_errno($conn) === self::NO_SUCH_OBJECT) {
                return [];
            }
            throw new \RuntimeException("LDAP search below '{$baseDn}' failed: " . ldap_error($conn));
        }

        $entries = ldap_get_entries($conn, $result);
        unset($entries['count']);

        return array_values($entries);
    }

    /**
     * Reads one entry. A missing entry gives null; any other error throws.
     *
     * @param string[] $attrs
     * @return array<string, mixed>|null
     */
    public static function readEntry(\LDAP\Connection $conn, string $dn, string $filter, array $attrs): ?array
    {
        $result = @ldap_read($conn, $dn, $filter, $attrs);
        if ($result === false) {
            if (ldap_errno($conn) === self::NO_SUCH_OBJECT) {
                return null;
            }
            throw new \RuntimeException("LDAP read of '{$dn}' failed: " . ldap_error($conn));
        }

        return ldap_get_entries($conn, $result)[0] ?? null;
    }

    /**
     * Replaces attribute values; an empty value list deletes the attribute.
     *
     * @param array<string, string[]> $values
     */
    public static function replaceValues(\LDAP\Connection $conn, string $dn, array $values): void
    {
        if (!@ldap_mod_replace($conn, $dn, $values)) {
            throw new \RuntimeException("LDAP update of '{$dn}' failed: " . ldap_error($conn));
        }
    }

    /**
     * Adds each value that the attribute does not hold yet (result code 20 counts as done).
     *
     * @param string[] $values
     */
    public static function addValues(\LDAP\Connection $conn, string $dn, string $attr, array $values): void
    {
        foreach ($values as $value) {
            if (!@ldap_mod_add($conn, $dn, [$attr => [$value]]) && ldap_errno($conn) !== self::TYPE_OR_VALUE_EXISTS) {
                throw new \RuntimeException("LDAP update of {$attr} failed for '{$dn}': " . ldap_error($conn));
            }
        }
    }

    /**
     * Deletes each value that the attribute holds (result code 16 counts as done).
     *
     * @param string[] $values
     */
    public static function deleteValues(\LDAP\Connection $conn, string $dn, string $attr, array $values): void
    {
        foreach ($values as $value) {
            if (!@ldap_mod_del($conn, $dn, [$attr => [$value]]) && ldap_errno($conn) !== self::NO_SUCH_ATTRIBUTE) {
                throw new \RuntimeException("LDAP update of {$attr} failed for '{$dn}': " . ldap_error($conn));
            }
        }
    }

    /**
     * Returns `<local part>@<alias domain>` for each alias domain of the address's domain.
     * iRedMail resolves an address in an alias domain through these shadowAddress values.
     *
     * @return string[]
     */
    public static function aliasDomainAddresses(\LDAP\Connection $conn, string $address): array
    {
        [$local, $domain] = explode('@', strtolower($address), 2) + [1 => ''];
        $entry = self::readEntry($conn, self::getDomainDn($domain), '(objectClass=mailDomain)', ['domainAliasName']);

        return array_map(
            static fn (string $aliasDomain): string => "{$local}@{$aliasDomain}",
            self::allValues($entry ?? [], 'domainAliasName')
        );
    }

    /**
     * Normalizes a PHP LDAP entry (from ldap_get_entries) into a clean associative array.
     *
     * PHP ldap_get_entries() returns lowercased attribute names with nested arrays
     * containing 'count' keys. This method converts to simple ['attrName' => 'value']
     * using the $requestedAttrs list to preserve original casing.
     */
    public static function normalizeEntry(array $entry, array $requestedAttrs): array
    {
        $result = [];
        $lowerMap = [];

        foreach ($requestedAttrs as $attr) {
            $lowerMap[strtolower($attr)] = $attr;
        }

        foreach ($lowerMap as $lowerAttr => $originalAttr) {
            if (isset($entry[$lowerAttr][0])) {
                $result[$originalAttr] = $entry[$lowerAttr][0];
            }
        }

        return $result;
    }

    /**
     * Replaces the old address in every entry attribute that stores an address, or removes it
     * when $newEmail is null, as the SQL backends do for their forwardings, moderator, owner,
     * BCC and domain_admins columns. A user or alias rename and a user delete call it.
     */
    public static function replaceAddressReferences(\LDAP\Connection $conn, string $oldEmail, ?string $newEmail): void
    {
        $safeOld = ldap_escape($oldEmail, '', LDAP_ESCAPE_FILTER);
        $filter = '(|' . implode('', array_map(
            static fn (string $attr): string => "({$attr}={$safeOld})",
            self::ADDRESS_ATTRS
        )) . ')';
        $result = @ldap_search($conn, 'o=domains,' . Settings::getInstance()->ldapRootDn, $filter, self::ADDRESS_ATTRS);
        if ($result === false) {
            throw new \RuntimeException('LDAP search failed: ' . ldap_error($conn));
        }

        $entries = ldap_get_entries($conn, $result);
        for ($i = 0; $i < $entries['count']; $i++) {
            $changes = self::replacedValues($entries[$i], $oldEmail, $newEmail);
            if ($changes !== [] && !@ldap_mod_replace($conn, $entries[$i]['dn'], $changes)) {
                throw new \RuntimeException('LDAP update failed: ' . ldap_error($conn));
            }
        }
    }

    /**
     * @return array<string, string[]> the attributes of the entry that hold $oldEmail, with $newEmail
     *         instead or without it. An empty list deletes the attribute.
     */
    private static function replacedValues(array $entry, string $oldEmail, ?string $newEmail): array
    {
        $changes = [];
        foreach (self::ADDRESS_ATTRS as $attr) {
            $values = self::allValues($entry, $attr);
            $kept = array_filter($values, static fn (string $value): bool => strcasecmp($value, $oldEmail) !== 0);
            if (count($kept) !== count($values)) {
                $changes[$attr] = array_values(array_unique($newEmail === null ? $kept : [...$kept, $newEmail]));
            }
        }

        return $changes;
    }
}
