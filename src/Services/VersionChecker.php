<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Settings;

class VersionChecker
{
    private const API_URL = 'https://api.github.com/repos/KilimcininKorOglu/iRedPanel/releases/latest';
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Checks if a newer version of iRedPanel is available on GitHub.
     * Returns the new version string or null if up-to-date/unavailable.
     */
    public static function checkForUpdate(): ?string
    {
        $settings = Settings::getInstance();
        if (!$settings->checkUpdates) {
            return null;
        }

        $cacheFile = sys_get_temp_dir() . '/iredpanel_version_check.json';
        $cached = self::readCache($cacheFile);
        if ($cached !== false) {
            return $cached;
        }

        $latestVersion = self::fetchLatestVersion();
        if ($latestVersion === null) {
            self::writeCache($cacheFile, null);
            return null;
        }

        $currentVersion = defined('APP_VERSION') ? APP_VERSION : '0.0.0';
        $isNewer = version_compare($latestVersion, $currentVersion, '>');

        $result = $isNewer ? $latestVersion : null;
        self::writeCache($cacheFile, $result);

        return $result;
    }

    private static function fetchLatestVersion(): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: iRedPanel\r\nAccept: application/vnd.github.v3+json\r\n",
                'timeout' => 5,
            ],
        ]);

        $response = @file_get_contents(self::API_URL, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        $tagName = $data['tag_name'] ?? null;

        return $tagName !== null ? ltrim((string) $tagName, 'v') : null;
    }

    /**
     * Returns cached version (string|null) or false if cache is miss/expired.
     */
    private static function readCache(string $file): string|null|false
    {
        if (!file_exists($file)) {
            return false;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return false;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['timestamp'])) {
            return false;
        }

        if (time() - $data['timestamp'] > self::CACHE_TTL) {
            return false;
        }

        return $data['version'] ?? null;
    }

    private static function writeCache(string $file, ?string $version): void
    {
        $data = json_encode(['timestamp' => time(), 'version' => $version]);
        @file_put_contents($file, $data);
    }
}
