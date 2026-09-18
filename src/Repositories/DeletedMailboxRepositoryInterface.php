<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PaginatedResult;

interface DeletedMailboxRepositoryInterface
{
    /**
     * Returns paginated list of pending mailbox deletions.
     */
    public function getPendingDeletions(int $page, int $perPage): PaginatedResult;

    /**
     * Cancels a pending deletion (removes the record).
     *
     * @return bool false when no record has this id
     */
    public function cancelDeletion(int $id): bool;

    /**
     * Reschedules a pending deletion to a new date (Y-m-d).
     *
     * @return bool false when no record has this id
     */
    public function reschedule(int $id, string $newDate): bool;
}
