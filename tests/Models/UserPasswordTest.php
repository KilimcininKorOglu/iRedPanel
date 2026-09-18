<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\DomainSettings;
use App\Models\UserPassword;
use PHPUnit\Framework\TestCase;

class UserPasswordTest extends TestCase
{
    public function testValidPasswordReturnsNoErrors(): void
    {
        $errors = UserPassword::validate('Test1234!', 'Test1234!');
        $this->assertEmpty($errors);
    }

    public function testPasswordTooShort(): void
    {
        $errors = UserPassword::validate('Te1!', 'Te1!');
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('at least', $errors['password']);
    }

    public function testPasswordMissingDigit(): void
    {
        $errors = UserPassword::validate('TestPass!', 'TestPass!');
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('digit', $errors['password']);
    }

    public function testPasswordMissingUppercase(): void
    {
        $errors = UserPassword::validate('testpass1!', 'testpass1!');
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('uppercase', $errors['password']);
    }

    public function testPasswordMissingLowercase(): void
    {
        $errors = UserPassword::validate('TESTPASS1!', 'TESTPASS1!');
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('lowercase', $errors['password']);
    }

    public function testPasswordMissingSpecialChar(): void
    {
        $errors = UserPassword::validate('TestPass1', 'TestPass1');
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('special', $errors['password']);
    }

    public function testPasswordMismatch(): void
    {
        $errors = UserPassword::validate('Test1234!', 'Test1234@');
        $this->assertArrayHasKey('password_repeat', $errors);
        $this->assertStringContainsString('do not match', $errors['password_repeat']);
    }

    public function testNonAsciiCharacterRejected(): void
    {
        $errors = UserPassword::validate("Test1234!\xC3\xBC", "Test1234!\xC3\xBC");
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('ASCII', $errors['password']);
    }

    /**
     * A domain minimum length replaces the global minimum of 8.
     */
    public function testDomainMinimumLengthOverridesGlobalMinimum(): void
    {
        $domainSettings = new DomainSettings(minPasswordLength: 12);

        $errors = UserPassword::validate('Test1234!', 'Test1234!', $domainSettings);
        $this->assertStringContainsString('at least 12', $errors['password']);

        $this->assertEmpty(UserPassword::validate('Test1234!abc', 'Test1234!abc', $domainSettings));
    }

    public function testDomainMaximumLengthIsEnforced(): void
    {
        $errors = UserPassword::validate('Test1234!abc', 'Test1234!abc', new DomainSettings(maxPasswordLength: 10));

        $this->assertStringContainsString('at most 10', $errors['password']);
    }

    public function testZeroDomainLimitsKeepGlobalRules(): void
    {
        $this->assertEmpty(UserPassword::validate('Test1234!', 'Test1234!', new DomainSettings()));
    }
}
