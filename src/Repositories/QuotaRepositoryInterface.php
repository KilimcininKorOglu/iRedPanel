<?php

declare(strict_types=1);

namespace App\Repositories;

interface QuotaRepositoryInterface
{
    /**
     * Returns used quota for all users in a domain.
     *
     * @return array<string, array{bytes: int, messages: int}> keyed by email
     */
    public function getDomainUsedQuotas(string $domain): array;

    /**
     * Returns the stored size of one mailbox in bytes, or null when the backend
     * holds no row for it.
     */
    public function getUsedBytes(string $email): ?int;
}
