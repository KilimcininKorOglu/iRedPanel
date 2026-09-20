<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Lists the accounts whose expiry date has passed and that are still active. No
 * iRedMail component reads the expiry date, so the panel enforces it itself.
 */
interface ExpiredAccountRepositoryInterface
{
    /**
     * @param ?int $now unix timestamp, the current time when null
     * @return list<string> the addresses of the expired mailboxes
     */
    public function expiredMailboxes(?int $now = null): array;

    /**
     * @param ?int $now unix timestamp, the current time when null
     * @return list<string> the names of the expired domains
     */
    public function expiredDomains(?int $now = null): array;

    /**
     * @param ?int $now unix timestamp, the current time when null
     * @return list<string> the addresses of the expired standalone admins
     */
    public function expiredAdmins(?int $now = null): array;

    /**
     * The mailbox counts of the dashboard card: the mailboxes whose date has passed,
     * whatever their status, and the mailboxes that expire within ExpiryDate::SOON_DAYS.
     *
     * @param ?int $now unix timestamp, the current time when null
     * @return array{expired: int, expiring: int}
     */
    public function mailboxCounts(?int $now = null): array;
}
