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
     * Postfix relays a backup MX domain through its transport (relay_domains plus
     * transport_maps), so the transport follows the backup MX fields.
     */
    public function testBackupMxSetsTheRelayTransport(): void
    {
        $created = Domain::fromFormData(['domainName' => 'example.com', 'backupMx' => true, 'primaryMx' => '[mx1.example.net]:25']);
        $this->assertSame('relay:[mx1.example.net]:25', $created->transport);
        $this->assertSame('[mx1.example.net]:25', $created->primaryMx);

        $stored = new Domain('example.com');
        $stored->applyProfile(Domain::fromFormData(['backupMx' => '1', 'transport' => 'dovecot']));
        $this->assertSame('relay:example.com', $stored->transport);
        $this->assertSame('', $stored->primaryMx);

        // The edit form still posts the relay transport when the admin turns backup MX off.
        $stored->applyProfile(Domain::fromFormData(['transport' => 'relay:example.com']));
        $this->assertFalse($stored->backupMx);
        $this->assertSame('dovecot', $stored->transport);
    }

    public function testInvalidPrimaryMxIsRejected(): void
    {
        $this->expectException(InvalidInputException::class);
        Domain::fromFormData(['domainName' => 'example.com', 'backupMx' => true, 'primaryMx' => 'bad host;x']);
    }

    public function testListLimitCountsMailingListsOnly(): void
    {
        $domain = new Domain('example.com', lists: 2);

        $this->assertNull($domain->newListError(1));
        $this->assertSame(['current' => 2, 'max' => 2], $domain->newListError(2)?->params);
        $this->assertNull((new Domain('example.com'))->newListError(500));
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
            'mtaTransport' => 'lmtp:unix:private/dovecot-lmtp',
        ];

        $domain = Domain::fromLdapEntry($entry);

        $this->assertSame('ldap.test', $domain->domainName);
        $this->assertSame('LDAP domain', $domain->description);
        $this->assertTrue($domain->active);
        $this->assertSame(15, $domain->currentUserCount);
        $this->assertSame('lmtp:unix:private/dovecot-lmtp', $domain->transport);
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

    public function testNewMailboxFitsUnlimitedDomain(): void
    {
        $this->assertNull((new Domain(domainName: 'x.test', currentUserCount: 50))->newMailboxError(1024));
    }

    /**
     * The web form, the REST API and the CLI import refuse a mailbox that breaks a domain limit.
     *
     * @return array<string, array{Domain, int, string}>
     */
    public static function brokenLimits(): array
    {
        return [
            'mailbox count' => [new Domain(domainName: 'x.test', mailboxes: 3, currentUserCount: 3), 10, 'user.msg_mailbox_limit'],
            'user quota' => [new Domain(domainName: 'x.test', maxQuota: 100), 101, 'user.msg_quota_exceeds_max'],
            'domain quota' => [new Domain(domainName: 'x.test', quota: 500, currentQuotaUsed: 450), 60, 'user.msg_total_quota_exceeded_detail'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('brokenLimits')]
    public function testNewMailboxBreakingALimitIsRefused(Domain $domain, int $mailQuota, string $translationKey): void
    {
        $error = $domain->newMailboxError($mailQuota);

        $this->assertInstanceOf(InvalidInputException::class, $error);
        $this->assertSame($translationKey, $error->translationKey);
    }

    public function testNewMailboxAtTheLimitsFits(): void
    {
        $domain = new Domain(domainName: 'x.test', maxQuota: 100, quota: 500, mailboxes: 3, currentUserCount: 2, currentQuotaUsed: 400);

        $this->assertNull($domain->newMailboxError(100));
    }

    public function testQuotaChangeAboveTheUserMaximumIsRefused(): void
    {
        $error = (new Domain(domainName: 'x.test', maxQuota: 100))->quotaChangeError(50, 101);

        $this->assertSame('user.msg_quota_exceeds_max', $error?->translationKey);
    }

    /**
     * Only the growth of the quota counts against the total domain quota,
     * so a mailbox in a full domain can still shrink or keep its quota.
     */
    public function testQuotaChangeCountsOnlyTheGrowthAgainstTheDomainQuota(): void
    {
        $domain = new Domain(domainName: 'x.test', quota: 500, currentQuotaUsed: 480);

        $this->assertNull($domain->quotaChangeError(100, 120));
        $this->assertNull($domain->quotaChangeError(100, 50));
        $this->assertSame('user.msg_total_quota_exceeded', $domain->quotaChangeError(100, 121)?->translationKey);
    }
}
