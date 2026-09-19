<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Domain;
use App\Models\PaginatedResult;

interface DomainRepositoryInterface
{
    /**
     * Returns array of domain info arrays (legacy format for backward compatibility).
     *
     * @return array<int, array<string, string>>
     */
    public function getDomains(): array;

    /**
     * Returns paginated domain list as Domain model objects.
     */
    public function getDomainsPaginated(int $page, int $perPage, ?bool $activeOnly = null): PaginatedResult;

    /**
     * Returns a single domain by name, or null if not found.
     */
    public function getDomain(string $domainName): ?Domain;

    /**
     * Creates a new domain.
     */
    public function createDomain(Domain $domain): void;

    /**
     * Updates an existing domain's settings.
     */
    public function updateDomain(Domain $domain): void;

    /**
     * Deletes a domain and records mailboxes for deferred deletion.
     *
     * @param string|null $deleteDate the day (Y-m-d) the mailboxes are removed from disk, null to keep them
     */
    public function deleteDomain(string $domainName, string $adminEmail, ?string $deleteDate = null): void;

    /**
     * Enables or disables a domain.
     */
    public function enableDisableDomain(string $domainName, bool $active): void;

    /**
     * Returns the total used quota for a domain in MB.
     */
    public function getDomainQuotaUsage(string $domainName): int;

    /**
     * Whether the backend stores a total quota per domain. iRedMail's LDAP backend has none.
     */
    public function supportsDomainQuota(): bool;
}
