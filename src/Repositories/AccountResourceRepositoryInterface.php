<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AccountResource;
use App\Models\ReplicatedAccount;

/**
 * Account resources, the directory objects they replicate, and the replication log.
 * The tables live in the iredadmin database.
 */
interface AccountResourceRepositoryInterface
{
    public function isAvailable(): bool;

    public function ensureTablesExist(): void;

    /** @return AccountResource[] */
    public function all(): array;

    public function find(int $id): ?AccountResource;

    /**
     * Inserts a resource with id 0, updates it otherwise.
     *
     * @return int the resource id
     */
    public function save(AccountResource $resource): int;

    /**
     * Deletes the resource, its object links and its log. Local accounts stay.
     */
    public function delete(int $id): void;

    public function setEnabled(int $id, bool $enabled): void;

    public function recordRun(int $id, int $at, string $status): void;

    /** @return array<string, ReplicatedAccount> keyed by GUID */
    public function replicatedAccounts(int $resourceId): array;

    public function saveReplicatedAccount(int $resourceId, ReplicatedAccount $account): void;

    public function deleteReplicatedAccount(int $resourceId, string $guid): void;

    /**
     * Returns the link of the local account with this address, if a resource owns it.
     */
    public function findOwner(string $address): ?ReplicatedAccount;

    /** @return int the run id */
    public function startRun(int $resourceId, int $at): int;

    /**
     * @param array<string, int> $counts action => number of events
     */
    public function finishRun(int $runId, int $at, string $status, array $counts, string $message): void;

    public function addEvent(int $runId, string $action, string $kind, string $address, string $detail): void;

    /**
     * Deletes the oldest runs of a resource beyond $keep, with their events.
     */
    public function pruneRuns(int $resourceId, int $keep): void;

    /** @return array<int, array<string, mixed>> newest first */
    public function runs(int $resourceId, int $limit, int $offset): array;

    public function countRuns(int $resourceId): int;

    /** @return array<int, array<string, mixed>> */
    public function events(int $runId): array;
}
