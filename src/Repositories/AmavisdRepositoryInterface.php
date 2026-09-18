<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PaginatedResult;

interface AmavisdRepositoryInterface
{
    public function getQuarantinedMessages(int $page, int $perPage, ?string $domain = null): PaginatedResult;
    public function releaseMessage(string $mailId, string $requestedBy): void;
    public function deleteQuarantinedMessage(string $mailId): void;
    public function getMailLog(int $page, int $perPage, ?string $email = null): PaginatedResult;
    public function cleanupQuarantined(int $olderThanDays): int;
    public function cleanupMailLog(int $olderThanDays): int;
}
