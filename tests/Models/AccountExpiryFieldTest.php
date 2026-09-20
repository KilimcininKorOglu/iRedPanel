<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Exceptions\InvalidInputException;
use App\Models\Admin;
use App\Models\Domain;
use App\Models\User;
use App\Utils\ExpiryDate;
use PHPUnit\Framework\TestCase;

/**
 * The expiry date field of the mailbox, the domain and the admin model.
 */
class AccountExpiryFieldTest extends TestCase
{
    public function testAFormWithoutADateMeansNoExpiry(): void
    {
        $this->assertSame('', User::fromFormData(['uid' => 'u'])->expiredDate);
        $this->assertSame('', Domain::fromFormData(['domainName' => 'test.com'])->expiredDate);
        $this->assertSame('', Admin::fromFormData(['username' => 'a@test.com'])->expiredDate);
    }

    public function testAFormDateReachesEveryModel(): void
    {
        $this->assertSame('2026-12-31', User::fromFormData(['uid' => 'u', 'expiredDate' => '2026-12-31'])->expiredDate);
        $this->assertSame('2026-12-31', Domain::fromFormData(['domainName' => 'test.com', 'expiredDate' => '2026-12-31'])->expiredDate);
        $this->assertSame('2026-12-31', Admin::fromFormData(['username' => 'a@test.com', 'expiredDate' => '2026-12-31'])->expiredDate);
    }

    public function testAnInvalidFormDateIsRefused(): void
    {
        $this->expectException(InvalidInputException::class);
        Domain::fromFormData(['domainName' => 'test.com', 'expiredDate' => '31.12.2026']);
    }

    public function testTheStoredSqlValueReachesTheDomainModel(): void
    {
        $domain = Domain::fromMysqlRow(['domain' => 'test.com', 'expired' => '2026-12-31 00:00:00']);
        $this->assertSame('2026-12-31', $domain->expiredDate);

        $never = Domain::fromMysqlRow(['domain' => 'test.com', 'expired' => ExpiryDate::SQL_NONE]);
        $this->assertSame('', $never->expiredDate);
    }

    public function testTheStoredSqlValueReachesTheAdminModel(): void
    {
        $admin = Admin::fromMysqlRow(['username' => 'a@test.com', 'expired' => '2026-12-31 00:00:00']);
        $this->assertSame('2026-12-31', $admin->expiredDate);
    }

    public function testTheStoredLdapValueReachesTheDomainAndAdminModel(): void
    {
        $domain = Domain::fromLdapEntry(['domainName' => 'test.com', 'expiredDate' => '20261231000000Z']);
        $this->assertSame('2026-12-31', $domain->expiredDate);

        $admin = Admin::fromLdapEntry(['mail' => 'a@test.com', 'expiredDate' => '20261231000000Z']);
        $this->assertSame('2026-12-31', $admin->expiredDate);
    }

    public function testTheGeneralDomainFormChangesTheStoredDate(): void
    {
        $stored = new Domain(domainName: 'test.com', expiredDate: '2026-12-31');
        $stored->applyProfile(Domain::fromFormData(['domainName' => 'test.com', 'expiredDate' => '2027-06-30']));

        $this->assertSame('2027-06-30', $stored->expiredDate);
    }
}
