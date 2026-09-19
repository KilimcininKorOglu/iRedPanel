<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Models\User;
use App\Repositories\Mysql\IredadminConnection;
use App\Repositories\UserRepositoryInterface;
use App\Utils\LdapUtils;

class LdapUserRepository implements UserRepositoryInterface
{
    private const USER_DETAIL_ATTRS = [
        'mail', 'accountStatus', 'domainGlobalAdmin', 'mailQuota', 'uid',
        'cn', 'givenName', 'sn', 'title', 'telephoneNumber', 'mobile', 'employeeNumber',
        'enabledService',
    ];

    /** Attributes that store a mail address and must follow a rename. */
    /** iredadmin table and column pairs that hold a mailbox address on the LDAP backend. */
    private const IREDADMIN_ADDRESS_COLUMNS = [
        ['used_quota', 'username'], ['last_login', 'username'],
        ['share_folder', 'from_user'], ['share_folder', 'to_user'], ['anyone_shares', 'from_user'],
    ];

    private const ADDRESS_ATTRS = [
        'mailForwardingAddress', 'listModerator', 'listOwner', 'listAllowedUser',
        'userSenderBccAddress', 'userRecipientBccAddress', 'domainSenderBccAddress', 'domainRecipientBccAddress',
    ];

    private const USER_LIST_ATTRS = [
        'mail', 'accountStatus', 'domainGlobalAdmin', 'mailQuota', 'uid', 'cn',
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
        return self::listUsers($domain);
    }

    /**
     * Reads the mailboxes of a domain without the catch-all entry. An LDAP error throws.
     *
     * @param string $extraFilter filter components added inside the AND
     * @return User[]
     */
    private static function listUsers(string $domain, string $extraFilter = ''): array
    {
        $safeDomain = ldap_escape($domain, '', LDAP_ESCAPE_FILTER);
        $entries = LdapUtils::searchEntries(
            LdapConnection::getInstance()->getConn(),
            'ou=Users,' . LdapUtils::getDomainDn($domain),
            "(&(objectClass=mailUser)(!(mail=@{$safeDomain})){$extraFilter})",
            self::USER_LIST_ATTRS
        );

        return array_map(
            static fn (array $entry): User => User::fromLdapEntry(LdapUtils::normalizeEntry($entry, self::USER_LIST_ATTRS)),
            $entries
        );
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
        $serviceList = $user->toLdapServiceList(self::storedServices($conn, $dn));
        if (!@ldap_mod_replace($conn, $dn, ['enabledService' => $serviceList])) {
            throw new \RuntimeException('LDAP update failed: ' . ldap_error($conn));
        }
    }

    /**
     * @return string[] the enabledService values of the entry
     */
    private static function storedServices(\LDAP\Connection $conn, string $dn): array
    {
        $result = @ldap_read($conn, $dn, '(objectClass=mailUser)', ['enabledService']);
        if ($result === false) {
            throw new \RuntimeException('LDAP read failed: ' . ldap_error($conn));
        }
        $values = ldap_get_entries($conn, $result)[0]['enabledservice'] ?? ['count' => 0];
        unset($values['count']);

        return array_values($values);
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
        // Same layout as the SQL backends: <vmail path>/<storage node>/<domain>/<uid>/
        $maildir = "{$settings->storageNode}/{$domain}/{$user->uid}/";

        $entry = [
            'objectClass' => ['inetOrgPerson', 'mailUser', 'shadowAccount', 'amavisAccount'],
            'mail' => $email,
            'uid' => $user->uid,
            'cn' => $user->cn ?: $user->uid,
            'sn' => $user->sn ?: $user->uid,
            'userPassword' => $passwordHash,
            'accountStatus' => $user->accountStatus ? 'active' : 'disabled',
            'homeDirectory' => "{$settings->vmailPath}/{$maildir}",
            'amavisLocal' => 'TRUE',
            // The caller sets the toggles with setNewMailboxServices().
            'enabledService' => $user->toLdapServiceList(),
            'storageBaseDirectory' => $settings->vmailPath,
            'mailMessageStore' => $maildir,
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
        $shadowAddresses = LdapUtils::aliasDomainAddresses($conn, $email);
        if ($shadowAddresses !== []) {
            $entry['shadowAddress'] = $shadowAddresses;
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
        $filter = '';
        if ($startsWith !== null && $startsWith !== '') {
            $filter .= '(uid=' . ldap_escape($startsWith, '', LDAP_ESCAPE_FILTER) . '*)';
        }
        if ($activeOnly !== null) {
            $filter .= $activeOnly ? '(accountStatus=active)' : '(accountStatus=disabled)';
        }

        $allUsers = self::listUsers($domain, $filter);

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
        $email = "{$userUid}@{$domain}";
        $dn = LdapUtils::getEmailDn($email);

        $result = @ldap_read($conn, $dn, '(objectClass=mailUser)', ['homeDirectory']);
        if ($result === false) {
            throw new \RuntimeException("LDAP user deletion failed for '{$email}': " . ldap_error($conn));
        }
        $maildir = LdapUtils::allValues(ldap_get_entries($conn, $result)[0] ?? [], 'homeDirectory')[0] ?? '';

        if (!@ldap_delete($conn, $dn)) {
            throw new \RuntimeException("LDAP user deletion failed for '{$email}': " . ldap_error($conn));
        }

        self::replaceAddressReferences($conn, $email, null);
        self::deleteIredadminRows($email, $domain, $maildir, $adminEmail);
    }

    public function renameUser(string $domain, string $oldUid, string $newUid): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $oldEmail = "{$oldUid}@{$domain}";
        $newEmail = "{$newUid}@{$domain}";
        $newRdn = 'mail=' . ldap_escape($newEmail, '', LDAP_ESCAPE_DN);
        $parentDn = 'ou=Users,' . LdapUtils::getDomainDn($domain);

        if (!@ldap_rename($conn, LdapUtils::getEmailDn($oldEmail), $newRdn, $parentDn, true)) {
            throw new \RuntimeException('LDAP rename failed: ' . ldap_error($conn));
        }
        // The panel finds a user by uid, so uid must follow the address.
        if (!@ldap_mod_replace($conn, "{$newRdn},{$parentDn}", ['mail' => [$newEmail], 'uid' => [$newUid]])) {
            throw new \RuntimeException('LDAP rename failed: ' . ldap_error($conn));
        }

        // The addresses in the alias domains follow the new local part.
        LdapUtils::deleteValues($conn, "{$newRdn},{$parentDn}", 'shadowAddress', LdapUtils::aliasDomainAddresses($conn, $oldEmail));
        LdapUtils::addValues($conn, "{$newRdn},{$parentDn}", 'shadowAddress', LdapUtils::aliasDomainAddresses($conn, $newEmail));

        self::replaceAddressReferences($conn, $oldEmail, $newEmail);
        self::renameIredadminRows($oldEmail, $newEmail);
    }

    /**
     * Replaces the old address in every entry attribute that stores an address, or removes it
     * when $newEmail is null, as the SQL backends do for their forwardings, moderator, owner
     * and BCC columns.
     */
    private static function replaceAddressReferences(\LDAP\Connection $conn, string $oldEmail, ?string $newEmail): void
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
            $values = LdapUtils::allValues($entry, $attr);
            $kept = array_filter($values, static fn (string $value): bool => strcasecmp($value, $oldEmail) !== 0);
            if (count($kept) !== count($values)) {
                $changes[$attr] = array_values(array_unique($newEmail === null ? $kept : [...$kept, $newEmail]));
            }
        }

        return $changes;
    }

    /**
     * With the LDAP backend, Dovecot keeps quota, last login and shared folder rows in the
     * iredadmin database. Without a configured iredadmin database there are no rows to move.
     */
    private static function renameIredadminRows(string $oldEmail, string $newEmail): void
    {
        $pdo = IredadminConnection::getInstance()->getPdo();
        if ($pdo === null) {
            return;
        }
        foreach (self::IREDADMIN_ADDRESS_COLUMNS as [$table, $column]) {
            $pdo->prepare("UPDATE {$table} SET {$column} = :new WHERE {$column} = :old")
                ->execute(['new' => $newEmail, 'old' => $oldEmail]);
        }
    }

    /**
     * Records the maildir for deferred deletion and removes the quota, last login and shared
     * folder rows, as the SQL backends do in the vmail database. Domain deletion calls it
     * for every mailbox of the domain.
     */
    public static function deleteIredadminRows(string $email, string $domain, string $maildir, string $adminEmail): void
    {
        $pdo = IredadminConnection::getInstance()->getPdo();
        if ($pdo === null) {
            return;
        }
        $pdo->prepare("INSERT INTO deleted_mailboxes (username, maildir, domain, admin) VALUES (:username, :maildir, :domain, :admin)")
            ->execute(['username' => $email, 'maildir' => rtrim($maildir, '/'), 'domain' => $domain, 'admin' => $adminEmail]);
        foreach (self::IREDADMIN_ADDRESS_COLUMNS as [$table, $column]) {
            $pdo->prepare("DELETE FROM {$table} WHERE {$column} = :email")->execute(['email' => $email]);
        }
    }
}
