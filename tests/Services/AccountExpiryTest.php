<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Repositories\ExpiredAccountRepositoryInterface;
use App\Services\AccountExpiry;
use PHPUnit\Framework\TestCase;

/**
 * The dry run of the expiry service. It lists the accounts of the repository and
 * touches no other repository, so it needs no backend.
 */
class AccountExpiryTest extends TestCase
{
    private function service(array $mailboxes, array $domains, array $admins): AccountExpiry
    {
        $repo = new class ($mailboxes, $domains, $admins) implements ExpiredAccountRepositoryInterface {
            public function __construct(
                private readonly array $mailboxes,
                private readonly array $domains,
                private readonly array $admins,
            ) {
            }

            public function expiredMailboxes(?int $now = null): array
            {
                return $this->mailboxes;
            }

            public function expiredDomains(?int $now = null): array
            {
                return $this->domains;
            }

            public function expiredAdmins(?int $now = null): array
            {
                return $this->admins;
            }

            public function mailboxCounts(?int $now = null): array
            {
                return ['expired' => count($this->mailboxes), 'expiring' => 0];
            }
        };

        return new AccountExpiry($repo);
    }

    public function testADryRunListsEveryExpiredAccount(): void
    {
        $service = $this->service(['user@test.com'], ['test.com'], ['admin@other.test']);

        $this->assertSame(
            ['mailboxes' => ['user@test.com'], 'domains' => ['test.com'], 'admins' => ['admin@other.test']],
            $service->disableExpired(true),
        );
    }

    public function testNoExpiredAccountGivesEmptyLists(): void
    {
        $this->assertSame(
            ['mailboxes' => [], 'domains' => [], 'admins' => []],
            $this->service([], [], [])->disableExpired(true),
        );
    }

    public function testAnEmptyListWritesNothingOutsideADryRun(): void
    {
        // Nothing to disable, so no repository of the backend is reached.
        $this->assertSame(
            ['mailboxes' => [], 'domains' => [], 'admins' => []],
            $this->service([], [], [])->disableExpired(false),
        );
    }
}
