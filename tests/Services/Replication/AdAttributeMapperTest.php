<?php

declare(strict_types=1);

namespace Tests\Services\Replication;

use App\Models\AccountResource;
use App\Services\Directory\DirectoryEntry;
use App\Services\Replication\AdAttributeMapper;
use App\Services\Replication\SourceAccount;
use PHPUnit\Framework\TestCase;

class AdAttributeMapperTest extends TestCase
{
    private function mapper(array $userAttributes = AccountResource::DEFAULT_USER_ATTRIBUTES): AdAttributeMapper
    {
        return new AdAttributeMapper(new AccountResource(domain: 'Example.com', userAttributes: $userAttributes));
    }

    private function user(array $values): DirectoryEntry
    {
        return new DirectoryEntry('CN=Jane,CN=Users,DC=example,DC=com', $values + ['objectGUID' => ["\x01\xab"]]);
    }

    public function testMapsTheAddressAndProfile(): void
    {
        $account = $this->mapper()->user($this->user([
            'userPrincipalName' => ['Jane.Doe@EXAMPLE.com'],
            'displayName' => ['Jane Doe'],
            'givenname' => ['Jane'],
            'userAccountControl' => ['512'],
            'employeeID' => ['E-7'],
        ]));

        $this->assertTrue($account->isUsable());
        $this->assertSame('01ab', $account->guid);
        $this->assertSame('jane.doe@example.com', $account->address);
        $this->assertTrue($account->active);
        $this->assertSame('Jane Doe', $account->profile['cn']);
        $this->assertSame('Jane', $account->profile['givenName']);
        $this->assertSame('E-7', $account->profile['employeeNumber']);
        // A mapped attribute that the entry lacks clears the local value.
        $this->assertSame('', $account->profile['title']);
    }

    public function testTheDisableBitOfUserAccountControlDisablesTheAccount(): void
    {
        // 514 = NORMAL_ACCOUNT | ACCOUNTDISABLE, 66050 = 514 | DONT_EXPIRE_PASSWORD
        foreach (['514' => false, '66050' => false, '512' => true, '66048' => true] as $uac => $active) {
            $account = $this->mapper()->user($this->user(['userPrincipalName' => ['a@example.com'], 'userAccountControl' => [(string) $uac]]));
            $this->assertSame($active, $account->active, (string) $uac);
        }
    }

    public function testAnUnmappedStatusOrProfileFieldIsNotReplicated(): void
    {
        $attributes = ['accountStatus' => '', 'title' => ''] + AccountResource::DEFAULT_USER_ATTRIBUTES;
        $account = $this->mapper($attributes)->user($this->user(['userPrincipalName' => ['a@example.com'], 'title' => ['CEO']]));

        $this->assertNull($account->active);
        $this->assertArrayNotHasKey('title', $account->profile);
    }

    public function testACustomStatusAttributeReadsCommonOffValues(): void
    {
        $attributes = ['accountStatus' => 'employeeType'] + AccountResource::DEFAULT_USER_ATTRIBUTES;
        foreach (['disabled' => false, 'FALSE' => false, 'staff' => true] as $value => $active) {
            $account = $this->mapper($attributes)->user($this->user(['userPrincipalName' => ['a@example.com'], 'employeeType' => [$value]]));
            $this->assertSame($active, $account->active, $value);
        }
    }

    public function testAnAddressOutsideTheTargetDomainIsSkipped(): void
    {
        $cases = [
            SourceAccount::SKIP_NO_ADDRESS => [],
            SourceAccount::SKIP_INVALID_ADDRESS => ['userPrincipalName' => ['not an address']],
            // A subdomain is another domain: EE accepts only the target domain.
            SourceAccount::SKIP_OTHER_DOMAIN => ['userPrincipalName' => ['a@sub.example.com']],
        ];
        foreach ($cases as $reason => $values) {
            $this->assertSame($reason, $this->mapper()->user($this->user($values))->skipReason, $reason);
        }
    }

    public function testMapsAGroup(): void
    {
        $group = $this->mapper()->group(new DirectoryEntry('CN=Sales,DC=example,DC=com', [
            'objectGUID' => ["\xff"],
            'mail' => ['Sales@example.com'],
            'cn' => ['Sales'],
            'member' => ['CN=Jane,CN=Users,DC=example,DC=com'],
        ]));

        $this->assertSame('ff', $group->guid);
        $this->assertSame('sales@example.com', $group->address);
        $this->assertSame('Sales', $group->name);
        $this->assertSame(['CN=Jane,CN=Users,DC=example,DC=com'], $group->memberDns);
    }

    public function testDirectoryEntryReadsARangedAttribute(): void
    {
        $entry = new DirectoryEntry('CN=G', ['member;range=0-1499' => ['a', 'b']]);
        $this->assertSame([['a', 'b'], 1500], $entry->range('member'));

        $last = new DirectoryEntry('CN=G', ['member;range=1500-*' => ['c']]);
        $this->assertSame([['c'], null], $last->range('member'));

        $this->assertNull((new DirectoryEntry('CN=G', ['member' => ['a']]))->range('member'));
        $this->assertSame(['x'], $entry->with('member', ['x'])->all('MEMBER'));
    }

    public function testDirectoryEntryReadsLdapGetEntriesOutput(): void
    {
        $entry = DirectoryEntry::fromLdap([
            'count' => 1,
            0 => 'mail',
            'mail' => ['count' => 2, 0 => 'a@example.com', 1 => 'b@example.com'],
            'dn' => 'CN=A',
        ]);

        $this->assertSame('CN=A', $entry->dn);
        $this->assertSame(['a@example.com', 'b@example.com'], $entry->all('Mail'));
        $this->assertSame('', $entry->first('cn'));
    }
}
