<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Exceptions\InvalidInputException;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserAllowNetsTest extends TestCase
{
    public function testEmptyValueIsAccepted(): void
    {
        $this->assertSame('', User::validAllowNets(''));
        $this->assertSame('', User::validAllowNets(null));
    }

    public function testAddressesAndRangesAreKept(): void
    {
        $this->assertSame('192.0.2.10,198.51.100.0/24', User::validAllowNets('192.0.2.10, 198.51.100.0/24'));
    }

    public function testDuplicateEntryIsDropped(): void
    {
        $this->assertSame('192.0.2.10', User::validAllowNets('192.0.2.10, 192.0.2.10'));
    }

    public function testHostnameIsRejected(): void
    {
        $this->expectException(InvalidInputException::class);
        User::validAllowNets('mail.example.com');
    }

    public function testEmptyValueWritesNull(): void
    {
        $this->assertNull((new User('john'))->allowNetsSql());
        $this->assertSame('192.0.2.10', (new User('john', allowNets: '192.0.2.10'))->allowNetsSql());
    }
}
