<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\DomainAlias;
use App\Models\PaginatedResult;

interface DomainAliasRepositoryInterface
{
    /**
     * Returns all aliases pointing to a specific target domain.
     *
     * @return DomainAlias[]
     */
    public function getAliasesForDomain(string $domain): array;

    /**
     * Returns paginated list of all domain aliases.
     */
    public function getAllAliasesPaginated(int $page, int $perPage): PaginatedResult;

    /**
     * Returns a single alias by its alias domain name, or null if not found.
     */
    public function getAlias(string $aliasDomain): ?DomainAlias;

    /**
     * Creates a new domain alias.
     */
    public function createAlias(DomainAlias $alias): void;

    /**
     * Deletes a domain alias.
     */
    public function deleteAlias(string $aliasDomain): void;

    /**
     * Enables or disables a domain alias.
     */
    public function enableDisableAlias(string $aliasDomain, bool $active): void;

    /**
     * Returns false when the backend stores no active/inactive status for an alias domain.
     */
    public function supportsStatus(): bool;
}
