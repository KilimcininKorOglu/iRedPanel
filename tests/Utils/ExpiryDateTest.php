<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Exceptions\InvalidInputException;
use App\Utils\ExpiryDate;
use PHPUnit\Framework\TestCase;

/**
 * The account expiry date at the SQL and the LDAP boundary.
 */
class ExpiryDateTest extends TestCase
{
    public function testTheColumnDefaultReadsAsNoExpiry(): void
    {
        $this->assertSame('', ExpiryDate::fromSql(ExpiryDate::SQL_NONE));
        $this->assertSame('', ExpiryDate::fromSql(null));
        $this->assertSame('', ExpiryDate::fromSql(''));
    }

    public function testAStoredDateReadsAsTheDatePart(): void
    {
        $this->assertSame('2026-03-01', ExpiryDate::fromSql('2026-03-01 00:00:00'));
    }

    public function testNoExpiryWritesTheColumnDefault(): void
    {
        $this->assertSame(ExpiryDate::SQL_NONE, ExpiryDate::toSql(''));
        $this->assertSame('2026-03-01 00:00:00', ExpiryDate::toSql('2026-03-01'));
    }

    public function testTheLdapValueIsAGeneralizedTime(): void
    {
        $this->assertSame('20260301000000Z', ExpiryDate::toLdap('2026-03-01'));
        $this->assertNull(ExpiryDate::toLdap(''));
        $this->assertSame('2026-03-01', ExpiryDate::fromLdap('20260301000000Z'));
    }

    public function testAMissingLdapAttributeReadsAsNoExpiry(): void
    {
        $this->assertSame('', ExpiryDate::fromLdap(null));
        $this->assertSame('', ExpiryDate::fromLdap(''));
        $this->assertSame('', ExpiryDate::fromLdap('99991231000000Z'));
    }

    public function testAValidDatePassesAndAnEmptyValueMeansNoExpiry(): void
    {
        $this->assertSame('2026-03-01', ExpiryDate::valid('2026-03-01'));
        $this->assertSame('', ExpiryDate::valid(''));
        $this->assertSame('', ExpiryDate::valid(null));
        $this->assertSame('', ExpiryDate::valid(ExpiryDate::NONE));
    }

    public function testAnInvalidDateIsRefused(): void
    {
        $this->expectException(InvalidInputException::class);
        ExpiryDate::valid('2026-02-30');
    }

    public function testATextValueIsRefused(): void
    {
        $this->expectException(InvalidInputException::class);
        ExpiryDate::valid('next year');
    }

    public function testTheAccountExpiresAtTheEndOfTheStoredDay(): void
    {
        $date = '2026-03-01';
        $this->assertFalse(ExpiryDate::isExpired($date, (int) strtotime('2026-03-01 23:59:59')));
        $this->assertTrue(ExpiryDate::isExpired($date, (int) strtotime('2026-03-02 00:00:00')));
    }

    public function testAnAccountWithoutADateNeverExpires(): void
    {
        $this->assertFalse(ExpiryDate::isExpired('', (int) strtotime('2999-01-01 00:00:00')));
    }
}
