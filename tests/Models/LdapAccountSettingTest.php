<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\Domain;
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

    public function testWriteKeepsOtherKeysAndDropsUnlimitedLimits(): void
    {
        $domain = new Domain('example.com', maxQuota: 250, mailboxes: 0, aliases: 7);
        $current = ['minPasswordLength:8', 'numberOfUsers:5', 'disabledDomainProfile:bcc', 'disabledDomainProfile:relay'];

        $this->assertEqualsCanonicalizing(
            ['minPasswordLength:8', 'disabledDomainProfile:bcc', 'disabledDomainProfile:relay', 'maxUserQuota:250', 'numberOfAliases:7'],
            LdapAccountSetting::valuesFor($domain, $current)
        );
    }
}
