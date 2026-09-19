<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\Admin;
use App\Models\LdapConnection;
use App\Models\Settings;
use App\Repositories\AdminRepositoryInterface;
use App\Utils\LdapUtils;

class LdapAdminRepository implements AdminRepositoryInterface
{
    private const ADMIN_ATTRS = ['mail', 'cn', 'accountStatus', 'domainGlobalAdmin'];

    public function getAdmins(): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();
        $admins = [];

        // Mail users with global admin flag
        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            '(&(objectClass=mailUser)(domainGlobalAdmin=yes))',
            self::ADMIN_ATTRS
        );

        if ($result !== false) {
            $entries = ldap_get_entries($conn, $result);
            for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
                $normalized = LdapUtils::normalizeEntry($entries[$i], self::ADMIN_ATTRS);
                $admins[] = Admin::fromLdapEntry($normalized, true);
            }
        }

        usort($admins, fn(Admin $a, Admin $b) => strcmp($a->username, $b->username));

        return $admins;
    }

    public function getAdmin(string $username): ?Admin
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();
        $safeUsername = ldap_escape($username, '', LDAP_ESCAPE_FILTER);

        // Search as mail user with admin flag
        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            "(&(objectClass=mailUser)(mail={$safeUsername}))",
            self::ADMIN_ATTRS
        );

        if ($result !== false && ldap_count_entries($conn, $result) > 0) {
            $entries = ldap_get_entries($conn, $result);
            $normalized = LdapUtils::normalizeEntry($entries[0], self::ADMIN_ATTRS);
            return Admin::fromLdapEntry($normalized, true);
        }

        return null;
    }

    public function createAdmin(Admin $admin, string $passwordHash): void
    {
        // In LDAP, admins are typically mail users promoted to global admin.
        // Standalone admin creation would require a separate objectClass (mailAdmin).
        // For now, we only support promoting existing mail users.
        throw new \RuntimeException('Standalone admin creation is not supported for LDAP. Promote an existing mail user instead.');
    }

    public function updateAdmin(Admin $admin): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();
        $safeUsername = ldap_escape($admin->username, '', LDAP_ESCAPE_FILTER);

        // Find user DN
        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            "(&(objectClass=mailUser)(mail={$safeUsername}))",
            ['dn']
        );

        if ($result === false || ldap_count_entries($conn, $result) === 0) {
            throw new \RuntimeException("Admin user '{$admin->username}' not found in LDAP");
        }

        $entries = ldap_get_entries($conn, $result);
        $dn = $entries[0]['dn'];

        $mods = [
            LdapUtils::modReplace('cn', $admin->name ?: null),
            LdapUtils::modReplace('accountStatus', $admin->active ? 'active' : 'disabled'),
            LdapUtils::modReplace('domainGlobalAdmin', $admin->isGlobalAdmin ? 'yes' : null),
        ];

        if (!LdapUtils::modifyBatch($conn, $dn, $mods)) {
            throw new \RuntimeException('LDAP admin update failed: ' . ldap_error($conn));
        }
    }

    public function updateAdminPassword(string $username, string $passwordHash): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();
        $safeUsername = ldap_escape($username, '', LDAP_ESCAPE_FILTER);

        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            "(&(objectClass=mailUser)(mail={$safeUsername}))",
            ['dn']
        );

        if ($result === false || ldap_count_entries($conn, $result) === 0) {
            throw new \RuntimeException("Admin user '{$username}' not found in LDAP");
        }

        $entries = ldap_get_entries($conn, $result);
        $dn = $entries[0]['dn'];

        if (!@ldap_mod_replace($conn, $dn, ['userPassword' => $passwordHash])) {
            throw new \RuntimeException('LDAP admin password update failed: ' . ldap_error($conn));
        }
    }

    public function deleteAdmin(string $username): void
    {
        // For LDAP, "deleting" an admin means revoking the domainGlobalAdmin attribute
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();
        $safeUsername = ldap_escape($username, '', LDAP_ESCAPE_FILTER);

        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            "(&(objectClass=mailUser)(mail={$safeUsername}))",
            ['dn']
        );

        if ($result === false || ldap_count_entries($conn, $result) === 0) {
            throw new \RuntimeException("Admin user '{$username}' not found in LDAP");
        }

        $entries = ldap_get_entries($conn, $result);
        $dn = $entries[0]['dn'];

        $mods = [LdapUtils::modReplace('domainGlobalAdmin', null)];

        if (!LdapUtils::modifyBatch($conn, $dn, $mods)) {
            throw new \RuntimeException('LDAP admin revocation failed: ' . ldap_error($conn));
        }
    }

    public function getManagedDomains(string $adminUsername): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();
        $safeAdmin = ldap_escape($adminUsername, '', LDAP_ESCAPE_FILTER);

        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            "(&(objectClass=mailDomain)(domainAdmin={$safeAdmin}))",
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

    public function assignDomainToAdmin(string $adminUsername, string $domain): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getDomainDn($domain);

        if (!@ldap_mod_add($conn, $dn, ['domainAdmin' => $adminUsername])) {
            throw new \RuntimeException('Failed to assign domain admin: ' . ldap_error($conn));
        }
    }

    public function revokeDomainFromAdmin(string $adminUsername, string $domain): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getDomainDn($domain);

        if (!@ldap_mod_del($conn, $dn, ['domainAdmin' => $adminUsername])) {
            throw new \RuntimeException('Failed to revoke domain admin: ' . ldap_error($conn));
        }
    }

    public function enableDisableAdmin(string $username, bool $active): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();
        $safeUsername = ldap_escape($username, '', LDAP_ESCAPE_FILTER);

        $result = @ldap_search(
            $conn,
            $settings->ldapRootDn,
            "(&(objectClass=mailUser)(mail={$safeUsername}))",
            ['dn']
        );

        if ($result === false || ldap_count_entries($conn, $result) === 0) {
            throw new \RuntimeException("Admin user '{$username}' not found in LDAP");
        }

        $entries = ldap_get_entries($conn, $result);
        $dn = $entries[0]['dn'];

        if (!@ldap_mod_replace($conn, $dn, ['accountStatus' => $active ? 'active' : 'disabled'])) {
            throw new \RuntimeException('LDAP admin status update failed: ' . ldap_error($conn));
        }
    }

    public function updateAdminSettings(string $username, string $settingsJson): void
    {
        // LDAP admin settings are not stored as JSON; no-op for LDAP backend
    }

    public function getAdminsPaginated(int $page, int $perPage): \App\Models\PaginatedResult
    {
        $admins = $this->getAdmins();
        $totalCount = count($admins);
        $offset = ($page - 1) * $perPage;
        $pageItems = array_slice($admins, $offset, $perPage);

        return new \App\Models\PaginatedResult($pageItems, $totalCount, $page, $perPage);
    }

    public function countManagedDomains(string $adminUsername): int
    {
        return count($this->getManagedDomains($adminUsername));
    }

    public function countGlobalAdmins(): int
    {
        $admins = $this->getAdmins();
        $count = 0;
        foreach ($admins as $admin) {
            if ($admin->isGlobalAdmin) {
                $count++;
            }
        }
        return $count;
    }

    public function getAdminResourceCounts(string $adminUsername): array
    {
        $managedDomains = $this->getManagedDomains($adminUsername);
        $admin = $this->getAdmin($adminUsername);
        $isGlobal = $admin !== null && $admin->isGlobalAdmin;

        $userRepo = \App\Repositories\RepositoryFactory::getUserRepository();
        $aliasRepo = \App\Repositories\RepositoryFactory::getAliasRepository();
        $mlRepo = \App\Repositories\RepositoryFactory::getMailingListRepository();
        $domainRepo = \App\Repositories\RepositoryFactory::getDomainRepository();

        $users = 0;
        $aliases = 0;
        $lists = 0;
        $quotaMb = 0;

        $domains = $isGlobal
            ? $domainRepo->getDomainsPaginated(1, 999999)->total
            : count($managedDomains);

        $domainNames = $isGlobal
            ? array_map(fn($d) => $d->domainName, $domainRepo->getDomainsPaginated(1, 999999)->items)
            : $managedDomains;

        foreach ($domainNames as $domainName) {
            $userResult = $userRepo->getUsersPaginated($domainName, 1, 1);
            $users += $userResult->total;

            $domain = $domainRepo->getDomain($domainName);
            if ($domain !== null) {
                $quotaMb += $domain->currentUserCount * ($domain->maxQuota > 0 ? $domain->maxQuota : 0);
            }
        }

        return [
            'domains' => $domains,
            'users' => $users,
            'aliases' => $aliases,
            'lists' => $lists,
            'quotaMb' => $quotaMb,
        ];
    }
}
