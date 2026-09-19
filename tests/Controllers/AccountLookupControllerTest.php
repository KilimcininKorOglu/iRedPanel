<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\AccountLookupController;
use PHPUnit\Framework\TestCase;

class AccountLookupControllerTest extends TestCase
{
    public function testParseTypesKeepsOnlyKnownTypes(): void
    {
        $this->assertSame(['user', 'ml'], AccountLookupController::parseTypes('ml, user,admin,domain'));
    }

    public function testParseTypesFallsBackToAllPickerTypes(): void
    {
        // An empty list must not reach the repository, because [] searches domains and admins too.
        $this->assertSame(['user', 'alias', 'ml'], AccountLookupController::parseTypes(''));
        $this->assertSame(['user', 'alias', 'ml'], AccountLookupController::parseTypes('admin'));
    }

    public function testGlobalAdminSearchesEveryDomainOrTheRequestedOne(): void
    {
        $this->assertSame([], AccountLookupController::scopeDomains(true, [], ''));
        $this->assertSame(['example.com'], AccountLookupController::scopeDomains(true, [], ' Example.COM '));
    }

    public function testDomainAdminIsLimitedToManagedDomains(): void
    {
        $managed = ['a.test', 'b.test'];
        $this->assertSame($managed, AccountLookupController::scopeDomains(false, $managed, ''));
        $this->assertSame(['b.test'], AccountLookupController::scopeDomains(false, $managed, 'b.test'));
    }

    public function testDomainAdminGetsNoScopeForAnotherDomain(): void
    {
        $this->assertNull(AccountLookupController::scopeDomains(false, ['a.test'], 'other.test'));
    }

    public function testDomainAdminWithoutDomainsGetsNoScope(): void
    {
        // [] would mean "every domain" in the search repository, so an admin with no domains must get null.
        $this->assertNull(AccountLookupController::scopeDomains(false, [], ''));
    }

    public function testToOptionsFlattensTypesInRequestedOrder(): void
    {
        $results = [
            'users' => [['username' => 'u@a.test', 'name' => 'User One', 'domain' => 'a.test', 'active' => 1]],
            'aliases' => [['address' => 'al@a.test', 'name' => '', 'domain' => 'a.test', 'active' => 1]],
            'mailingLists' => [['address' => 'list@a.test', 'name' => 'List', 'domain' => 'a.test', 'active' => 1]],
        ];

        $this->assertSame([
            ['value' => 'list@a.test', 'text' => 'List <list@a.test>', 'type' => 'ml'],
            ['value' => 'u@a.test', 'text' => 'User One <u@a.test>', 'type' => 'user'],
        ], AccountLookupController::toOptions($results, ['ml', 'user'], 20));
    }

    public function testToOptionsDropsDuplicatesAndAppliesTheLimit(): void
    {
        $results = [
            'users' => [['username' => 'x@a.test', 'name' => ''], ['username' => 'y@a.test', 'name' => '']],
            'aliases' => [['address' => 'x@a.test', 'name' => 'Dup'], ['address' => 'z@a.test', 'name' => '']],
        ];

        $options = AccountLookupController::toOptions($results, ['user', 'alias'], 2);

        $this->assertSame(['x@a.test', 'y@a.test'], array_column($options, 'value'));
        $this->assertSame('user', $options[0]['type']);
    }
}
