<?php

declare(strict_types=1);

namespace App\Utils;

use App\Models\Settings;

class LdapUtils
{
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
}
