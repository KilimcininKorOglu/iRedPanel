<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Alias;
use App\Models\PaginatedResult;

interface AliasRepositoryInterface
{
    /**
     * @param ?bool $activeOnly true for the active aliases, false for the disabled ones, null for all
     */
    public function getAliasesPaginated(int $page, int $perPage, ?string $domain = null, ?bool $activeOnly = null): PaginatedResult;

    public function getAlias(string $address): ?Alias;

    public function createAlias(string $address, string $domain, string $name, array $members, string $accessPolicy): bool;

    public function updateAlias(string $address, string $name, array $members, string $accessPolicy, bool $active): bool;

    public function deleteAlias(string $address): bool;

    /**
     * Moves an alias to a free address in the same domain. The members, the moderators
     * and the references to the alias in other accounts follow the new address.
     */
    public function renameAlias(string $oldAddress, string $newAddress): void;

    /** @return string[] */
    public function getAliasMembers(string $address): array;

    public function addAliasMember(string $address, string $member): bool;

    public function removeAliasMember(string $address, string $member): bool;

    /** @return string[] */
    public function getModerators(string $address): array;

    /** @param string[] $moderators */
    public function setModerators(string $address, array $moderators): bool;

    /** @return string[] */
    public function getUserAliases(string $email): array;

    public function addUserAlias(string $email, string $aliasAddress): bool;

    public function removeUserAlias(string $email, string $aliasAddress): bool;

    /**
     * Returns true when a mailbox, alias, mailing list or per-user alias already uses the address.
     */
    public function isAddressInUse(string $address): bool;

    public function getCatchall(string $domain): ?string;

    public function setCatchall(string $domain, ?string $targetEmail): bool;

    public function enableDisableAlias(string $address, bool $active): bool;

    /**
     * Counts total aliases + mailing lists for a domain (for limit enforcement).
     */
    public function countAliasesForDomain(string $domain): int;
}
