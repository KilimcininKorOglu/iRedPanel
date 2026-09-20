<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Exceptions\InvalidInputException;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserBirthdayTest extends TestCase
{
    public function testEmptyValueIsAccepted(): void
    {
        $this->assertSame('', User::validBirthday(''));
        $this->assertSame('', User::validBirthday(null));
    }

    public function testValidDateIsKept(): void
    {
        $this->assertSame('1990-02-28', User::validBirthday('1990-02-28'));
        $this->assertSame('2024-02-29', User::validBirthday('2024-02-29'));
    }

    public function testDateThatDoesNotExistIsRejected(): void
    {
        $this->expectException(InvalidInputException::class);
        User::validBirthday('2023-02-29');
    }

    public function testOtherFormatIsRejected(): void
    {
        $this->expectException(InvalidInputException::class);
        User::validBirthday('28.02.1990');
    }

    public function testColumnDefaultReadsAsNotSet(): void
    {
        $this->assertSame('', User::birthdayFromSql(User::EMPTY_BIRTHDAY));
        $this->assertSame('', User::birthdayFromSql(null));
        $this->assertSame('1990-02-28', User::birthdayFromSql('1990-02-28'));
    }

    public function testNotSetWritesTheColumnDefault(): void
    {
        $this->assertSame(User::EMPTY_BIRTHDAY, (new User('john'))->birthdaySql());
        $this->assertSame('1990-02-28', (new User('john', birthday: '1990-02-28'))->birthdaySql());
    }
}
