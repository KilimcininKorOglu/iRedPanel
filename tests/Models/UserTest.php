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

    /**
     * Dovecot and Postfix accept an LDAP mailbox only with enabledService=mail, and a new
     * mailbox used to get no login service at all because the create form has no toggles.
     */
    public function testNewLdapMailboxGetsMailAndEveryLoginService(): void
    {
        $services = User::defaultLdapServices();

        foreach (['mail', 'deliver', 'smtp', 'smtptls', 'pop3tls', 'imap', 'imaptls', 'sieve', 'sievetls', 'managesieve'] as $value) {
            $this->assertContains($value, $services);
        }
    }

    /**
     * An update used to replace enabledService with a fixed list, which removed `mail`,
     * `shadowaddress` and every value the panel does not manage.
     */
    public function testLdapUpdateKeepsUnmanagedValuesAndAppliesTogglesToTlsValues(): void
    {
        $stored = ['mail', 'deliver', 'shadowaddress', 'domainadmin', 'sogowebmail', 'imap', 'imapsecured', 'imaptls', 'sieve', 'sievetls'];
        $user = User::fromFormData(['uid' => 'a', 'enableImap' => '1']);

        $services = $user->toLdapServiceList($stored);

        $this->assertEqualsCanonicalizing(['mail', 'deliver', 'shadowaddress', 'domainadmin', 'sogowebmail', 'imap'], $services);
    }

    public function testLdapUpdateRestoresMailOnAnEntryWithoutIt(): void
    {
        $this->assertContains('mail', User::fromFormData(['uid' => 'a'])->toLdapServiceList(['deliver']));
    }

    public function testLdapTlsValueShowsAnOpenSecuredLogin(): void
    {
        $user = User::fromLdapEntry(['uid' => 'a', 'enabledService' => ['mail', 'imaptls', 'sieve']]);

        $this->assertTrue($user->enableImapSecured);
        $this->assertTrue($user->enableManagesieve);
        $this->assertFalse($user->enableManagesieveSecured);
        $this->assertFalse($user->enablePop3);
    }

    public function testSecuredToggleShowsAnOpenTlsLogin(): void
    {
        $row = ['enablepop3secured' => 0, 'enablepop3tls' => 1, 'enablesievesecured' => 0, 'enablesievetls' => 0];

        $this->assertTrue(User::anySqlServiceEnabled($row, 'enablepop3secured', 'enablepop3tls'));
        $this->assertFalse(User::anySqlServiceEnabled($row, 'enablesievesecured', 'enablesievetls'));
    }
}
