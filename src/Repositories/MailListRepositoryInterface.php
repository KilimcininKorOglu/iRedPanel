<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MailList;
use App\Models\PaginatedResult;

/**
 * Mail lists of the LDAP backend: group accounts with the members that the
 * admin manages. Only the LDAP backend has this account type.
 */
interface MailListRepositoryInterface
{
    /** Whether the backend holds mail lists. */
    public function isAvailable(): bool;

    /**
     * @param ?list<string> $domains the domains of a domain admin, null for every domain
     */
    public function getMailListsPaginated(?array $domains, int $page, int $perPage, ?bool $activeOnly = null): PaginatedResult;

    public function getMailList(string $address): ?MailList;

    /**
     * @throws \RuntimeException when the directory refuses the entry
     */
    public function createMailList(MailList $list): void;

    /**
     * @throws \RuntimeException when the directory refuses the change
     */
    public function updateMailList(MailList $list): void;

    /**
     * @throws \RuntimeException when the directory refuses the delete
     */
    public function deleteMailList(string $address): void;

    /** Whether an account already uses the address. */
    public function isAddressInUse(string $address): bool;
}
