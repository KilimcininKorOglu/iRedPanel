<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\Admin;
use App\Models\LdapConnection;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\AdminRepositoryInterface;
use App\Repositories\RepositoryFactory;
use App\Utils\LdapUtils;

/**
 * Admins in the iRedAdmin-Pro LDAP layout. A standalone admin is a `mailAdmin` entry
 * `mail=<address>,o=domainAdmins`; a mailbox admin is a `mailUser` entry. Either one is a
 * global admin with `domainGlobalAdmin: yes`, and a domain admin of every domain whose
 * entry lists its address in `domainAdmin`. Creation limits are `accountSetting` values.
 */
class LdapAdminRepository implements AdminRepositoryInterface
{
    private const DOMAIN_ADMIN_SERVICE = 'domainadmin';

    private const ADMIN_ATTRS = ['mail', 'cn', 'accountStatus', 'domainGlobalAdmin', 'accountSetting', 'objectClass'];

    public function getAdmins(): array
    {
        $conn = self::conn();
        $admins = [];
        foreach (LdapUtils::searchEntries($conn, self::adminsBase(), '(objectClass=mailAdmin)', self::ADMIN_ATTRS) as $entry) {
            $admins[] = self::toAdmin($entry);
        }

        $filter = '(&(objectClass=mailUser)(|(domainGlobalAdmin=yes)'
            . implode('', array_map(
                static fn (string $mail): string => '(mail=' . ldap_escape($mail, '', LDAP_ESCAPE_FILTER) . ')',
                self::domainAdminAddresses($conn)
            )) . '))';
        foreach (LdapUtils::searchEntries($conn, self::domainsBase(), $filter, self::ADMIN_ATTRS) as $entry) {
            $admins[] = self::toAdmin($entry);
        }

        usort($admins, static fn (Admin $a, Admin $b): int => strcmp($a->username, $b->username));

        return $admins;
    }

    public function getAdmin(string $username): ?Admin
    {
        $entry = self::findAdminEntry(self::conn(), $username);

        return $entry === null ? null : self::toAdmin($entry);
    }

    public function createAdmin(Admin $admin, string $passwordHash): void
    {
        $conn = self::conn();
        $entry = [
            'objectClass' => ['mailAdmin'],
            'mail' => $admin->username,
            'userPassword' => $passwordHash,
            'accountStatus' => $admin->active ? 'active' : 'disabled',
        ];
        if ($admin->name !== '') {
            $entry['cn'] = $admin->name;
        }
        if ($admin->isGlobalAdmin) {
            $entry['domainGlobalAdmin'] = 'yes';
        }

        if (!@ldap_add($conn, self::standaloneDn($admin->username), $entry)) {
            throw new \RuntimeException("LDAP admin creation failed for '{$admin->username}': " . ldap_error($conn));
        }
    }

    public function updateAdmin(Admin $admin): void
    {
        LdapUtils::replaceValues(self::conn(), self::requireAdminDn($admin->username), [
            'cn' => $admin->name !== '' ? [$admin->name] : [],
            'accountStatus' => [$admin->active ? 'active' : 'disabled'],
            'domainGlobalAdmin' => $admin->isGlobalAdmin ? ['yes'] : [],
        ]);
    }

    public function updateAdminPassword(string $username, string $passwordHash): void
    {
        LdapUtils::replaceValues(self::conn(), self::requireAdminDn($username), ['userPassword' => [$passwordHash]]);
    }

    /**
     * Deletes a standalone admin entry. A mailbox admin keeps its mailbox and loses
     * the global flag and every domain assignment, as on the SQL backends.
     */
    public function deleteAdmin(string $username): void
    {
        $conn = self::conn();
        $entry = self::findAdminEntry($conn, $username)
            ?? throw new \RuntimeException("Admin '{$username}' not found");
        if (($entry['domainglobaladmin'][0] ?? '') === 'yes' && $this->countGlobalAdmins() <= 1) {
            throw new \RuntimeException('Cannot delete the last global admin');
        }

        foreach ($this->getManagedDomains($username) as $domain) {
            $this->revokeDomainFromAdmin($username, $domain);
        }
        if (self::isStandalone($entry)) {
            if (!@ldap_delete($conn, $entry['dn'])) {
                throw new \RuntimeException("LDAP admin deletion failed for '{$username}': " . ldap_error($conn));
            }
            return;
        }
        LdapUtils::replaceValues($conn, $entry['dn'], ['domainGlobalAdmin' => []]);
    }

    public function getManagedDomains(string $adminUsername): array
    {
        $filter = '(&(objectClass=mailDomain)(domainAdmin=' . ldap_escape(strtolower($adminUsername), '', LDAP_ESCAPE_FILTER) . '))';
        $domains = array_map(
            static fn (array $entry): string => LdapUtils::allValues($entry, 'domainName')[0] ?? '',
            LdapUtils::searchEntries(self::conn(), self::domainsBase(), $filter, ['domainName'])
        );
        sort($domains);

        return $domains;
    }

    public function getDomainAdmins(string $domain): array
    {
        $entry = LdapUtils::readEntry(self::conn(), LdapUtils::getDomainDn($domain), '(objectClass=mailDomain)', ['domainAdmin']);
        $admins = array_values(array_unique(array_map('strtolower', LdapUtils::allValues($entry ?? [], 'domainAdmin'))));
        sort($admins);

        return $admins;
    }

    public function assignDomainToAdmin(string $adminUsername, string $domain): void
    {
        $conn = self::conn();
        LdapUtils::addValues($conn, LdapUtils::getDomainDn($domain), 'domainAdmin', [strtolower($adminUsername)]);
        // iRedAdmin marks a mailbox that administers a domain with this service.
        $mailbox = self::mailboxEntry($conn, $adminUsername);
        if ($mailbox !== null) {
            LdapUtils::addValues($conn, $mailbox['dn'], 'enabledService', [self::DOMAIN_ADMIN_SERVICE]);
        }
    }

    public function revokeDomainFromAdmin(string $adminUsername, string $domain): void
    {
        $conn = self::conn();
        LdapUtils::deleteValues($conn, LdapUtils::getDomainDn($domain), 'domainAdmin', [strtolower($adminUsername)]);
        $mailbox = self::mailboxEntry($conn, $adminUsername);
        $keepsService = $mailbox === null
            || (LdapUtils::allValues($mailbox, 'domainGlobalAdmin')[0] ?? '') === 'yes'
            || $this->getManagedDomains($adminUsername) !== [];
        if (!$keepsService) {
            LdapUtils::deleteValues($conn, $mailbox['dn'], 'enabledService', [self::DOMAIN_ADMIN_SERVICE]);
        }
    }

    /**
     * @return array<string, mixed>|null the mailbox entry of the address, or null for a standalone admin
     */
    private static function mailboxEntry(\LDAP\Connection $conn, string $address): ?array
    {
        return LdapUtils::readEntry($conn, LdapUtils::accountDn($address, 'Users'), '(objectClass=mailUser)', ['domainGlobalAdmin']);
    }

    public function enableDisableAdmin(string $username, bool $active): void
    {
        LdapUtils::replaceValues(self::conn(), self::requireAdminDn($username), ['accountStatus' => [$active ? 'active' : 'disabled']]);
    }

    /**
     * Stores the creation limits as accountSetting values and keeps the other values.
     */
    public function updateAdminSettings(Admin $admin): void
    {
        $conn = self::conn();
        $entry = self::findAdminEntry($conn, $admin->username)
            ?? throw new \RuntimeException("Admin '{$admin->username}' not found");

        $kept = array_filter(
            LdapUtils::allValues($entry, 'accountSetting'),
            static fn (string $value): bool => !in_array(explode(':', $value, 2)[0], Admin::SETTING_KEYS, true)
        );

        LdapUtils::replaceValues($conn, $entry['dn'], ['accountSetting' => [...array_values($kept), ...$admin->toLdapAccountSetting()]]);
    }

    public function getAdminsPaginated(int $page, int $perPage): PaginatedResult
    {
        $admins = $this->getAdmins();

        return new PaginatedResult(array_slice($admins, max(0, ($page - 1) * $perPage), $perPage), count($admins), $page, $perPage);
    }

    public function countManagedDomains(string $adminUsername): int
    {
        return count($this->getManagedDomains($adminUsername));
    }

    public function countGlobalAdmins(): int
    {
        return count(array_filter($this->getAdmins(), static fn (Admin $admin): bool => $admin->isGlobalAdmin));
    }

    public function getAdminResourceCounts(string $adminUsername): array
    {
        $isGlobal = $this->getAdmin($adminUsername)?->isGlobalAdmin === true;
        $domainNames = $isGlobal
            ? array_map(static fn (array $d): string => $d['domainName'], RepositoryFactory::getDomainRepository()->getDomains())
            : $this->getManagedDomains($adminUsername);

        $counts = ['domains' => count($domainNames), 'users' => 0, 'aliases' => 0, 'lists' => 0, 'quotaMb' => 0];
        foreach ($domainNames as $domainName) {
            $users = RepositoryFactory::getUserRepository()->getUsersPaginated($domainName, 1, PHP_INT_MAX)->items;
            $counts['users'] += count($users);
            $counts['quotaMb'] += array_sum(array_map(static fn ($user): int => $user->mailQuota, $users));
            $counts['aliases'] += RepositoryFactory::getAliasRepository()->getAliasesPaginated(1, 1, $domainName)->total;
            $counts['lists'] += RepositoryFactory::getMailingListRepository()->getMailingListsPaginated(1, 1, $domainName)->total;
        }

        return $counts;
    }

    /**
     * Finds the entry of an admin: a standalone admin first, then a mailbox that is a
     * global admin or listed in the domainAdmin of a domain.
     *
     * @return array<string, mixed>|null
     */
    public static function findAdminEntry(\LDAP\Connection $conn, string $username): ?array
    {
        $username = strtolower($username);
        $entry = LdapUtils::readEntry($conn, self::standaloneDn($username), '(objectClass=mailAdmin)', self::ADMIN_ATTRS);
        if ($entry !== null) {
            return $entry;
        }

        $safe = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
        $entries = LdapUtils::searchEntries($conn, self::domainsBase(), "(&(objectClass=mailUser)(mail={$safe}))", self::ADMIN_ATTRS);
        $entry = $entries[0] ?? null;
        if ($entry === null) {
            return null;
        }
        $isAdmin = (LdapUtils::allValues($entry, 'domainGlobalAdmin')[0] ?? '') === 'yes'
            || in_array($username, self::domainAdminAddresses($conn), true);

        return $isAdmin ? $entry : null;
    }

    private static function conn(): \LDAP\Connection
    {
        return LdapConnection::getInstance()->getConn();
    }

    private static function adminsBase(): string
    {
        return 'o=domainAdmins,' . Settings::getInstance()->ldapRootDn;
    }

    private static function domainsBase(): string
    {
        return 'o=domains,' . Settings::getInstance()->ldapRootDn;
    }

    private static function standaloneDn(string $username): string
    {
        return 'mail=' . ldap_escape(strtolower($username), '', LDAP_ESCAPE_DN) . ',' . self::adminsBase();
    }

    private static function requireAdminDn(string $username): string
    {
        $entry = self::findAdminEntry(self::conn(), $username)
            ?? throw new \RuntimeException("Admin '{$username}' not found");

        return $entry['dn'];
    }

    /**
     * @return string[] every address listed in the domainAdmin attribute of a domain
     */
    private static function domainAdminAddresses(\LDAP\Connection $conn): array
    {
        $addresses = [];
        foreach (LdapUtils::searchEntries($conn, self::domainsBase(), '(&(objectClass=mailDomain)(domainAdmin=*))', ['domainAdmin']) as $entry) {
            array_push($addresses, ...array_map('strtolower', LdapUtils::allValues($entry, 'domainAdmin')));
        }

        return array_values(array_unique($addresses));
    }

    private static function isStandalone(array $entry): bool
    {
        return in_array('mailadmin', array_map('strtolower', LdapUtils::allValues($entry, 'objectClass')), true);
    }

    private static function toAdmin(array $entry): Admin
    {
        $normalized = LdapUtils::normalizeEntry($entry, self::ADMIN_ATTRS);
        $normalized['accountSetting'] = implode(';', LdapUtils::allValues($entry, 'accountSetting'));

        return Admin::fromLdapEntry($normalized, !self::isStandalone($entry));
    }
}
