<?php

declare(strict_types=1);

namespace App;

/**
 * Badge colors for categorical values. The same meaning gets the same color on
 * every page: allowed or clean is green, dangerous is red, restricted is orange
 * or yellow, and plain information is blue, cyan or purple. An unknown value is gray.
 */
class BadgeTone
{
    public const FALLBACK = 'gray';

    /** Category => lowercase value => tone. */
    private const TONES = [
        'log_event' => [
            'login' => 'cyan',
            'create' => 'green',
            'active' => 'green',
            'update' => 'blue',
            'delete' => 'red',
            'disable' => 'orange',
            'backup' => 'purple',
            'cleanup_db' => 'yellow',
            // A row with loglevel=error, such as a failed login.
            'error' => 'red',
        ],
        'admin_type' => [
            'mailbox' => 'blue',
            'standalone' => 'purple',
        ],
        'access_policy' => [
            'public' => 'green',
            'domain' => 'blue',
            'subdomain' => 'blue',
            'membersonly' => 'cyan',
            'moderatorsonly' => 'orange',
            'allowedonly' => 'orange',
        ],
        // Amavisd msgs.content codes.
        'mail_content' => [
            'c' => 'green',
            's' => 'orange',
            'y' => 'orange',
            'v' => 'red',
            'b' => 'red',
            'h' => 'yellow',
            'm' => 'yellow',
            'o' => 'yellow',
            't' => 'yellow',
            'u' => 'gray',
        ],
        'throttle_kind' => [
            'inbound' => 'cyan',
            'outbound' => 'purple',
            'external' => 'orange',
        ],
        'wblist' => [
            'w' => 'green',
            'b' => 'red',
        ],
        'ownership' => [
            'verified' => 'green',
            'pending' => 'yellow',
        ],
        'setting_source' => [
            'database' => 'green',
            'env' => 'gray',
        ],
        // Counters in tabs and section headers, by the kind of item they count.
        'count' => [
            'domains' => 'cyan',
            'users' => 'blue',
            'aliases' => 'purple',
            'mailing_lists' => 'green',
            'admins' => 'orange',
            'members' => 'blue',
            'moderators' => 'orange',
            'subscribers' => 'green',
        ],
    ];

    /**
     * Returns the badge CSS classes for a value of a category.
     */
    public static function classes(string $category, string $value): string
    {
        return 'tone-badge tone-' . self::tone($category, $value);
    }

    public static function tone(string $category, string $value): string
    {
        return self::TONES[$category][strtolower($value)] ?? self::FALLBACK;
    }
}
