<?php

declare(strict_types=1);

namespace App\Repositories;

interface IredapdRepositoryInterface extends AccountSettingsStoreInterface
{
    public function getThrottleSettings(string $account): array;
    public function setThrottleSettings(string $account, string $kind, int $period, int $maxMsgs, int $maxQuota, int $msgSize): void;

    /** @return string[] Every account that has at least one throttle row */
    public function getThrottleAccounts(): array;

    /** Deletes every throttle row of the account and returns the number of deleted rows. */
    public function deleteThrottleSettings(string $account): int;
    public function getGreylistSettings(string $account): array;
    public function setGreylistEnabled(string $account, bool $enabled): void;

    /**
     * Removes the account's own greylisting setting, so iRedAPD applies the
     * setting of the domain or the global '@.' account.
     */
    public function removeGreylistSetting(string $account): void;

    /**
     * Every account that has an own greylisting setting.
     *
     * @return list<array{account: string, active: bool}>
     */
    public function getGreylistAccounts(): array;

    /**
     * Removes the greylisting setting of the account and its whitelisted senders,
     * and returns the number of removed rows.
     */
    public function deleteGreylistSettings(string $account): int;

    /** @return string[] */
    public function getWhitelistedSenders(string $account): array;
    /** @param string[] $senders */
    public function setWhitelistedSenders(string $account, array $senders): void;

    /**
     * @param list<string> $senders
     * @return int the number of senders that the account did not have yet
     */
    public function addWhitelistedSenders(string $account, array $senders): int;

    /**
     * @param list<string> $senders
     * @return int the number of removed rows
     */
    public function removeWhitelistedSenders(string $account, array $senders): int;

    /**
     * The domains whose SPF records the iRedAPD job resolves into whitelisted senders.
     *
     * @return list<string>
     */
    public function getGreylistWhitelistDomains(): array;

    /** @param list<string> $domains */
    public function setGreylistWhitelistDomains(array $domains): void;

    /**
     * The resolved SPF senders, keyed by the domain they come from.
     *
     * @return array<string, list<string>>
     */
    public function getGreylistSpfSenders(): array;

    public function getGreylistTrackingPaginated(int $page, int $perPage): \App\Models\PaginatedResult;

    /** @return array{whitelists: string[], blacklists: string[]} */
    public function getWblistRdns(): array;

    /** @param string[] $whitelists @param string[] $blacklists */
    public function setWblistRdns(array $whitelists, array $blacklists): void;

    /** @return string[] Whitelisted IP addresses */
    public function getSenderScoreWhitelist(): array;

    /** @param string[] $ips */
    public function setSenderScoreWhitelist(array $ips): void;
}
