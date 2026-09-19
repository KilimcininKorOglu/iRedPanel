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

    /**
     * Dovecot checks enablepop3tls for a TLS login and enablesieve* for ManageSieve,
     * so turning off the toggles used to leave those logins open.
     */
    public function testDisabledTogglesAlsoDisableTheColumnsDovecotChecks(): void
    {
        $user = User::fromFormData(['uid' => 'a', 'enablePop3' => '1', 'enableImapSecured' => '1']);

        $this->assertSame([
            'enablePop3Tls' => 0,
            'enableImapTls' => 1,
            'enableSieve' => 0,
            'enableSieveSecured' => 0,
            'enableSieveTls' => 0,
        ], $user->dovecotServiceParams());
    }

    public function testSecuredToggleShowsAnOpenTlsLogin(): void
    {
        $row = ['enablepop3secured' => 0, 'enablepop3tls' => 1, 'enablesievesecured' => 0, 'enablesievetls' => 0];

        $this->assertTrue(User::anySqlServiceEnabled($row, 'enablepop3secured', 'enablepop3tls'));
        $this->assertFalse(User::anySqlServiceEnabled($row, 'enablesievesecured', 'enablesievetls'));
    }
}
