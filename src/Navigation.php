<?php

declare(strict_types=1);

namespace App;

/**
 * The sidebar menu: grouped items with their visibility rules and the item
 * that belongs to the current request path.
 */
class Navigation
{
    /**
     * Group label key => items. An item shows when every entry of `requires` is
     * true: `global` needs a global admin, the other names are feature flags.
     * `match` lists the path prefixes that mark the item as the current page.
     */
    private const GROUPS = [
        'nav.group_general' => [
            ['href' => '/dashboard', 'label' => 'nav.dashboard', 'icon' => 'speedometer2', 'requires' => [], 'match' => ['/dashboard']],
            ['href' => '/search', 'label' => 'nav.search', 'icon' => 'search', 'requires' => [], 'match' => ['/search']],
        ],
        'nav.group_accounts' => [
            ['href' => '/domains', 'label' => 'nav.domains', 'icon' => 'globe2', 'requires' => [], 'match' => ['/domains']],
            ['href' => '/aliases', 'label' => 'nav.aliases', 'icon' => 'at', 'requires' => ['global'], 'match' => ['/aliases']],
            ['href' => '/mailing-lists', 'label' => 'nav.mailing_lists', 'icon' => 'people', 'requires' => ['global'], 'match' => ['/mailing-lists']],
            ['href' => '/domain-aliases', 'label' => 'nav.domain_aliases', 'icon' => 'signpost-split', 'requires' => ['global'], 'match' => ['/domain-aliases']],
            ['href' => '/admins', 'label' => 'nav.admins', 'icon' => 'person-badge', 'requires' => ['global'], 'match' => ['/admins']],
            ['href' => '/deleted-mailboxes', 'label' => 'nav.deleted_mailboxes', 'icon' => 'trash3', 'requires' => ['global'], 'match' => ['/deleted-mailboxes']],
        ],
        'nav.group_security' => [
            ['href' => '/amavisd/quarantine', 'label' => 'nav.quarantine', 'icon' => 'shield-exclamation', 'requires' => ['global', 'amavisd'], 'match' => ['/amavisd/quarantine']],
            ['href' => '/amavisd/maillog', 'label' => 'maillog.title', 'icon' => 'envelope-paper', 'requires' => ['global', 'amavisd'], 'match' => ['/amavisd/maillog']],
            ['href' => '/amavisd/spam-policy', 'label' => 'nav.spam_policy', 'icon' => 'funnel', 'requires' => ['global', 'amavisd'], 'match' => ['/amavisd/spam-policy']],
            ['href' => '/amavisd/wblist', 'label' => 'nav.wblist', 'icon' => 'list-check', 'requires' => ['global', 'amavisd'], 'match' => ['/amavisd/wblist']],
            ['href' => '/fail2ban', 'label' => 'nav.fail2ban', 'icon' => 'shield-lock', 'requires' => ['global', 'fail2ban'], 'match' => ['/fail2ban']],
            ['href' => '/iredapd/throttle/@.', 'label' => 'nav.iredapd', 'icon' => 'speedometer', 'requires' => ['global', 'iredapd'], 'match' => ['/iredapd']],
        ],
        'nav.group_system' => [
            ['href' => '/logs', 'label' => 'nav.logs', 'icon' => 'journal-text', 'requires' => ['global'], 'match' => ['/logs']],
            ['href' => '/verify/domain-ownership', 'label' => 'nav.domain_ownership', 'icon' => 'patch-check', 'requires' => ['global', 'domainOwnership'], 'match' => ['/verify']],
            ['href' => '/panel-settings', 'label' => 'nav.panel_settings', 'icon' => 'sliders', 'requires' => ['global'], 'match' => ['/panel-settings']],
            ['href' => '/compatibility', 'label' => 'nav.compatibility', 'icon' => 'ui-checks-grid', 'requires' => ['global'], 'match' => ['/compatibility']],
            ['href' => '/system-settings', 'label' => 'nav.system', 'icon' => 'cpu', 'requires' => ['global'], 'match' => ['/system-settings', '/last-logins', '/export']],
        ],
    ];

    /**
     * @param array<string, bool> $features feature flags as TemplateEngine passes them
     * @return array<string, list<array{href: string, label: string, icon: string, match: string[]}>>
     *         the visible items per group; a group without visible items is left out
     */
    public static function groups(bool $isGlobalAdmin, array $features): array
    {
        $flags = ['global' => $isGlobalAdmin] + $features;
        $groups = [];
        foreach (self::GROUPS as $label => $items) {
            $visible = array_values(array_filter(
                $items,
                static fn (array $item): bool => array_filter($item['requires'], static fn (string $flag): bool => empty($flags[$flag])) === []
            ));
            if ($visible !== []) {
                $groups[$label] = $visible;
            }
        }

        return $groups;
    }

    /**
     * Returns the href of the item whose longest `match` prefix covers the path.
     * The user pages (`/{domain}/users...`) belong to Domains.
     *
     * @param array<string, list<array{href: string, match: string[]}>> $groups
     */
    public static function activeHref(array $groups, string $path): ?string
    {
        $path = (string) parse_url($path, PHP_URL_PATH);
        $best = null;
        $bestLength = 0;
        foreach (array_merge(...array_values($groups)) as $item) {
            foreach ($item['match'] as $prefix) {
                $covers = $path === $prefix || str_starts_with($path, $prefix . '/');
                if ($covers && strlen($prefix) > $bestLength) {
                    [$best, $bestLength] = [$item['href'], strlen($prefix)];
                }
            }
        }
        if ($best === null && preg_match('#^/[^/]+/users(/|$)#', $path) === 1) {
            return '/domains';
        }

        return $best;
    }
}
