<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Models\PaginatedResult;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use App\Utils\LdapUtils;

class LdapUserRepository implements UserRepositoryInterface
{
    private const USER_DETAIL_ATTRS = [
        'mail', 'accountStatus', 'domainGlobalAdmin', 'mailQuota', 'uid',
        'cn', 'givenName', 'sn', 'title', 'telephoneNumber', 'mobile', 'employeeNumber',
        'enabledService',
    ];

    private const USER_LIST_ATTRS = [
        'mail', 'accountStatus', 'domainGlobalAdmin', 'mailQuota', 'uid',
    ];

    public function getUser(string $domain, string $userId): ?User
    {
        $conn = LdapConnection::getInstance()->getConn();
        $baseDn = 'ou=Users,' . LdapUtils::getDomainDn($domain);
        $safeUserId = ldap_escape($userId, '', LDAP_ESCAPE_FILTER);

        $result = @ldap_list(
            $conn,
            $baseDn,
            "(&(objectClass=mailUser)(uid={$safeUserId}))",
            self::USER_DETAIL_ATTRS
        );

        if ($result === false || ldap_count_entries($conn, $result) === 0) {
            return null;
        }

        $entries = ldap_get_entries($conn, $result);
        $normalized = LdapUtils::normalizeEntry($entries[0], self::USER_DETAIL_ATTRS);

        // enabledService is multi-valued — extract all values
        if (isset($entries[0]['enabledservice'])) {
            $services = [];
            for ($j = 0; $j < ($entries[0]['enabledservice']['count'] ?? 0); $j++) {
                $services[] = $entries[0]['enabledservice'][$j];
            }
            $normalized['enabledService'] = $services;
        }

        return User::fromLdapEntry($normalized);
    }

    public function getUsers(string $domain): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $baseDn = 'ou=Users,' . LdapUtils::getDomainDn($domain);
        $safeDomain = ldap_escape($domain, '', LDAP_ESCAPE_FILTER);

        $result = @ldap_list(
            $conn,
            $baseDn,
            "(&(objectClass=mailUser)(!(mail=@{$safeDomain})))",
            self::USER_LIST_ATTRS
        );

        $users = [];
        if ($result !== false) {
            $entries = ldap_get_entries($conn, $result);
            for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
                $normalized = LdapUtils::normalizeEntry($entries[$i], self::USER_LIST_ATTRS);
                $users[] = User::fromLdapEntry($normalized);
            }
        }

        return $users;
    }

    public function updateUser(string $domain, User $user): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn("{$user->uid}@{$domain}");

        $mods = [
            LdapUtils::modReplace('domainGlobalAdmin', $user->domainGlobalAdmin ? 'yes' : null),
            LdapUtils::modReplace('mailQuota', (string) ($user->mailQuota * 1024 * 1024)),
            LdapUtils::modReplace('cn', $user->cn ?: null),
            LdapUtils::modReplace('givenName', $user->givenName ?: null),
            LdapUtils::modReplace('sn', $user->sn ?: null),
            LdapUtils::modReplace('employeeNumber', $user->employeeNumber ?: null),
            LdapUtils::modReplace('title', $user->title ?: null),
            LdapUtils::modReplace('telephoneNumber', $user->telephoneNumber ?: null),
            LdapUtils::modReplace('mobile', $user->mobile ?: null),
            LdapUtils::modReplace('accountStatus', $user->accountStatus ? 'active' : 'disabled'),
        ];

        if (!LdapUtils::modifyBatch($conn, $dn, $mods)) {
            throw new \RuntimeException('LDAP update failed: ' . ldap_error($conn));
        }

        // Update enabledService as a separate mod_replace (multi-valued attribute)
        $serviceList = $user->toLdapServiceList();
        if (!@ldap_mod_replace($conn, $dn, ['enabledService' => $serviceList])) {
            throw new \RuntimeException('LDAP update failed: ' . ldap_error($conn));
        }
    }

    public function updateUserPassword(string $domain, string $userUid, string $passwordHash): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn("{$userUid}@{$domain}");
        if (!ldap_mod_replace($conn, $dn, ['userPassword' => $passwordHash])) {
            throw new \RuntimeException('LDAP password update failed: ' . ldap_error($conn));
        }
    }

    public function createUser(string $domain, User $user, string $passwordHash): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = \App\Models\Settings::getInstance();
        $email = "{$user->uid}@{$domain}";
        $dn = LdapUtils::getEmailDn($email);

        $entry = [
            'objectClass' => ['inetOrgPerson', 'mailUser', 'shadowAccount', 'amavisAccount'],
            'mail' => $email,
            'uid' => $user->uid,
            'cn' => $user->cn ?: $user->uid,
            'sn' => $user->sn ?: $user->uid,
            'userPassword' => $passwordHash,
            'accountStatus' => $user->accountStatus ? 'active' : 'disabled',
            'homeDirectory' => "{$settings->vmailPath}/{$domain}/{$user->uid}/",
            'amavisLocal' => 'TRUE',
            'enabledService' => $user->toLdapServiceList(),
            'storageBaseDirectory' => $settings->vmailPath,
            'mailMessageStore' => "{$domain}/{$user->uid}/",
        ];

        if ($user->mailQuota > 0) {
            $entry['mailQuota'] = (string) ($user->mailQuota * 1048576);
        }

        if ($user->givenName !== '') {
            $entry['givenName'] = $user->givenName;
        }
        if ($user->employeeNumber !== '') {
            $entry['employeeNumber'] = $user->employeeNumber;
        }
        if ($user->title !== '') {
            $entry['title'] = $user->title;
        }
        if ($user->mobile !== '') {
            $entry['mobile'] = $user->mobile;
        }
        if ($user->telephoneNumber !== '') {
            $entry['telephoneNumber'] = $user->telephoneNumber;
        }

        if (!@ldap_add($conn, $dn, $entry)) {
            throw new \RuntimeException('LDAP user creation failed: ' . ldap_error($conn));
        }
    }

    public function supportsCreateUser(): bool
    {
        return true;
    }

    public function getUsersPaginated(string $domain, int $page, int $perPage, ?string $startsWith = null, ?bool $activeOnly = null, string $sortBy = 'uid', string $sortDir = 'asc'): PaginatedResult
    {
        $conn = LdapConnection::getInstance()->getConn();
        $baseDn = 'ou=Users,' . LdapUtils::getDomainDn($domain);
        $safeDomain = ldap_escape($domain, '', LDAP_ESCAPE_FILTER);

        $filter = "(&(objectClass=mailUser)(!(mail=@{$safeDomain}))";

        if ($startsWith !== null && $startsWith !== '') {
            $safeLetter = ldap_escape($startsWith, '', LDAP_ESCAPE_FILTER);
            $filter .= "(uid={$safeLetter}*)";
        }

        if ($activeOnly === true) {
            $filter .= "(accountStatus=active)";
        } elseif ($activeOnly === false) {
            $filter .= "(accountStatus=disabled)";
        }

        $filter .= ')';

        $result = @ldap_list($conn, $baseDn, $filter, self::USER_LIST_ATTRS);

        $allUsers = [];
        if ($result !== false) {
            $entries = ldap_get_entries($conn, $result);
            for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
                $normalized = LdapUtils::normalizeEntry($entries[$i], self::USER_LIST_ATTRS);
                $allUsers[] = User::fromLdapEntry($normalized);
            }
        }

        // Sort in PHP
        $sortProperty = match ($sortBy) {
            'mailQuota' => 'mailQuota',
            'accountStatus' => 'accountStatus',
            'cn' => 'cn',
            default => 'uid',
        };
        $descending = strtoupper($sortDir) === 'DESC';
        usort($allUsers, function (User $a, User $b) use ($sortProperty, $descending) {
            $cmp = match ($sortProperty) {
                'mailQuota' => $a->mailQuota <=> $b->mailQuota,
                'accountStatus' => (int) $a->accountStatus <=> (int) $b->accountStatus,
                default => strcmp($a->{$sortProperty}, $b->{$sortProperty}),
            };
            return $descending ? -$cmp : $cmp;
        });

        $totalCount = count($allUsers);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($allUsers, $offset, $perPage);

        return new PaginatedResult($items, $totalCount, $page, $perPage);
    }

    public function verifyUserPassword(string $domain, string $userUid, string $password): bool
    {
        $settings = \App\Models\Settings::getInstance();
        $email = "{$userUid}@{$domain}";
        $dn = LdapUtils::getEmailDn($email);

        // Attempt bind as the user to verify password
        $testConn = @ldap_connect($settings->ldapUri);
        if ($testConn === false) {
            return false;
        }

        ldap_set_option($testConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        $result = @ldap_bind($testConn, $dn, $password);
        @ldap_unbind($testConn);

        return $result;
    }

    public function deleteUser(string $domain, string $userUid, string $adminEmail): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn("{$userUid}@{$domain}");

        if (!@ldap_delete($conn, $dn)) {
            throw new \RuntimeException("LDAP user deletion failed for '{$userUid}@{$domain}': " . ldap_error($conn));
        }
    }

    public function renameUser(string $domain, string $oldUid, string $newUid): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $oldDn = LdapUtils::getEmailDn("{$oldUid}@{$domain}");
        $newRdn = "mail=" . ldap_escape("{$newUid}@{$domain}", '', LDAP_ESCAPE_DN);
        $settings = \App\Models\Settings::getInstance();
        $parentDn = "ou=Users,domainName=" . ldap_escape($domain, '', LDAP_ESCAPE_DN) . ",o=domains,{$settings->ldapRootDn}";

        if (!@ldap_rename($conn, $oldDn, $newRdn, $parentDn, true)) {
            throw new \RuntimeException("LDAP rename failed: " . ldap_error($conn));
        }

        // Update mail attribute
        $newDn = "{$newRdn},{$parentDn}";
        @ldap_modify($conn, $newDn, ['mail' => "{$newUid}@{$domain}"]);
    }
}
