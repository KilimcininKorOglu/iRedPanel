<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Exceptions\InvalidInputException;
use App\Models\DomainSettings;
use App\Models\ProfileToggles;
use App\Models\Settings;
use PHPUnit\Framework\TestCase;

class DomainSettingsTest extends TestCase
{
    public function testFormValuesRoundTripThroughTheSettingsString(): void
    {
        $settings = DomainSettings::fromFormData([
            'defaultUserQuota' => '256',
            'minPasswordLength' => '10',
            'maxPasswordLength' => '64',
        ]);

        $this->assertSame('default_user_quota:256;min_passwd_length:10;max_passwd_length:64;', $settings->toSettingsString());
    }

    /**
     * Other tools store more keys in domain.settings (default_language, timezone, ...).
     * A save from the settings tab used to delete them.
     */
    public function testSaveKeepsTheKeysOfOtherTools(): void
    {
        $settings = DomainSettings::fromFormData(['defaultUserQuota' => '100']);
        $settings->keepOtherKeysOf('default_user_quota:5;default_language:de_DE;timezone:UTC;');

        $this->assertSame(
            'default_user_quota:100;default_language:de_DE;timezone:UTC;',
            $settings->toSettingsString(),
        );
    }

    /**
     * The page toggles use the stored keys; self-service is an item of enabled_services,
     * and the other items of that list stay when the form turns self-service on or off.
     */
    public function testPageTogglesUseTheIredadminKeys(): void
    {
        $settings = DomainSettings::fromFormData([
            'disabledDomainProfiles' => ['bcc', 'relay'],
            'disabledUserProfiles' => ['password'],
            'disabledUserPreferences' => ['forwarding', 'wblist'],
            'selfService' => true,
        ]);
        $settings->keepOtherKeysOf('enabled_services:senderbcc;');

        $this->assertSame(
            'disabled_domain_profiles:bcc,relay;disabled_user_profiles:password;disabled_user_preferences:forwarding,wblist;enabled_services:senderbcc,self-service;',
            $settings->toSettingsString(),
        );
        $stored = DomainSettings::fromSettingsString($settings->toSettingsString());
        $this->assertTrue($stored->selfService());
        $this->assertFalse(ProfileToggles::preferenceOpen($stored, 'wblist'));
        $this->assertTrue(ProfileToggles::preferenceOpen($stored, 'quarantine'));
        // The admins and DNS pages carry no toggle, so a domain admin always opens them.
        $this->assertSame(['settings', 'catchall', 'admins', 'dns'], ProfileToggles::openDomainPages($stored, false));
        $this->assertFalse(ProfileToggles::userPageOpen($stored, false, 'password'));
        $this->assertTrue(ProfileToggles::userPageOpen($stored, true, 'password'));
    }

    public function testUnknownPageIsRejected(): void
    {
        $this->expectException(InvalidInputException::class);
        DomainSettings::fromFormData(['disabledUserProfiles' => ['general', 'nope']]);
    }

    /**
     * A domain admin saves the settings page, but the page toggles bound the domain admin.
     */
    public function testDomainAdminSaveKeepsTheGlobalAdminToggles(): void
    {
        $stored = DomainSettings::fromSettingsString('disabled_domain_profiles:relay;disabled_user_profiles:bcc;');
        $posted = DomainSettings::fromFormData(['disabledUserPreferences' => ['received']]);
        $posted->keepGlobalAdminTogglesOf($stored);

        $this->assertSame(['relay'], $posted->disabledDomainProfiles);
        $this->assertSame(['bcc'], $posted->disabledUserProfiles);
        $this->assertSame(['received'], $posted->disabledUserPreferences);
    }

    public function testDomainAdminCannotLowerTheMinPasswordLengthBelowTheGlobalMin(): void
    {
        $globalMin = Settings::getInstance()->passwordMinLength;
        $this->expectException(InvalidInputException::class);
        DomainSettings::fromFormData(['minPasswordLength' => (string) ($globalMin - 1)])->assertDomainAdminPasswordPolicy();
    }

    /**
     * The disclaimer lives in its own column, because ';' and ':' in the text break the settings string.
     */
    public function testDisclaimerIsNotWrittenToTheSettingsString(): void
    {
        $settings = DomainSettings::fromFormData(['disclaimer' => 'a; b: c']);

        $this->assertSame('a; b: c', $settings->disclaimer);
        $this->assertStringNotContainsString('disclaimer', $settings->toSettingsString());
    }

    /**
     * 0 means "use the global value" or "unlimited", so an invalid value used to remove the limit.
     */
    public function testNegativeNumberIsRejectedWithItsFieldLabel(): void
    {
        try {
            DomainSettings::fromFormData(['minPasswordLength' => '-3']);
            $this->fail('A negative length must be rejected');
        } catch (InvalidInputException $e) {
            $this->assertSame('domain.min_password_length', $e->fieldKey);
        }
    }

    public function testMinAboveMaxIsRejected(): void
    {
        $this->expectException(InvalidInputException::class);
        DomainSettings::fromFormData(['minPasswordLength' => '50', 'maxPasswordLength' => '20']);
    }

    /**
     * With no domain min, UserPassword applies the global min, so that value must fit under the max too.
     */
    public function testGlobalMinAboveDomainMaxIsRejected(): void
    {
        $globalMin = Settings::getInstance()->passwordMinLength;
        $this->assertGreaterThan(1, $globalMin);

        try {
            DomainSettings::fromFormData(['maxPasswordLength' => (string) ($globalMin - 1)]);
            $this->fail('A max below the global min must be rejected');
        } catch (InvalidInputException $e) {
            $this->assertSame(['min' => $globalMin, 'max' => $globalMin - 1], $e->params);
        }
    }
}
