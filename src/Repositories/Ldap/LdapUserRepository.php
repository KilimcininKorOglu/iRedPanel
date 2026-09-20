<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Models\MailboxStorage;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Models\User;
use App\Repositories\Mysql\IredadminConnection;
use App\Repositories\UserRepositoryInterface;
use App\Utils\ExpiryDate;
use App\Utils\LdapUtils;

class LdapUserRepository implements UserRepositoryInterface
{
    private const USER_DETAIL_ATTRS = [
        'mail', 'accountStatus', 'domainGlobalAdmin', 'mailQuota', 'uid',
        'cn', 'givenName', 'sn', 'title', 'departmentNumber', 'birthday', 'expiredDate',
        'telephoneNumber', 'mobile', 'employeeNumber', 'allowNets', 'recoveryEmail',
        'enabledService', 'preferredLanguage', 'shadowLastChange',
    ];

    /** iredadmin table and column pairs that hold a mailbox address on the LDAP backend. */
    private const IREDADMIN_ADDRESS_COLUMNS = [
        ['used_quota', 'username'], ['last_login', 'username'],
        ['share_folder', 'from_user'], ['share_folder', 'to_user'], ['anyone_shares', 'from_user'],
    ];

    private const USER_LIST_ATTRS = [
        'mail', 'accountStatus', 'domainGlobalAdmin', 'mailQuota', 'uid', 'cn', 'shadowLastChange', 'expiredDate',
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
        // A detail read loads the language, so an entry without it has the default ('').
        $normalized['preferredLanguage'] ??= '';

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

    /**
     * An empty profile field deletes the LDAP attribute instead of storing "".
     */
    private static function orNull(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    public function updateUser(string $domain, User $user): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn("{$user->uid}@{$domain}");

        $mods = [
            LdapUtils::modReplace('domainGlobalAdmin', $user->domainGlobalAdmin ? 'yes' : null),
            LdapUtils::modReplace('mailQuota', (string) ($user->mailQuota * 1024 * 1024)),
            LdapUtils::modReplace('cn', self::orNull($user->cn)),
            LdapUtils::modReplace('givenName', self::orNull($user->givenName)),
            LdapUtils::modReplace('sn', self::orNull($user->sn)),
            LdapUtils::modReplace('employeeNumber', self::orNull($user->employeeNumber)),
            LdapUtils::modReplace('title', self::orNull($user->title)),
            LdapUtils::modReplace('departmentNumber', self::orNull($user->department)),
            LdapUtils::modReplace('birthday', self::orNull($user->birthday)),
            LdapUtils::modReplace('expiredDate', ExpiryDate::toLdap($user->expiredDate)),
            LdapUtils::modReplace('telephoneNumber', self::orNull($user->telephoneNumber)),
            LdapUtils::modReplace('mobile', self::orNull($user->mobile)),
            LdapUtils::modReplace('allowNets', self::orNull($user->allowNets)),
            LdapUtils::modReplace('recoveryEmail', self::orNull($user->recoveryEmail)),
            LdapUtils::modReplace('accountStatus', $user->accountStatus ? 'active' : 'disabled'),
        ];
        // null: the caller did not read the language, so the stored value stays.
        if ($user->language !== null) {
            $mods[] = LdapUtils::modReplace('preferredLanguage', self::orNull($user->language));
        }

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

    public function getTransport(string $domain, string $userUid): ?string
    {
        $conn = LdapConnection::getInstance()->getConn();
        $entry = LdapUtils::readEntry($conn, LdapUtils::getEmailDn("{$userUid}@{$domain}"), '(objectClass=mailUser)', ['mtaTransport']);

        return $entry === null ? null : (($entry['mtatransport'][0] ?? '') ?: null);
    }

    public function setTransport(string $domain, string $userUid, ?string $transport): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn("{$userUid}@{$domain}");
        if (!LdapUtils::modifyBatch($conn, $dn, [LdapUtils::modReplace('mtaTransport', $transport)])) {
            throw new \RuntimeException('LDAP transport update failed: ' . ldap_error($conn));
        }
    }

    public function getDisclaimer(string $domain, string $userUid): string
    {
        $conn = LdapConnection::getInstance()->getConn();
        $entry = LdapUtils::readEntry($conn, LdapUtils::getEmailDn("{$userUid}@{$domain}"), '(objectClass=mailUser)', ['disclaimer']);

        return (string) ($entry['disclaimer'][0] ?? '');
    }

    public function setDisclaimer(string $domain, string $userUid, string $disclaimer): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn("{$userUid}@{$domain}");
        // modReplace() removes the attribute for an empty text, so the domain disclaimer applies again.
        if (!LdapUtils::modifyBatch($conn, $dn, [LdapUtils::modReplace('disclaimer', $disclaimer)])) {
            throw new \RuntimeException('LDAP disclaimer update failed: ' . ldap_error($conn));
        }
    }

    public function updateUserPassword(string $domain, string $userUid, string $passwordHash): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn("{$userUid}@{$domain}");
        // shadowLastChange records the date, as iRedAdmin does; the panel shows it on the user page.
        $values = ['userPassword' => $passwordHash, 'shadowLastChange' => User::shadowToday()];
        if (!ldap_mod_replace($conn, $dn, $values)) {
            throw new \RuntimeException('LDAP password update failed: ' . ldap_error($conn));
        }
    }

    public function createUser(string $domain, User $user, string $passwordHash, ?MailboxStorage $storage = null): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = \App\Models\Settings::getInstance();
        $email = "{$user->uid}@{$domain}";
        $dn = LdapUtils::getEmailDn($email);
        $storage ??= new MailboxStorage();
        // Same layout as the SQL backends: <vmail path>/<storage node>/<domain>/<uid>/
        [$storageBase, $storageNode, $maildir] = $storage->location($domain, $user->uid, $settings->vmailPath, $settings->storageNode);
        $maildir = "{$storageNode}/{$maildir}";

        $entry = [
            'objectClass' => ['inetOrgPerson', 'mailUser', 'shadowAccount', 'amavisAccount'],
            'mail' => $email,
            'uid' => $user->uid,
            'cn' => $user->cn ?: $user->uid,
            'sn' => $user->sn ?: $user->uid,
            'userPassword' => $passwordHash,
            'accountStatus' => $user->accountStatus ? 'active' : 'disabled',
            'shadowLastChange' => User::shadowToday(),
            'homeDirectory' => "{$storageBase}/{$maildir}",
            'amavisLocal' => 'TRUE',
            // The caller sets the toggles with setNewMailboxServices().
            'enabledService' => $user->toLdapServiceList(),
            'storageBaseDirectory' => $storageBase,
            'mailMessageStore' => $maildir,
        ];

        if ($user->mailQuota > 0) {
            $entry['mailQuota'] = (string) ($user->mailQuota * 1048576);
        }

        // An empty attribute value is not allowed in an LDAP add, so an empty field is left out.
        $optional = [
            'givenName' => $user->givenName,
            'employeeNumber' => $user->employeeNumber,
            'title' => $user->title,
            'departmentNumber' => $user->department,
            'birthday' => $user->birthday,
            'expiredDate' => (string) ExpiryDate::toLdap($user->expiredDate),
            'mobile' => $user->mobile,
            'telephoneNumber' => $user->telephoneNumber,
            'allowNets' => $user->allowNets,
            'recoveryEmail' => $user->recoveryEmail,
            'preferredLanguage' => $user->language ?? '',
            // Dovecot reads a missing value as its default (maildir, Maildir).
            'mailboxFormat' => $storage->format ?? '',
            'mailboxFolder' => $storage->folder ?? '',
        ];
        $entry += array_filter($optional, static fn (string $value): bool => $value !== '');
        $shadowAddresses = LdapUtils::aliasDomainAddresses($conn, $email);
        if ($shadowAddresses !== []) {
            $entry['shadowAddress'] = $shadowAddresses;
        }

        if (!@ldap_add($conn, $dn, $entry)) {
            throw new \RuntimeException('LDAP user creation failed: ' . ldap_error($conn));
        }
    }

    public function isMailboxPathInUse(array $paths): bool
    {
        if ($paths === []) {
            return false;
        }
        $conn = LdapConnection::getInstance()->getConn();
        $filter = '(&(objectClass=mailUser)(|' . implode('', array_map(
            static fn (string $path): string => '(homeDirectory=' . ldap_escape($path, '', LDAP_ESCAPE_FILTER) . ')',
            $paths,
        )) . '))';

        return LdapUtils::searchEntries($conn, 'o=domains,' . Settings::getInstance()->ldapRootDn, $filter, ['mail']) !== [];
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
        $filter .= LdapUtils::statusFilter($activeOnly);

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

    public function deleteUser(string $domain, string $userUid, string $adminEmail, ?string $deleteDate = null): void
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

        LdapUtils::replaceAddressReferences($conn, $email, null);
        self::deleteIredadminRows($email, $domain, $maildir, $adminEmail, $deleteDate);
    }

    public function renameUser(string $domain, string $oldUid, string $newUid): void
    {
        $oldEmail = "{$oldUid}@{$domain}";
        $newEmail = "{$newUid}@{$domain}";
        // The panel finds a user by uid, so uid must follow the address.
        LdapUtils::renameAccountEntry(LdapConnection::getInstance()->getConn(), $oldEmail, $newEmail, 'Users', ['uid' => [$newUid]]);
        self::renameIredadminRows($oldEmail, $newEmail);
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
    public static function deleteIredadminRows(string $email, string $domain, string $maildir, string $adminEmail, ?string $deleteDate = null): void
    {
        $pdo = IredadminConnection::getInstance()->getPdo();
        if ($pdo === null) {
            return;
        }
        $pdo->prepare("INSERT INTO deleted_mailboxes (username, maildir, domain, admin, delete_date) VALUES (:username, :maildir, :domain, :admin, :deleteDate)")
            ->execute(['username' => $email, 'maildir' => rtrim($maildir, '/'), 'domain' => $domain, 'admin' => $adminEmail, 'deleteDate' => $deleteDate]);
        foreach (self::IREDADMIN_ADDRESS_COLUMNS as [$table, $column]) {
            $pdo->prepare("DELETE FROM {$table} WHERE {$column} = :email")->execute(['email' => $email]);
        }
    }
}
