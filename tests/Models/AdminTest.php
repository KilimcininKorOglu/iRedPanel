<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Exceptions\InvalidInputException;
use App\Models\Admin;
use PHPUnit\Framework\TestCase;

class AdminTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $admin = new Admin(username: 'admin@test.com');

        $this->assertSame('admin@test.com', $admin->username);
        $this->assertSame('', $admin->name);
        $this->assertTrue($admin->active);
        $this->assertFalse($admin->isGlobalAdmin);
        $this->assertFalse($admin->isMailboxAdmin);
        $this->assertNull($admin->created);
    }

    public function testFromFormData(): void
    {
        $post = [
            'username' => '  Admin@Example.COM  ',
            'name' => 'Test Admin',
            'active' => '1',
            'isGlobalAdmin' => '1',
        ];

        $admin = Admin::fromFormData($post);

        $this->assertSame('admin@example.com', $admin->username);
        $this->assertSame('Test Admin', $admin->name);
        $this->assertTrue($admin->active);
        $this->assertTrue($admin->isGlobalAdmin);
    }

    public function testLanguageIsReadFromEveryBackend(): void
    {
        $this->assertSame('de_DE', Admin::fromFormData(['username' => 'a@test.com', 'language' => 'de_DE'])->language);
        $this->assertSame('', Admin::fromFormData(['username' => 'a@test.com'])->language);
        $this->assertSame('tr_TR', Admin::fromMysqlRow(['username' => 'a@test.com', 'language' => 'tr_TR'])->language);
        $this->assertSame('', Admin::fromMysqlRow(['username' => 'a@test.com', 'language' => null])->language);
        $this->assertSame('fr_FR', Admin::fromLdapEntry(['mail' => 'a@test.com', 'preferredLanguage' => 'fr_FR'])->language);
    }

    public function testInvalidLanguageIsRejected(): void
    {
        $this->expectException(InvalidInputException::class);
        Admin::fromFormData(['username' => 'a@test.com', 'language' => '../x']);
    }

    public function testFromFormDataNoCheckboxes(): void
    {
        $post = ['username' => 'admin@test.com', 'name' => 'Test'];

        $admin = Admin::fromFormData($post);

        $this->assertFalse($admin->active);
        $this->assertFalse($admin->isGlobalAdmin);
    }

    public function testFromMysqlRowStandalone(): void
    {
        $row = [
            'username' => 'standalone@test.com',
            'name' => 'Standalone Admin',
            'active' => 1,
            'isGlobalAdmin' => 1,
            'created' => '2026-01-01',
            'passwordlastchange' => '2026-03-01',
        ];

        $admin = Admin::fromMysqlRow($row, false);

        $this->assertSame('standalone@test.com', $admin->username);
        $this->assertSame('Standalone Admin', $admin->name);
        $this->assertTrue($admin->active);
        $this->assertTrue($admin->isGlobalAdmin);
        $this->assertFalse($admin->isMailboxAdmin);
        $this->assertSame('2026-01-01', $admin->created);
    }

    public function testFromMysqlRowMailboxAdmin(): void
    {
        $row = [
            'username' => 'mailbox@test.com',
            'name' => 'Mailbox Admin',
            'active' => 1,
            'isGlobalAdmin' => 0,
        ];

        $admin = Admin::fromMysqlRow($row, true);

        $this->assertTrue($admin->isMailboxAdmin);
        $this->assertFalse($admin->isGlobalAdmin);
    }

    public function testFromLdapEntry(): void
    {
        $entry = [
            'mail' => 'ldap@test.com',
            'cn' => 'LDAP Admin',
            'accountStatus' => 'active',
            'domainGlobalAdmin' => 'yes',
        ];

        $admin = Admin::fromLdapEntry($entry, true);

        $this->assertSame('ldap@test.com', $admin->username);
        $this->assertSame('LDAP Admin', $admin->name);
        $this->assertTrue($admin->active);
        $this->assertTrue($admin->isGlobalAdmin);
        $this->assertTrue($admin->isMailboxAdmin);
    }

    public function testFromLdapEntryDisabled(): void
    {
        $entry = [
            'mail' => 'disabled@test.com',
            'cn' => 'Disabled',
            'accountStatus' => 'disabled',
        ];

        $admin = Admin::fromLdapEntry($entry);

        $this->assertFalse($admin->active);
        $this->assertFalse($admin->isGlobalAdmin);
    }

    public function testApplyLimits(): void
    {
        $admin = new Admin(username: 'a@test.com');
        $admin->applyLimits(['createMaxUsers' => '2', 'createMaxQuota' => '-1', 'createMaxAliases' => '']);

        $this->assertSame(2, $admin->createMaxUsers);
        $this->assertSame(-1, $admin->createMaxQuota);
        $this->assertSame(-1, $admin->createMaxAliases);
        $this->assertFalse($admin->createNewDomains);
    }

    /**
     * Text used to become 0 (nothing may be created) and -5 used to become -1 (unlimited).
     *
     * @return array<string, array{string}>
     */
    public static function invalidLimits(): array
    {
        return ['text' => ['abc'], 'below -1' => ['-5'], 'fraction' => ['2.5']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLimits')]
    public function testInvalidLimitIsRejectedAndNothingChanges(string $value): void
    {
        $admin = new Admin(username: 'a@test.com', createMaxDomains: 3);

        try {
            $admin->applyLimits(['createMaxDomains' => '5', 'createMaxUsers' => $value]);
            $this->fail("{$value} must be rejected");
        } catch (InvalidInputException $e) {
            $this->assertSame('admin.max_users', $e->fieldKey);
        }
        $this->assertSame(3, $admin->createMaxDomains);
    }

    /**
     * A partial JSON body must keep the limits it does not name; the form semantics
     * of applyLimits() would reset them to unlimited and clear createNewDomains.
     */
    public function testApplyLimitsFromJsonKeepsUnnamedLimits(): void
    {
        $admin = new Admin(username: 'a@test.com', createMaxDomains: 3, createMaxUsers: 7, createNewDomains: true);

        $this->assertTrue($admin->applyLimitsFromJson(['createMaxUsers' => 2]));
        $this->assertSame(2, $admin->createMaxUsers);
        $this->assertSame(3, $admin->createMaxDomains);
        $this->assertTrue($admin->createNewDomains);

        $this->assertTrue($admin->applyLimitsFromJson(['createNewDomains' => false]));
        $this->assertFalse($admin->createNewDomains);
        $this->assertSame(2, $admin->createMaxUsers);
    }

    public function testApplyLimitsFromJsonWithoutLimits(): void
    {
        $admin = new Admin(username: 'a@test.com', createMaxUsers: 7);

        $this->assertFalse($admin->applyLimitsFromJson(['name' => 'x', 'active' => false]));
        $this->assertSame(7, $admin->createMaxUsers);
    }

    public function testApplyLimitsFromJsonRejectsInvalidLimit(): void
    {
        $admin = new Admin(username: 'a@test.com', createMaxUsers: 7);

        $this->expectException(InvalidInputException::class);
        $admin->applyLimitsFromJson(['createMaxUsers' => 'abc']);
    }

    /**
     * iRedAdmin reads -1 as "not allowed" and allows domain creation when the key is
     * present, so an unlimited limit and a forbidden domain creation are not written.
     */
    public function testSettingValuesUseTheIredadminForm(): void
    {
        $admin = new Admin(username: 'a@test.com', createMaxDomains: 0, createMaxUsers: 20);
        $this->assertSame(['create_max_domains' => '0', 'create_max_users' => '20'], $admin->settingValues());

        $admin->createNewDomains = true;
        $this->assertSame(['create_max_domains:0', 'create_max_users:20', 'create_new_domains:yes'], $admin->toLdapAccountSetting());
    }

    /**
     * A save keeps the keys that iRedAdmin wrote and replaces the panel keys, also the
     * ones that are unlimited now.
     */
    public function testMergedSettingsKeepOtherKeys(): void
    {
        $admin = new Admin(username: 'a@test.com', createMaxUsers: 5);
        $stored = 'create_max_domains:3;other_key:1;create_new_domains:yes;disable_viewing_mail_log:yes;';

        $this->assertSame('other_key:1;create_max_users:5;', $admin->mergedSettings($stored));
    }

    /**
     * An older panel version stored JSON, which iRedAdmin cannot parse.
     */
    public function testMergedSettingsConvertsLegacyJson(): void
    {
        $admin = Admin::fromMysqlRow(['username' => 'a@test.com', 'settings' => '{"create_max_users":4,"create_new_domains":false}']);
        $this->assertSame(4, $admin->createMaxUsers);
        $this->assertFalse($admin->createNewDomains);

        $this->assertSame('create_max_users:4;', $admin->mergedSettings('{"create_max_users":4,"create_new_domains":false}'));
    }

    /**
     * iRedAdmin writes create_new_domains:yes; an older panel version also wrote "no".
     */
    public function testDomainCreationFollowsTheStoredKey(): void
    {
        $this->assertFalse(Admin::fromMysqlRow(['username' => 'a@test.com', 'settings' => ''])->createNewDomains);
        $this->assertTrue(Admin::fromMysqlRow(['username' => 'a@test.com', 'settings' => 'create_new_domains:yes;'])->createNewDomains);
        $this->assertFalse(Admin::fromLdapEntry(['mail' => 'a@test.com', 'accountSetting' => 'create_new_domains:no'])->createNewDomains);
    }

    /**
     * iRedAdmin reads the toggles from the SQL settings ("yes") and from the LDAP
     * disabledService values; LDAP keeps them out of accountSetting.
     */
    public function testPermissionTogglesFollowTheBackendForm(): void
    {
        $sql = Admin::fromMysqlRow(['username' => 'a@test.com', 'settings' => 'disable_viewing_mail_log:yes;disable_managing_quarantined_mails:no;']);
        $this->assertTrue($sql->disableViewingMailLog);
        $this->assertFalse($sql->disableManagingQuarantinedMails);

        $ldap = Admin::fromLdapEntry(['mail' => 'a@test.com', 'disabledService' => 'smtp;manage_quarantined_mails']);
        $this->assertFalse($ldap->disableViewingMailLog);
        $this->assertTrue($ldap->disableManagingQuarantinedMails);

        $admin = new Admin(username: 'a@test.com', createMaxUsers: 2);
        $admin->applyLimits(['createMaxUsers' => '2', 'disableViewingMailLog' => 'on']);
        $this->assertSame(['create_max_users' => '2', 'disable_viewing_mail_log' => 'yes'], $admin->settingValues());
        $this->assertSame(['create_max_users:2'], $admin->toLdapAccountSetting());
        $this->assertSame(['view_mail_log'], $admin->ldapDisabledServices());
    }

    public function testPermissionTogglesFromJsonKeepTheOtherValues(): void
    {
        $admin = new Admin(username: 'a@test.com', createMaxUsers: 7, disableViewingMailLog: true);

        $this->assertTrue($admin->applyLimitsFromJson(['disableManagingQuarantinedMails' => true]));
        $this->assertTrue($admin->disableViewingMailLog);
        $this->assertTrue($admin->disableManagingQuarantinedMails);
        $this->assertSame(7, $admin->createMaxUsers);
    }
}
