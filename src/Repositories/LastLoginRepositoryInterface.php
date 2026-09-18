<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PaginatedResult;

interface LastLoginRepositoryInterface
{
    /** @return array{imap: ?string, pop3: ?string, lda: ?string}|null */
    public function getLastLogin(string $username): ?array;

    public function getLastLoginsPaginated(int $page, int $perPage, ?string $domain = null): PaginatedResult;
}
