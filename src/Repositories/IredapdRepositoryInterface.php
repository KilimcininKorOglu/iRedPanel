<?php

declare(strict_types=1);

namespace App\Repositories;

interface IredapdRepositoryInterface extends AccountSettingsStoreInterface
{
    public function getThrottleSettings(string $account): array;
    public function setThrottleSettings(string $account, string $kind, int $period, int $maxMsgs, int $maxQuota, int $msgSize): void;
    public function getGreylistSettings(string $account): array;
    public function setGreylistEnabled(string $account, bool $enabled): void;

    /**
     * Removes the account's own greylisting setting, so iRedAPD applies the
     * setting of the domain or the global '@.' account.
     */
    public function removeGreylistSetting(string $account): void;
    /** @return string[] */
    public function getWhitelistedSenders(string $account): array;
    /** @param string[] $senders */
    public function setWhitelistedSenders(string $account, array $senders): void;

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
