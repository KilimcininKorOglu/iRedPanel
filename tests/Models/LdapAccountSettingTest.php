<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\LdapAccountSetting;
use PHPUnit\Framework\TestCase;

class LdapAccountSettingTest extends TestCase
{
    /**
     * The LDAP repository used to ignore the domain limits, so a limit entered in the form was lost.
     */
    public function testLimitsAreReadFromTheIredadminKeys(): void
    {
        $domain = new Domain('example.com');

        LdapAccountSetting::applyTo($domain, ['numberOfUsers:5', 'maxUserQuota:500', 'numberOfAliases:3', 'defaultQuota:1024']);

        $this->assertSame(5, $domain->mailboxes);
        $this->assertSame(500, $domain->maxQuota);
        $this->assertSame(3, $domain->aliases);
    }

    /**
     * The Settings tab used to show 0 for a domain with iRedAdmin's defaultQuota and
     * minPasswordLength, and a save did not reach LDAP.
     */
    public function testDomainSettingsUseTheIredadminKeys(): void
    {
        $domain = new Domain('example.com');
        LdapAccountSetting::applyTo($domain, ['minPasswordLength:8', 'defaultQuota:1024']);

        $settings = DomainSettings::fromSettingsString($domain->settings);
        $this->assertSame(1024, $settings->defaultUserQuota);
        $this->assertSame(8, $settings->minPasswordLength);
        $this->assertSame(0, $settings->maxPasswordLength);

        $domain->settings = (new DomainSettings(defaultUserQuota: 512, maxPasswordLength: 40))->toSettingsString();
        $this->assertEqualsCanonicalizing(
            ['defaultQuota:512', 'maxPasswordLength:40'],
            LdapAccountSetting::valuesFor($domain, ['minPasswordLength:8', 'defaultQuota:1024'])
        );
    }

    /**
     * iRedAdmin stores one accountSetting value per disabled service ("disabledMailService:imap").
     */
    public function testDisabledMailServicesUseOneValuePerService(): void
    {
        $domain = new Domain('example.com');
        LdapAccountSetting::applyTo($domain, ['disabledMailService:POP3', 'disabledMailService:imap', 'numberOfUsers:2']);
        $this->assertSame(['pop3', 'imap'], DomainSettings::fromSettingsString($domain->settings)->disabledMailServices);

        $domain->settings = (new DomainSettings(disabledMailServices: ['sogo']))->toSettingsString();
        $this->assertEqualsCanonicalizing(
            ['numberOfUsers:2', 'disabledMailService:sogo'],
            LdapAccountSetting::valuesFor($domain, ['disabledMailService:pop3', 'disabledMailService:imap', 'numberOfUsers:9'])
        );
    }

    public function testWriteKeepsOtherKeysAndDropsUnlimitedLimits(): void
    {
        $domain = new Domain('example.com', maxQuota: 250, mailboxes: 0, aliases: 7);
        $current = ['numberOfLists:2', 'numberOfUsers:5', 'disabledDomainProfile:bcc', 'disabledDomainProfile:relay'];

        $this->assertEqualsCanonicalizing(
            ['numberOfLists:2', 'disabledDomainProfile:bcc', 'disabledDomainProfile:relay', 'maxUserQuota:250', 'numberOfAliases:7'],
            LdapAccountSetting::valuesFor($domain, $current)
        );
    }
}
