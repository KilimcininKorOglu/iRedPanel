<?php

declare(strict_types=1);

namespace Tests;

use App\Navigation;
use PHPUnit\Framework\TestCase;

class NavigationTest extends TestCase
{
    private const ALL_FEATURES = ['amavisd' => true, 'fail2ban' => true, 'iredapd' => true, 'domainOwnership' => true];

    public function testDomainAdminSeesOnlyTheSharedItems(): void
    {
        // A domain admin must not see links to pages that answer 403 for them.
        $hrefs = self::hrefs(Navigation::groups(false, self::ALL_FEATURES));

        // The alias and mailing list pages show only the domains of the admin.
        $this->assertSame(['/dashboard', '/search', '/domains', '/aliases', '/mailing-lists'], $hrefs);
    }

    public function testDisabledFeatureHidesItsItems(): void
    {
        $hrefs = self::hrefs(Navigation::groups(true, ['amavisd' => false, 'fail2ban' => false, 'iredapd' => true, 'domainOwnership' => false]));

        $this->assertNotContains('/amavisd/quarantine', $hrefs);
        $this->assertNotContains('/fail2ban', $hrefs);
        $this->assertNotContains('/verify/domain-ownership', $hrefs);
        $this->assertContains('/iredapd/throttle/@.', $hrefs);
    }

    public function testGroupWithoutVisibleItemsIsLeftOut(): void
    {
        $groups = Navigation::groups(false, self::ALL_FEATURES);

        $this->assertSame(['nav.group_general', 'nav.group_accounts'], array_keys($groups));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function activePaths(): array
    {
        return [
            'exact' => ['/domains', '/domains'],
            'sub page' => ['/domains/example.com/settings', '/domains'],
            'query string' => ['/aliases?domain=example.com', '/aliases'],
            'user pages belong to domains' => ['/example.com/users/john/general', '/domains'],
            'iredapd sub page' => ['/iredapd/greylisting/@.', '/iredapd/throttle/@.'],
            'extra prefix' => ['/last-logins', '/system-settings'],
            'compatibility list' => ['/compatibility', '/compatibility'],
            'prefix is not a word match' => ['/domains-old', null],
            'unknown page' => ['/nowhere', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('activePaths')]
    public function testActiveHref(string $path, ?string $expected): void
    {
        $this->assertSame($expected, Navigation::activeHref(Navigation::groups(true, self::ALL_FEATURES), $path));
    }

    /**
     * @param array<string, list<array{href: string}>> $groups
     * @return string[]
     */
    private static function hrefs(array $groups): array
    {
        return array_column(array_merge(...array_values($groups)), 'href');
    }
}
