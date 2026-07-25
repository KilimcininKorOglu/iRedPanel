<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Models\Settings;
use App\Repositories\AuthRepositoryInterface;
use App\Utils\LdapUtils;

class LdapAuthRepository implements AuthRepositoryInterface
{
    public function authenticate(string $email, string $password): bool
    {
        LdapConnection::connect($email, $password);
        return true;
    }

    public function isGlobalAdmin(string $email): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();
        $safeEmail = ldap_escape($email, '', LDAP_ESCAPE_FILTER);

        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            "(&(objectClass=mailUser)(mail={$safeEmail})(domainGlobalAdmin=yes))",
            ['mail']
        );

        if ($result === false) {
            return false;
        }

        return ldap_count_entries($conn, $result) > 0;
    }

    public function getManagedDomains(string $email): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();
        $safeEmail = ldap_escape($email, '', LDAP_ESCAPE_FILTER);

        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            "(&(objectClass=mailDomain)(domainAdmin={$safeEmail}))",
            ['domainName']
        );

        $domains = [];
        if ($result !== false) {
            $entries = ldap_get_entries($conn, $result);
            for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
                $normalized = LdapUtils::normalizeEntry($entries[$i], ['domainName']);
                if (!empty($normalized['domainName'])) {
                    $domains[] = $normalized['domainName'];
                }
            }
        }

        sort($domains);
        return $domains;
    }

    public function getLanguage(string $email): string
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn($email);

        $result = @ldap_read($conn, $dn, '(objectClass=*)', ['preferredLanguage']);
        if ($result === false) {
            return '';
        }

        $entries = ldap_get_entries($conn, $result);
        if (($entries['count'] ?? 0) === 0) {
            return '';
        }

        return $entries[0]['preferredlanguage'][0] ?? '';
    }

    public function setLanguage(string $email, string $locale): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn($email);

        @ldap_modify_batch($conn, $dn, [LdapUtils::modReplace('preferredLanguage', $locale)]);
    }

    public function supportsLanguagePersistence(): bool
    {
        return true;
    }
}
