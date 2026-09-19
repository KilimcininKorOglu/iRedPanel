<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Exceptions\SecretBoxException;
use App\Utils\SecretBox;
use PHPUnit\Framework\TestCase;

class SecretBoxTest extends TestCase
{
    public function testDecryptReturnsTheEncryptedValue(): void
    {
        $box = SecretBox::fromSecret('panel-secret');
        $stored = $box->encrypt('bind password');

        $this->assertStringStartsWith('v1:', $stored);
        $this->assertStringNotContainsString('bind password', $stored);
        $this->assertSame('bind password', $box->decrypt($stored));
    }

    public function testEachEncryptionUsesANewNonce(): void
    {
        $box = SecretBox::fromSecret('panel-secret');

        $this->assertNotSame($box->encrypt('same'), $box->encrypt('same'));
    }

    public function testAChangedSecretKeyCannotReadTheValue(): void
    {
        // The admin must enter the bind password again after the secret key changes.
        $stored = SecretBox::fromSecret('old-secret')->encrypt('bind password');

        $this->expectException(SecretBoxException::class);
        SecretBox::fromSecret('new-secret')->decrypt($stored);
    }

    public function testATamperedValueIsRejected(): void
    {
        $box = SecretBox::fromSecret('panel-secret');
        $raw = base64_decode(substr($box->encrypt('bind password'), 3), true);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);

        $this->expectException(SecretBoxException::class);
        $box->decrypt('v1:' . base64_encode($raw));
    }

    public function testMalformedValuesAreRejected(): void
    {
        $box = SecretBox::fromSecret('panel-secret');
        foreach (['', 'plaintext', 'v1:not-base64!', 'v1:' . base64_encode('short')] as $stored) {
            try {
                $box->decrypt($stored);
                $this->fail("Accepted {$stored}");
            } catch (SecretBoxException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
