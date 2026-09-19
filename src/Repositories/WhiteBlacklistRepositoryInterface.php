<?php

declare(strict_types=1);

namespace App\Repositories;

interface WhiteBlacklistRepositoryInterface
{
    /** @return array<int, array{sender: string, wb: string}> */
    public function getInboundList(string $account): array;

    public function addInboundEntry(string $account, string $sender, string $wb): bool;

    /** @return bool false when the account has no inbound entry for the sender */
    public function removeInboundEntry(string $account, string $sender): bool;

    /** @return array<int, array{recipient: string, wb: string}> */
    public function getOutboundList(string $account): array;

    public function addOutboundEntry(string $account, string $recipient, string $wb): bool;

    /** @return bool false when the account has no outbound entry for the recipient */
    public function removeOutboundEntry(string $account, string $recipient): bool;

    /**
     * Removes every inbound entry of the account.
     *
     * @param ?string $wb W or B to remove only that kind, null for every entry
     * @return int the number of removed entries
     */
    public function removeAllInboundEntries(string $account, ?string $wb = null): int;

    /**
     * Removes every outbound entry of the account.
     *
     * @param ?string $wb W or B to remove only that kind, null for every entry
     * @return int the number of removed entries
     */
    public function removeAllOutboundEntries(string $account, ?string $wb = null): int;

    public function getOrCreateUserId(string $email): int;

    public function getOrCreateMailaddrId(string $email): int;
}
