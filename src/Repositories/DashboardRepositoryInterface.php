<?php

declare(strict_types=1);

namespace App\Repositories;

interface DashboardRepositoryInterface
{
    /**
     * Returns dashboard statistics.
     *
     * @return array{
     *   totalDomains: int,
     *   activeDomains: int,
     *   totalUsers: int,
     *   activeUsers: int,
     *   totalAdmins: int,
     *   totalQuotaAllocated: int,
     *   totalQuotaUsed: int,
     *   totalMessages: int,
     *   expiredUsers: int,
     *   expiringUsers: int,
     * }
     */
    public function getStats(): array;
}
