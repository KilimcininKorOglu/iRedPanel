<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Exceptions\InvalidInputException;
use App\Models\DomainSettings;
use App\Models\User;
use App\Navigation;
use App\Services\SelfService;
use PHPUnit\Framework\TestCase;

class SelfServiceTest extends TestCase
{
    public function testDomainWithoutSelfServiceOpensNoPage(): void
    {
        $this->assertSame([], SelfService::openPages(DomainSettings::fromSettingsString(''), true));
    }

    /**
     * The domain admin closes preferences, and without the Amavisd integration the
     * white/blacklist, spam policy and mail pages have no data to show.
     */
    public function testClosedPreferencesAndMissingAmavisdHidePages(): void
    {
        $settings = DomainSettings::fromSettingsString('enabled_services:self-service;disabled_user_preferences:password,quarantine;');

        $this->assertSame(['profile', 'forwarding', 'wblist', 'spam-policy', 'received'], SelfService::openPages($settings, true));
        $this->assertSame(['profile', 'forwarding'], SelfService::openPages($settings, false));
    }

    public function testMenuListsOnlyTheOpenPages(): void
    {
        $groups = Navigation::selfServiceGroups(['profile', 'quarantine']);

        $this->assertSame(['nav.group_self_service'], array_keys($groups));
        $this->assertSame(['/self/profile', '/self/quarantine'], array_column($groups['nav.group_self_service'], 'href'));
        $this->assertSame('/self/quarantine', Navigation::activeHref($groups, '/self/quarantine?page=2'));
        $this->assertSame([], Navigation::selfServiceGroups([]));
    }

    public function testLanguageAcceptsLocaleCodesOnly(): void
    {
        $this->assertSame('de_DE', User::validLanguage('de_DE'));
        $this->assertSame('', User::validLanguage(''));
        $this->assertNull(User::validLanguage(null));

        $this->expectException(InvalidInputException::class);
        User::validLanguage('de_DE;x');
    }
}
