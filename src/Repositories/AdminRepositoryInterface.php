<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Admin;

interface AdminRepositoryInterface
{
    /**
     * Returns all admin accounts (standalone + mailbox-based admins).
     *
     * @return Admin[]
     */
    public function getAdmins(): array;

    /**
     * Returns a single admin by username, or null if not found.
     */
    public function getAdmin(string $username): ?Admin;

    /**
     * Creates a new standalone admin account.
     */
    public function createAdmin(Admin $admin, string $passwordHash): void;

    /**
     * Updates admin profile fields (name, active status).
     */
    public function updateAdmin(Admin $admin): void;

    /**
     * Updates an admin's password.
     */
    public function updateAdminPassword(string $username, string $passwordHash): void;

    /**
     * Deletes an admin account.
     */
    public function deleteAdmin(string $username): void;

    /**
     * Returns domain names managed by a specific admin.
     *
     * @return string[]
     */
    public function getManagedDomains(string $adminUsername): array;

    /**
     * Returns the addresses of the standalone and mailbox admins of a domain, lowercased and sorted.
     *
     * @return list<string>
     */
    public function getDomainAdmins(string $domain): array;

    /**
     * Assigns a domain to an admin for management. A mailbox becomes a domain admin, as in
     * iRedAdmin: SQL sets mailbox.isadmin, LDAP adds enabledService=domainadmin.
     */
    public function assignDomainToAdmin(string $adminUsername, string $domain): void;

    /**
     * Revokes a domain assignment from an admin. A mailbox loses the domain admin flag with
     * its last domain.
     */
    public function revokeDomainFromAdmin(string $adminUsername, string $domain): void;

    /**
     * Enables or disables an admin account.
     */
    public function enableDisableAdmin(string $username, bool $active): void;

    /**
     * Updates admin resource limits stored in settings JSON.
     */
    public function updateAdminSettings(string $username, string $settingsJson): void;

    /**
     * Returns paginated admin list.
     */
    public function getAdminsPaginated(int $page, int $perPage): \App\Models\PaginatedResult;

    /**
     * Counts domains managed by an admin.
     */
    public function countManagedDomains(string $adminUsername): int;

    /**
     * Counts active global admin accounts.
     */
    public function countGlobalAdmins(): int;

    /**
     * Returns resource counts for an admin across their managed domains.
     *
     * @return array{domains: int, users: int, aliases: int, lists: int, quotaMb: int}
     */
    public function getAdminResourceCounts(string $adminUsername): array;
}
