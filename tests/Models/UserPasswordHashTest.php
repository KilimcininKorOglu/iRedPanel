<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Exceptions\InvalidInputException;
use App\Models\UserPassword;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserPasswordHashTest extends TestCase
{
    public function testSupportedSchemeIsAccepted(): void
    {
        $this->assertSame('{SSHA512}abc=', UserPassword::acceptedHash('{SSHA512}abc=', ''));
        $this->assertSame('{sha512-crypt}$6$x', UserPassword::acceptedHash('{sha512-crypt}$6$x', ''));
    }

    /**
     * The admin gives either the password or its hash.
     */
    public function testPasswordAndHashTogetherAreRefused(): void
    {
        try {
            UserPassword::acceptedHash('{SSHA512}abc=', 'Test1234!');
            $this->fail('a password and a hash must be refused together');
        } catch (InvalidInputException $e) {
            $this->assertSame('user.msg_password_or_hash', $e->translationKey);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHashes(): array
    {
        return [
            'no scheme' => ['abc='],
            'unknown scheme' => ['{FOO}abc='],
            'scheme only' => ['{SSHA512}'],
            'whitespace' => ['{SSHA512}ab c='],
            'line break' => ["{SSHA512}abc=\nuid: x"],
        ];
    }

    #[DataProvider('invalidHashes')]
    public function testInvalidHashIsRefused(string $hash): void
    {
        try {
            UserPassword::acceptedHash($hash, '');
            $this->fail('the hash must be refused');
        } catch (InvalidInputException $e) {
            $this->assertSame('user.msg_invalid_password_hash', $e->translationKey);
        }
    }
}
