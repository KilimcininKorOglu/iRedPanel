<?php

declare(strict_types=1);

namespace App\Services\Replication;

use App\Models\AccountResource;
use App\Models\ReplicatedAccount;
use App\Repositories\AccountResourceRepositoryInterface;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\Services\Directory\DirectoryClient;
use App\Utils\SecretBox;

/**
 * Runs one replication of an account resource: reads the directory, plans the
 * actions, applies them, and writes the run and its events to the log.
 */
final class ReplicationRunner
{
    /** Runs kept per resource in the log. */
    public const KEEP_RUNS = 200;

    /** Actions that change a local account and go to the activity log. */
    private const LOGGED_ACTIONS = [
        ReplicationAction::CREATE, ReplicationAction::UPDATE, ReplicationAction::RENAME,
        ReplicationAction::DISABLE, ReplicationAction::ENABLE,
    ];

    public function __construct(private readonly AccountResourceRepositoryInterface $store) {}

    public static function create(): self
    {
        return new self(RepositoryFactory::getAccountResourceRepository());
    }

    /**
     * Reads the directory and returns the actions of a run without applying them.
     */
    public function plan(AccountResource $resource, bool $allowMassDisable = false): ReplicationPlan
    {
        $client = DirectoryClient::connect($resource, SecretBox::fromSettings()->decrypt($resource->bindPassword));
        $mapper = new AdAttributeMapper($resource);
        $users = array_map(
            $mapper->user(...),
            $client->search($resource->baseDn, $resource->userFilter, $resource->userSearchAttributes()),
        );
        $groups = null;
        if ($resource->replicateGroups) {
            $groups = array_map(
                $mapper->group(...),
                $client->search($resource->baseDn, $resource->groupFilter, $resource->groupSearchAttributes(), rangedAttribute: 'member'),
            );
        }
        $aliases = RepositoryFactory::getAliasRepository();
        $planner = new ReplicationPlanner(static fn(string $address): bool => $aliases->isAddressInUse($address), $allowMassDisable);

        return $planner->plan($users, $groups, $this->store->replicatedAccounts($resource->id));
    }

    /**
     * Runs one replication while holding the lock of the resource.
     *
     * @return ?RunResult null when another run of the resource holds the lock
     */
    public function run(AccountResource $resource, bool $allowMassDisable = false): ?RunResult
    {
        if (!$this->store->tryLock($resource->id)) {
            return null;
        }
        try {
            return $this->runLocked($resource, $allowMassDisable);
        } finally {
            $this->store->unlock($resource->id);
        }
    }

    private function runLocked(AccountResource $resource, bool $allowMassDisable): RunResult
    {
        $started = time();
        $runId = $this->store->startRun($resource->id, $started);
        $result = new RunResult($runId);
        try {
            $plan = $this->plan($resource, $allowMassDisable);
            $writer = new LocalAccountWriter(
                $resource,
                RepositoryFactory::getUserRepository(),
                RepositoryFactory::getAliasRepository(),
                RepositoryFactory::getDomainRepository(),
            );
            foreach ($plan->actions as $action) {
                $this->apply($resource, $writer, $action, $result);
            }
            $result->heldBack = $plan->heldBack;
        } catch (\Throwable $e) {
            // The run is logged as failed; the admin reads the reason in the replication log.
            $result->fail($e->getMessage());
        }
        $this->store->finishRun($runId, time(), $result->status(), $result->counts, $result->message());
        $this->store->recordRun($resource->id, $started, $result->status());
        $this->store->pruneRuns($resource->id, self::KEEP_RUNS);

        return $result;
    }

    private function apply(AccountResource $resource, LocalAccountWriter $writer, ReplicationAction $action, RunResult $result): void
    {
        try {
            $detail = $this->applyToAccount($writer, $action);
            $this->storeLink($resource->id, $action);
        } catch (\Throwable $e) {
            $this->store->addEvent($result->runId, ReplicationAction::ERROR, $action->kind, $action->address(), "{$action->type}: {$e->getMessage()}");
            $result->count(ReplicationAction::ERROR);
            return;
        }
        if ($action->type === ReplicationAction::FORGET) {
            return;
        }
        $this->store->addEvent($result->runId, $action->type, $action->kind, $action->address(), $detail);
        $result->count($action->type);
        if (in_array($action->type, self::LOGGED_ACTIONS, true)) {
            ActivityLogger::log(
                // iRedAdmin logs a rename as an update.
                $action->type === ReplicationAction::RENAME ? 'update' : $action->type,
                $resource->domain,
                $action->address(),
                "Account resource #{$resource->id} replicated {$action->kind} {$action->address()}: {$action->type}",
            );
        }
    }

    /**
     * @return string the event detail
     */
    private function applyToAccount(LocalAccountWriter $writer, ReplicationAction $action): string
    {
        switch ($action->type) {
            case ReplicationAction::CREATE:
            case ReplicationAction::UPDATE:
            case ReplicationAction::ENABLE:
                return trim($writer->write($action) . ' ' . $action->detail);
            case ReplicationAction::RENAME:
                $writer->rename($action);
                return trim("from {$action->link->address} {$writer->write($action)} {$action->detail}");
            case ReplicationAction::DISABLE:
                $writer->disable($action->link);
                return $action->detail;
            default:
                return $action->detail;
        }
    }

    private function storeLink(int $resourceId, ReplicationAction $action): void
    {
        if ($action->type === ReplicationAction::FORGET) {
            $this->store->deleteReplicatedAccount($resourceId, $action->guid);
            return;
        }
        $this->store->saveReplicatedAccount($resourceId, self::linkAfter($action));
    }

    /**
     * The stored link after an action. A conflict of an owned account keeps its address and state.
     */
    private static function linkAfter(ReplicationAction $action): ReplicatedAccount
    {
        $link = $action->link;
        $owned = $link !== null && $link->ownsAccount();
        [$state, $address] = match ($action->type) {
            ReplicationAction::DISABLE => [ReplicatedAccount::STATE_MISSING, $link->address],
            ReplicationAction::SKIP => [ReplicatedAccount::STATE_SKIPPED, $action->address()],
            ReplicationAction::CONFLICT => $owned ? [$link->state, $link->address] : [ReplicatedAccount::STATE_CONFLICT, $action->address()],
            default => [ReplicatedAccount::STATE_ACTIVE, $action->address()],
        };

        return new ReplicatedAccount(
            guid: $action->guid,
            kind: $action->kind,
            address: $address,
            sourceDn: $action->source?->dn ?? $link->sourceDn,
            fingerprint: $action->fingerprint,
            state: $state,
            lastSeenAt: time(),
        );
    }
}
