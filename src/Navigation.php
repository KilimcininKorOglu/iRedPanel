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
            ['href' => '/aliases', 'label' => 'nav.aliases', 'icon' => 'at', 'requires' => [], 'match' => ['/aliases']],
            ['href' => '/mailing-lists', 'label' => 'nav.mailing_lists', 'icon' => 'people', 'requires' => [], 'match' => ['/mailing-lists']],
            ['href' => '/mail-lists', 'label' => 'nav.mail_lists', 'icon' => 'collection', 'requires' => ['mailLists'], 'match' => ['/mail-lists']],
            ['href' => '/domain-aliases', 'label' => 'nav.domain_aliases', 'icon' => 'signpost-split', 'requires' => ['global'], 'match' => ['/domain-aliases']],
            ['href' => '/admins', 'label' => 'nav.admins', 'icon' => 'person-badge', 'requires' => ['global'], 'match' => ['/admins']],
            ['href' => '/deleted-mailboxes', 'label' => 'nav.deleted_mailboxes', 'icon' => 'trash3', 'requires' => ['global'], 'match' => ['/deleted-mailboxes']],
        ],
        'nav.group_security' => [
            ['href' => '/amavisd/quarantine', 'label' => 'nav.quarantine', 'icon' => 'shield-exclamation', 'requires' => ['quarantine'], 'match' => ['/amavisd/quarantine']],
            ['href' => '/amavisd/maillog', 'label' => 'maillog.title', 'icon' => 'envelope-paper', 'requires' => ['mailLog'], 'match' => ['/amavisd/maillog']],
            ['href' => '/amavisd/spam-policy', 'label' => 'nav.spam_policy', 'icon' => 'funnel', 'requires' => ['global', 'amavisd'], 'match' => ['/amavisd/spam-policy']],
            ['href' => '/amavisd/wblist', 'label' => 'nav.wblist', 'icon' => 'list-check', 'requires' => ['global', 'amavisd'], 'match' => ['/amavisd/wblist']],
            ['href' => '/fail2ban', 'label' => 'nav.fail2ban', 'icon' => 'shield-lock', 'requires' => ['global', 'fail2ban'], 'match' => ['/fail2ban']],
            ['href' => '/iredapd/throttle/@.', 'label' => 'nav.iredapd', 'icon' => 'speedometer', 'requires' => ['global', 'iredapd'], 'match' => ['/iredapd']],
        ],
        'nav.group_system' => [
            ['href' => '/logs', 'label' => 'nav.logs', 'icon' => 'journal-text', 'requires' => ['global'], 'match' => ['/logs']],
            ['href' => '/account-resources', 'label' => 'nav.account_resources', 'icon' => 'diagram-3', 'requires' => ['global', 'accountResources'], 'match' => ['/account-resources']],
            ['href' => '/verify/domain-ownership', 'label' => 'nav.domain_ownership', 'icon' => 'patch-check', 'requires' => ['global', 'domainOwnership'], 'match' => ['/verify']],
            ['href' => '/panel-settings', 'label' => 'nav.panel_settings', 'icon' => 'sliders', 'requires' => ['global'], 'match' => ['/panel-settings']],
            ['href' => '/compatibility', 'label' => 'nav.compatibility', 'icon' => 'ui-checks-grid', 'requires' => ['global'], 'match' => ['/compatibility']],
            ['href' => '/system-settings', 'label' => 'nav.system', 'icon' => 'cpu', 'requires' => ['global'], 'match' => ['/system-settings', '/last-logins', '/export']],
        ],
    ];

    /** Self-service page slug => label and icon; the menu of a mailbox user. */
    private const SELF_SERVICE_ITEMS = [
        'profile' => ['domain.pref_personal_info', 'person'],
        'password' => ['common.password', 'key'],
        'forwarding' => ['user.tab_forwarding', 'forward'],
        'wblist' => ['domain.pref_wblist', 'list-check'],
        'spam-policy' => ['domain.pref_spampolicy', 'funnel'],
        'quarantine' => ['domain.pref_quarantine', 'shield-exclamation'],
        'received' => ['domain.pref_received', 'envelope-paper'],
    ];

    /**
     * The menu of a self-service session: the open pages of the user, in menu order.
     *
     * @param list<string> $pages open page slugs (SelfService::openPages())
     * @return array<string, list<array{href: string, label: string, icon: string, match: string[]}>>
     */
    public static function selfServiceGroups(array $pages): array
    {
        $items = [];
        foreach (self::SELF_SERVICE_ITEMS as $page => [$label, $icon]) {
            if (in_array($page, $pages, true)) {
                $items[] = ['href' => "/self/{$page}", 'label' => $label, 'icon' => $icon, 'match' => ["/self/{$page}"]];
            }
        }

        return $items === [] ? [] : ['nav.group_self_service' => $items];
    }

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
            // PHPStan reads array_filter over the constant GROUPS list as non-empty,
            // but the callback drops every item whose flags are off, so a group can be empty.
            // @phpstan-ignore notIdentical.alwaysTrue
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
