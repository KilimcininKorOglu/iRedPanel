<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MailList;
use App\Models\PaginatedResult;

/**
 * The mail list repository of a SQL backend, which has no such account type.
 * Every read answers empty and every write refuses, so a caller that skips
 * `isAvailable()` fails loudly instead of writing nothing.
 */
class NullMailListRepository implements MailListRepositoryInterface
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function getMailListsPaginated(?array $domains, int $page, int $perPage, ?bool $activeOnly = null): PaginatedResult
    {
        return new PaginatedResult([], 0, $page, $perPage);
    }

    public function getMailList(string $address): ?MailList
    {
        return null;
    }

    public function createMailList(MailList $list): void
    {
        throw new \RuntimeException('Mail lists need the LDAP backend');
    }

    public function updateMailList(MailList $list): void
    {
        throw new \RuntimeException('Mail lists need the LDAP backend');
    }

    public function deleteMailList(string $address): void
    {
        throw new \RuntimeException('Mail lists need the LDAP backend');
    }

    public function isAddressInUse(string $address): bool
    {
        return false;
    }
}
