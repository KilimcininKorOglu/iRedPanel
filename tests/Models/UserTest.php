<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Exceptions\InvalidInputException;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testFormQuotaIsReadInMegabytes(): void
    {
        $this->assertSame(250, User::fromFormData(['uid' => 'a', 'mailQuota' => '250'])->mailQuota);
        $this->assertSame(0, User::fromFormData(['uid' => 'a', 'mailQuota' => 0])->mailQuota);
    }

    /**
     * A negative quota used to become 0, which iRedMail treats as unlimited.
     */
    public function testNegativeQuotaIsRejectedInsteadOfBecomingUnlimited(): void
    {
        try {
            User::fromFormData(['uid' => 'a', 'mailQuota' => '-5']);
            $this->fail('A negative quota must be rejected');
        } catch (InvalidInputException $e) {
            $this->assertSame('user.msg_invalid_quota', $e->translationKey);
        }
    }

    public function testTextQuotaIsRejected(): void
    {
        $this->expectException(InvalidInputException::class);
        User::fromFormData(['uid' => 'a', 'mailQuota' => 'abc']);
    }
}
