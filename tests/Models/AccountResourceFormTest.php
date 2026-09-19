<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Exceptions\InvalidInputException;
use App\Models\AccountResource;
use App\Models\AccountResourceForm;
use PHPUnit\Framework\TestCase;

class AccountResourceFormTest extends TestCase
{
    private const CONNECTION = [
        'domain' => 'Example.com',
        'host' => 'dc1.example.com',
        'port' => '389',
        'tls' => '1',
        'timeout' => '5',
        'baseDn' => 'DC=example,DC=com',
        'bindDn' => 'svc@example.com',
        'bindPassword' => '',
        'userFilter' => AccountResource::DEFAULT_USER_FILTER,
        'groupFilter' => AccountResource::DEFAULT_GROUP_FILTER,
    ];

    public function testConnectionKeepsTheStoredPasswordWhenTheFieldIsEmpty(): void
    {
        $resource = new AccountResource(bindPassword: 'v1:stored');

        $this->assertNull(AccountResourceForm::apply($resource, 'connection', self::CONNECTION));
        $this->assertSame('example.com', $resource->domain);
        $this->assertSame(389, $resource->port);
        $this->assertTrue($resource->tls);
        // An unchecked box posts nothing, so certificate verification is off.
        $this->assertFalse($resource->tlsVerify);
        $this->assertSame('v1:stored', $resource->bindPassword);

        $this->assertSame('new secret', AccountResourceForm::apply($resource, 'connection', ['bindPassword' => 'new secret'] + self::CONNECTION));
    }

    public function testPort636IsAlwaysEncrypted(): void
    {
        $resource = new AccountResource();
        AccountResourceForm::apply($resource, 'connection', ['port' => '636', 'tls' => null] + self::CONNECTION);

        $this->assertTrue($resource->tls);
    }

    public function testRejectsInvalidConnectionFields(): void
    {
        $cases = [
            'resource.host' => ['host' => 'dc1 example'],
            'resource.port' => ['port' => '70000'],
            'resource.timeout' => ['timeout' => '0'],
            'resource.base_dn' => ['baseDn' => 'example'],
            'resource.bind_dn' => ['bindDn' => ''],
            'resource.user_filter' => ['userFilter' => '(objectClass=user'],
            'resource.group_filter' => ['groupFilter' => '(a=1)(b=2)'],
            'resource.target_domain' => ['domain' => 'not a domain'],
        ];
        foreach ($cases as $field => $override) {
            try {
                AccountResourceForm::apply(new AccountResource(), 'connection', $override + self::CONNECTION);
                $this->fail("Accepted {$field}");
            } catch (InvalidInputException $e) {
                $this->assertSame($field, $e->fieldKey);
            }
        }
    }

    public function testUsersTabAcceptsEmptyMappingsAndRejectsInvalidNames(): void
    {
        $resource = new AccountResource();
        AccountResourceForm::apply($resource, 'users', ['userMailAttribute' => 'mail', 'attr_title' => '', 'attr_cn' => 'cn']);

        $this->assertSame('mail', $resource->userMailAttribute);
        $this->assertSame('', $resource->userAttributes['title']);
        $this->assertSame('cn', $resource->userAttributes['cn']);
        $this->assertSame(array_keys(AccountResource::DEFAULT_USER_ATTRIBUTES), array_keys($resource->userAttributes));

        $this->expectException(InvalidInputException::class);
        AccountResourceForm::apply($resource, 'users', ['userMailAttribute' => 'mail', 'attr_cn' => 'cn)(x']);
    }

    public function testGroupsTabChecksTheAccessPolicy(): void
    {
        $resource = new AccountResource();
        AccountResourceForm::apply($resource, 'groups', ['groupMailAttribute' => 'mail', 'groupNameAttribute' => 'cn', 'groupAccessPolicy' => 'membersOnly']);
        $this->assertSame('membersOnly', $resource->groupAccessPolicy);

        $this->expectException(InvalidInputException::class);
        AccountResourceForm::apply($resource, 'groups', ['groupMailAttribute' => 'mail', 'groupAccessPolicy' => 'everyone']);
    }

    public function testReplicationTabLimitsTheInterval(): void
    {
        $resource = new AccountResource();
        AccountResourceForm::apply($resource, 'replication', ['intervalMinutes' => '15', 'replicateGroups' => '1']);
        $this->assertSame(15, $resource->intervalMinutes);
        $this->assertTrue($resource->replicateGroups);

        $this->expectException(InvalidInputException::class);
        AccountResourceForm::apply($resource, 'replication', ['intervalMinutes' => '0']);
    }
}
