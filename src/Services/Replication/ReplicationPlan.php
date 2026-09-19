<?php

declare(strict_types=1);

namespace App\Services\Replication;

/**
 * The actions of one replication run.
 */
final class ReplicationPlan
{
    /**
     * @param ReplicationAction[] $actions
     * @param int $heldBack the disable actions that the mass-disable guard removed
     */
    public function __construct(
        public readonly array $actions,
        public readonly int $heldBack = 0,
    ) {}
}
