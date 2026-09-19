<?php

declare(strict_types=1);

namespace App\Services\Replication;

use App\Models\AccountResource;

/**
 * The outcome of one replication run.
 */
final class RunResult
{
    /** @var array<string, int> action => number of events */
    public array $counts = [];

    /** The disable actions that the mass-disable guard held back. */
    public int $heldBack = 0;

    private string $failure = '';

    public function __construct(public readonly int $runId) {}

    public function count(string $action): void
    {
        $this->counts[$action] = ($this->counts[$action] ?? 0) + 1;
    }

    public function fail(string $reason): void
    {
        $this->failure = $reason;
    }

    public function status(): string
    {
        if ($this->failure !== '') {
            return AccountResource::STATUS_FAILED;
        }
        if ($this->heldBack > 0 || ($this->counts[ReplicationAction::ERROR] ?? 0) > 0) {
            return AccountResource::STATUS_PARTIAL;
        }

        return AccountResource::STATUS_OK;
    }

    /**
     * The run message: the failure reason, or the number of held back disables.
     */
    public function message(): string
    {
        if ($this->failure !== '') {
            return $this->failure;
        }

        return $this->heldBack > 0 ? "held_back:{$this->heldBack}" : '';
    }
}
