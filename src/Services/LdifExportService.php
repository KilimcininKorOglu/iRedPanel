<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LdapConnection;
use App\Models\Settings;
use App\Utils\LdapUtils;
use App\Utils\Ldif;

/**
 * Exports an LDAP subtree as an LDIF file, for a backup or a migration. The
 * file holds every attribute of every entry, including the password hashes.
 */
final class LdifExportService
{
    /** Entries per page of the paged search. */
    private const PAGE_SIZE = 500;

    /** Whether the backend holds its accounts in LDAP. */
    public static function available(): bool
    {
        return Settings::getInstance()->backend === 'ldap';
    }

    /**
     * @throws \RuntimeException when the search fails
     */
    public static function domain(string $domain): string
    {
        return self::subtree(LdapUtils::getDomainDn($domain));
    }

    /**
     * @throws \RuntimeException when the search fails
     */
    public static function tree(): string
    {
        return self::subtree(Settings::getInstance()->ldapRootDn);
    }

    /**
     * Reads the subtree of $baseDn and returns it as LDIF text.
     *
     * @throws \RuntimeException when the search fails
     */
    public static function subtree(string $baseDn): string
    {
        $conn = LdapConnection::getInstance()->getConn();
        $ldif = "version: 1\n";
        $cookie = '';

        do {
            $result = @ldap_search($conn, $baseDn, '(objectClass=*)', ['*'], 0, 0, 0, LDAP_DEREF_NEVER, [[
                'oid' => LDAP_CONTROL_PAGEDRESULTS,
                'value' => ['size' => self::PAGE_SIZE, 'cookie' => $cookie],
            ]]);
            if ($result === false) {
                throw new \RuntimeException("LDAP search failed for {$baseDn}: " . ldap_error($conn));
            }

            $ldif .= self::entries($conn, $result);
            $cookie = self::nextCookie($conn, $result);
        } while ($cookie !== '' && $cookie !== null);

        return $ldif;
    }

    /**
     * Sends the LDIF file as a download.
     */
    public static function download(string $filename, string $ldif): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($ldif));
        echo $ldif;
    }

    /**
     * @param \LDAP\Result $result
     */
    private static function entries(\LDAP\Connection $conn, $result): string
    {
        $ldif = '';
        for ($entry = ldap_first_entry($conn, $result); $entry !== false; $entry = ldap_next_entry($conn, $entry)) {
            $dn = ldap_get_dn($conn, $entry);
            if ($dn === false) {
                continue;
            }
            $ldif .= "\n" . Ldif::entry($dn, self::attributes($conn, $entry));
        }

        return rtrim($ldif, "\n") . "\n";
    }

    /**
     * Every attribute value of one entry, in the order the directory returns them.
     *
     * @param \LDAP\ResultEntry $entry
     * @return array<string, list<string>>
     */
    private static function attributes(\LDAP\Connection $conn, $entry): array
    {
        $attributes = [];
        $names = ldap_get_attributes($conn, $entry);
        for ($i = 0; $i < ($names['count'] ?? 0); $i++) {
            $name = $names[$i];
            $values = ldap_get_values_len($conn, $entry, $name);
            if ($values === false) {
                continue;
            }
            for ($v = 0; $v < $values['count']; $v++) {
                $attributes[$name][] = $values[$v];
            }
        }

        return $attributes;
    }

    /**
     * The cookie of the next page, or an empty string after the last one.
     *
     * @param \LDAP\Result $result
     */
    private static function nextCookie(\LDAP\Connection $conn, $result): ?string
    {
        $controls = [];
        ldap_parse_result($conn, $result, $errcode, $matcheddn, $errmsg, $referrals, $controls);

        return $controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
    }
}
