<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Exceptions\InvalidInputException;
use App\Models\Domain;
use PHPUnit\Framework\TestCase;

class DomainTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $domain = new Domain(domainName: 'example.com');

        $this->assertSame('example.com', $domain->domainName);
        $this->assertSame('', $domain->description);
        $this->assertTrue($domain->active);
        $this->assertSame(0, $domain->maxQuota);
        $this->assertSame(0, $domain->quota);
        $this->assertSame(0, $domain->mailboxes);
        $this->assertSame(0, $domain->aliases);
        $this->assertSame('dovecot', $domain->transport);
        $this->assertSame(0, $domain->currentUserCount);
    }

    public function testFromFormData(): void
    {
        $post = [
            'domainName' => '  Example.COM  ',
            'description' => 'Test domain',
            'active' => '1',
            'maxQuota' => '1024',
            'mailboxes' => '50',
        ];

        $domain = Domain::fromFormData($post);

        $this->assertSame('example.com', $domain->domainName);
        $this->assertSame('Test domain', $domain->description);
        $this->assertTrue($domain->active);
        $this->assertSame(1024, $domain->maxQuota);
        $this->assertSame(50, $domain->mailboxes);
    }

    public function testFromFormDataActiveNotSet(): void
    {
        $post = ['domainName' => 'test.com'];

        $domain = Domain::fromFormData($post);

        $this->assertFalse($domain->active);
    }

    /**
     * A negative limit used to become 0, which means unlimited.
     */
    public function testNegativeLimitIsRejectedWithItsFieldLabel(): void
    {
        try {
            Domain::fromFormData(['domainName' => 'test.com', 'mailboxes' => '-5']);
            $this->fail('A negative limit must be rejected');
        } catch (InvalidInputException $e) {
            $this->assertSame('domain.max_mailboxes', $e->fieldKey);
            $this->assertSame('mailboxes must be a whole number of 0 or more', $e->getMessage());
        }
    }

    public function testTextLimitIsRejected(): void
    {
        $this->expectException(InvalidInputException::class);
        Domain::validLimit('abc', 'quota');
    }

    public function testValidNames(): void
    {
        $this->assertTrue(Domain::isValidName('example.com'));
        $this->assertTrue(Domain::isValidName('mail-1.example.co.uk'));
        $this->assertFalse(Domain::isValidName('bad domain!'));
        $this->assertFalse(Domain::isValidName('nodot'));
        $this->assertFalse(Domain::isValidName('-lead.example.com'));
        $this->assertFalse(Domain::isValidName('Example.com'));
    }

    public function testFromMysqlRow(): void
    {
        $row = [
            'domain' => 'mysql.test',
            'description' => 'MySQL domain',
            'active' => 1,
            'maxquota' => 2048,
            'quota' => 1024,
            'mailboxes' => 100,
            'aliases' => 50,
            'transport' => 'lmtp',
            'settings' => 'key:val;',
            'created' => '2026-01-01',
            'modified' => '2026-03-01',
            'userCount' => 25,
            'quotaUsed' => 500,
        ];

        $domain = Domain::fromMysqlRow($row);

        $this->assertSame('mysql.test', $domain->domainName);
        $this->assertSame('MySQL domain', $domain->description);
        $this->assertTrue($domain->active);
        $this->assertSame(2048, $domain->maxQuota);
        $this->assertSame(1024, $domain->quota);
        $this->assertSame(100, $domain->mailboxes);
        $this->assertSame(50, $domain->aliases);
        $this->assertSame('lmtp', $domain->transport);
        $this->assertSame(25, $domain->currentUserCount);
        $this->assertSame(500, $domain->currentQuotaUsed);
    }

    /**
     * The settings string splits on ';' and ':', so a disclaimer stored there came back truncated.
     */
    public function testDisclaimerColumnKeepsSeparators(): void
    {
        $text = 'Part one; part two: max_passwd_length:3';
        $domain = Domain::fromMysqlRow(['domain' => 'd.test', 'disclaimer' => $text, 'settings' => 'disclaimer:old;']);

        $this->assertSame($text, $domain->disclaimer);
    }

    public function testLegacyDisclaimerIsReadFromSettings(): void
    {
        $domain = Domain::fromMysqlRow(['domain' => 'd.test', 'disclaimer' => null, 'settings' => 'disclaimer:Legacy text;']);

        $this->assertSame('Legacy text', $domain->disclaimer);
    }

    /**
     * Saving the general tab used to erase the settings string.
     */
    public function testApplyProfileKeepsSettingsAndDisclaimer(): void
    {
        $stored = new Domain(domainName: 'd.test', settings: 'default_user_quota:128;', disclaimer: 'Kept');
        $form = Domain::fromFormData(['domainName' => 'd.test', 'description' => 'New', 'active' => '1', 'mailboxes' => '7']);

        $stored->applyProfile($form);

        $this->assertSame('New', $stored->description);
        $this->assertSame(7, $stored->mailboxes);
        $this->assertSame('default_user_quota:128;', $stored->settings);
        $this->assertSame('Kept', $stored->disclaimer);
    }

    public function testFromMysqlRowInactive(): void
    {
        $row = ['domain' => 'off.test', 'active' => 0];

        $domain = Domain::fromMysqlRow($row);

        $this->assertFalse($domain->active);
    }

    public function testFromLdapEntry(): void
    {
        $entry = [
            'domainName' => 'ldap.test',
            'cn' => 'LDAP domain',
            'accountStatus' => 'active',
            'domainCurrentUserNumber' => '15',
        ];

        $domain = Domain::fromLdapEntry($entry);

        $this->assertSame('ldap.test', $domain->domainName);
        $this->assertSame('LDAP domain', $domain->description);
        $this->assertTrue($domain->active);
        $this->assertSame(15, $domain->currentUserCount);
    }

    public function testFromLdapEntryDisabled(): void
    {
        $entry = [
            'domainName' => 'disabled.test',
            'accountStatus' => 'disabled',
        ];

        $domain = Domain::fromLdapEntry($entry);

        $this->assertFalse($domain->active);
    }
}
