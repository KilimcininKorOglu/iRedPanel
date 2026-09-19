<?php

declare(strict_types=1);

namespace Tests\Services\Replication;

use App\Models\AccountResource;
use App\Models\ReplicatedAccount;
use App\Models\User;
use App\Repositories\AccountResourceRepositoryInterface;
use App\Services\Replication\ReplicatedAccountGuard;
use PHPUnit\Framework\TestCase;

/**
 * The directory owns the mapped fields of a replicated account; a local account stays fully editable.
 */
class ReplicatedAccountGuardTest extends TestCase
{
    protected function setUp(): void
    {
        $attributes = AccountResource::DEFAULT_USER_ATTRIBUTES;
        $attributes['title'] = '';
        $resource = new AccountResource(id: 7, domain: 'x.test', userAttributes: $attributes);

        $repo = $this->createStub(AccountResourceRepositoryInterface::class);
        $repo->method('isAvailable')->willReturn(true);
        $repo->method('find')->willReturnCallback(static fn(int $id): ?AccountResource => $id === 7 ? $resource : null);
        $repo->method('findOwner')->willReturnCallback(static fn(string $address): ?ReplicatedAccount => match ($address) {
            'ad-user@x.test' => new ReplicatedAccount('g1', ReplicatedAccount::KIND_USER, $address, 'CN=u', 'f', ReplicatedAccount::STATE_ACTIVE, 1, 7),
            'ad-team@x.test' => new ReplicatedAccount('g2', ReplicatedAccount::KIND_GROUP, $address, 'CN=t', 'f', ReplicatedAccount::STATE_ACTIVE, 1, 7),
            default => null,
        });
        ReplicatedAccountGuard::useRepository($repo);
    }

    protected function tearDown(): void
    {
        ReplicatedAccountGuard::useRepository(null);
    }

    public function testLocalAccountHasNoLockedFields(): void
    {
        $this->assertNull(ReplicatedAccountGuard::owner('local@x.test'));
        $this->assertSame([], ReplicatedAccountGuard::lockedUserFields('local@x.test'));
        $this->assertFalse(ReplicatedAccountGuard::isGroupLocked('local@x.test'));
        ReplicatedAccountGuard::assertNotReplicated('local@x.test');
    }

    public function testUnmappedFieldStaysEditable(): void
    {
        $locked = ReplicatedAccountGuard::lockedUserFields('AD-User@x.test');

        $this->assertContains('cn', $locked);
        $this->assertContains('accountStatus', $locked);
        $this->assertNotContains('title', $locked);
    }

    public function testKeepUserFieldsRestoresTheDirectoryValues(): void
    {
        $stored = new User('ad-user', accountStatus: true, cn: 'Alice', title: 'Old');
        $submitted = new User('ad-user', accountStatus: false, mailQuota: 500, cn: 'Mallory', title: 'New');

        ReplicatedAccountGuard::keepUserFields('ad-user@x.test', $submitted, $stored);

        $this->assertTrue($submitted->accountStatus);
        $this->assertSame('Alice', $submitted->cn);
        $this->assertSame('New', $submitted->title);
        $this->assertSame(500, $submitted->mailQuota);
    }

    public function testChangedUserFieldsNamesOnlyDirectoryFields(): void
    {
        $stored = new User('ad-user', accountStatus: true, cn: 'Alice');
        $sameValues = new User('ad-user', accountStatus: true, mailQuota: 900, cn: 'Alice', title: 'Any');
        $changed = new User('ad-user', accountStatus: false, cn: 'Mallory');

        $this->assertSame([], ReplicatedAccountGuard::changedUserFields('ad-user@x.test', $sameValues, $stored));
        $this->assertSame(['accountStatus', 'cn'], ReplicatedAccountGuard::changedUserFields('ad-user@x.test', $changed, $stored));
        $this->assertSame([], ReplicatedAccountGuard::changedUserFields('local@x.test', $changed, $stored));
    }

    public function testGroupNameAndMembersAreLocked(): void
    {
        $members = ['a@x.test', 'b@x.test'];

        $this->assertSame([], ReplicatedAccountGuard::changedGroupFields('ad-team@x.test', 'Team', ['B@x.test', 'a@x.test'], 'Team', $members));
        $this->assertSame(['name', 'members'], ReplicatedAccountGuard::changedGroupFields('ad-team@x.test', 'Other', ['a@x.test'], 'Team', $members));
        $this->assertSame([], ReplicatedAccountGuard::changedGroupFields('local@x.test', 'Other', [], 'Team', $members));
    }

    public function testReplicatedAddressCannotBeChangedLocally(): void
    {
        $this->expectException(\RuntimeException::class);
        ReplicatedAccountGuard::assertNotReplicated('ad-team@x.test');
    }
}
