<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Api\ApiInput;
use PHPUnit\Framework\TestCase;

class ApiInputTest extends TestCase
{
    public function testAddressIsLowercasedAndNullClears(): void
    {
        $this->assertSame('a@example.com', ApiInput::address(['bcc' => ' A@Example.com '], 'bcc'));
        // A client removes the BCC with null or an empty string.
        $this->assertNull(ApiInput::address(['bcc' => null], 'bcc'));
        $this->assertNull(ApiInput::address(['bcc' => ''], 'bcc'));
    }

    public function testAddressRejectsTextAndNonStrings(): void
    {
        foreach (['not-an-address', 12, ['a@example.com']] as $value) {
            try {
                ApiInput::address(['bcc' => $value], 'bcc');
                $this->fail('Accepted ' . var_export($value, true));
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('bcc', $e->getMessage());
            }
        }
    }

    public function testAddressesRejectsAStringOrAnObject(): void
    {
        // A comma string would be split by position only, so the API asks for a JSON array.
        $this->expectException(\InvalidArgumentException::class);
        ApiInput::addresses('a@example.com,b@example.com', 'members');
    }

    public function testAddressesNamesTheInvalidAddress(): void
    {
        $this->expectExceptionMessage('Invalid members address: bad');
        ApiInput::addresses(['a@example.com', 'bad'], 'members');
    }

    public function testRelayhostAcceptsPostfixNextHopAndNullRemoves(): void
    {
        $this->assertSame('[192.0.2.1]:25', ApiInput::relayhost(['r' => ' [192.0.2.1]:25 '], 'r'));
        $this->assertNull(ApiInput::relayhost(['r' => null], 'r'));
        $this->expectException(\InvalidArgumentException::class);
        ApiInput::relayhost(['r' => 'not a host'], 'r');
    }

    public function testBoolRejectsAStringValue(): void
    {
        // "false" would cast to true, so only a JSON boolean is accepted.
        $this->assertTrue(ApiInput::bool([], 'x', true));
        $this->assertFalse(ApiInput::bool(['x' => false], 'x', true));
        $this->expectException(\InvalidArgumentException::class);
        ApiInput::bool(['x' => 'false'], 'x', true);
    }

    public function testListChangeReplacesAddsAndRemoves(): void
    {
        $keys = ['members', 'addMember', 'removeMember'];
        $parse = fn(mixed $v, string $f) => ApiInput::addresses($v, $f);
        $current = ['a@example.com', 'b@example.com'];

        $this->assertNull(ApiInput::listChange(['name' => 'x'], $keys, $current, $parse));
        $this->assertSame(['c@example.com'], ApiInput::listChange(['members' => ['C@example.com']], $keys, $current, $parse));
        $this->assertSame(
            ['a@example.com', 'c@example.com'],
            ApiInput::listChange(['addMember' => ['c@example.com', 'a@example.com'], 'removeMember' => ['b@example.com']], $keys, $current, $parse),
        );
        $this->assertSame([], ApiInput::listChange(['members' => []], $keys, $current, $parse));
    }

    public function testListChangeRejectsReplaceWithAddOrRemove(): void
    {
        // Both forms together have no defined order, so the request is refused.
        $this->expectExceptionMessage('members conflicts with addMember and removeMember');
        ApiInput::listChange(
            ['members' => [], 'addMember' => ['a@example.com']],
            ['members', 'addMember', 'removeMember'],
            [],
            fn(mixed $v, string $f) => ApiInput::addresses($v, $f),
        );
    }
}
