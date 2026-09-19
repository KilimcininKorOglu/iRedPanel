<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\Admin;
use App\Services\AdminLimits;
use PHPUnit\Framework\TestCase;

class AdminLimitsTest extends TestCase
{
    private const COUNTS = ['domains' => 2, 'users' => 5, 'aliases' => 3, 'lists' => 1, 'quotaMb' => 900];

    public function testReachedCountLimitIsReported(): void
    {
        $admin = new Admin(username: 'a@test.com', createMaxUsers: 5, createMaxAliases: 4);

        $error = AdminLimits::countError($admin, self::COUNTS, 'users');
        $this->assertSame('user.msg_creation_limit', $error?->translationKey);
        $this->assertSame(['limit' => 5], $error->params);
        $this->assertNull(AdminLimits::countError($admin, self::COUNTS, 'aliases'));
    }

    public function testUnlimitedAndGlobalAdminsAreNotBound(): void
    {
        $this->assertNull(AdminLimits::countError(new Admin(username: 'a@test.com'), self::COUNTS, 'domains'));
        $global = new Admin(username: 'g@test.com', isGlobalAdmin: true, createMaxDomains: 0, createMaxQuota: 0);
        $this->assertNull(AdminLimits::countError($global, self::COUNTS, 'domains'));
        $this->assertNull(AdminLimits::quotaError($global, 900, 0, 500));
    }

    /**
     * A zero limit allows nothing, as in iRedAdmin-Pro.
     */
    public function testZeroLimitAllowsNothing(): void
    {
        $admin = new Admin(username: 'a@test.com', createMaxLists: 0);

        $this->assertSame('mlist.msg_creation_limit', AdminLimits::countError($admin, ['lists' => 0], 'lists')?->translationKey);
    }

    public function testQuotaCountsTheSumOfTheMailboxes(): void
    {
        $admin = new Admin(username: 'a@test.com', createMaxQuota: 1000);

        $this->assertNull(AdminLimits::quotaError($admin, 900, 0, 100));
        $this->assertSame('user.msg_admin_quota_limit', AdminLimits::quotaError($admin, 900, 0, 101)?->translationKey);
        // A change counts only the growth of the mailbox.
        $this->assertNull(AdminLimits::quotaError($admin, 900, 200, 300));
        $this->assertNotNull(AdminLimits::quotaError($admin, 900, 200, 301));
    }

    public function testQuotaLimitRefusesUnlimitedAndAllowsALowerQuota(): void
    {
        $admin = new Admin(username: 'a@test.com', createMaxQuota: 1000);

        $this->assertSame('user.msg_admin_quota_unlimited', AdminLimits::quotaError($admin, 0, 0, 0)?->translationKey);
        // Lowering a quota is allowed even when the admin is already over the limit.
        $this->assertNull(AdminLimits::quotaError($admin, 1500, 500, 400));
    }
}
