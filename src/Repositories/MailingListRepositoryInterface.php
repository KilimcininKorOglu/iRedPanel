<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MailingList;
use App\Models\PaginatedResult;

interface MailingListRepositoryInterface
{
    public function getMailingListsPaginated(int $page, int $perPage, ?string $domain = null): PaginatedResult;

    public function getMailingList(string $address): ?MailingList;

    public function createMailingList(string $address, string $domain, string $name,
                                     string $accessPolicy, int $maxMsgSize): bool;

    public function updateMailingList(string $address, string $name, string $accessPolicy,
                                     int $maxMsgSize, bool $active): bool;

    public function deleteMailingList(string $address): bool;

    /** @return string[] */
    public function getOwners(string $address): array;

    /** @param string[] $owners */
    public function setOwners(string $address, array $owners): bool;

    public function enableDisableMailingList(string $address, bool $active): bool;
}
