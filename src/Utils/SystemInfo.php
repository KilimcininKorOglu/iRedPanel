<?php

declare(strict_types=1);

namespace App\Utils;

class SystemInfo
{
    public static function getHostname(): string
    {
        return gethostname() ?: php_uname('n');
    }

    /**
     * Returns server uptime as [days, hours, minutes] or null if unavailable.
     *
     * @return array{days: int, hours: int, minutes: int}|null
     */
    public static function getUptime(): ?array
    {
        if (!file_exists('/proc/uptime')) {
            return null;
        }
        $raw = @file_get_contents('/proc/uptime');
        if ($raw === false) {
            return null;
        }
        $seconds = (int) explode(' ', trim($raw))[0];
        return [
            'days' => intdiv($seconds, 86400),
            'hours' => intdiv($seconds % 86400, 3600),
            'minutes' => intdiv($seconds % 3600, 60),
        ];
    }

    /**
     * Returns system load averages [1min, 5min, 15min].
     *
     * @return float[]
     */
    public static function getLoadAverage(): array
    {
        return sys_getloadavg() ?: [0.0, 0.0, 0.0];
    }

    public static function getIredMailVersion(): string
    {
        $file = '/etc/iredmail-release';
        if (!file_exists($file)) {
            return 'N/A';
        }
        $content = @file_get_contents($file);
        if ($content === false) {
            return 'N/A';
        }
        $parts = explode(' ', trim($content));
        return $parts[0] ?? 'N/A';
    }

    public static function getPhpVersion(): string
    {
        return PHP_VERSION;
    }

    public static function getIredPanelVersion(): string
    {
        return defined('APP_VERSION') ? APP_VERSION : 'dev';
    }
}
