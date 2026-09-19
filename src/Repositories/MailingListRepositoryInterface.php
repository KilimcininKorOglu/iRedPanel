<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MailingList;
use App\Models\PaginatedResult;

interface MailingListRepositoryInterface
{
    public function getMailingListsPaginated(int $page, int $perPage, ?string $domain = null): PaginatedResult;

    public function getMailingList(string $address): ?MailingList;

    /**
     * Finds a list by its server-wide ID (`mlid`), used in public newsletter URLs.
     */
    public function getMailingListById(string $mlid): ?MailingList;

    /**
     * Whether the backend stores the newsletter flag (public subscription pages).
     */
    public function supportsNewsletter(): bool;

    public function setNewsletter(string $address, bool $enabled): void;

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
