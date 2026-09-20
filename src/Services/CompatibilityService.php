<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Settings;

/**
 * The iRedMail versions each iRedPanel release is compatible with.
 *
 * The list is compatibility.json in the repository root. The dashboard reads the
 * copy on the main branch of GitHub, so an installed panel also sees releases that
 * came out after it. When the update check is disabled or GitHub cannot be read,
 * the copy bundled with the installed version is used.
 */
class CompatibilityService
{
    public const REMOTE_URL = 'https://raw.githubusercontent.com/KilimcininKorOglu/iRedPanel/main/compatibility.json';

    public const SOURCE_REMOTE = 'remote';
    public const SOURCE_OFFLINE = 'offline';
    public const SOURCE_LOCAL = 'local';

    private const CACHE_TTL = 86400;
    // A failed download is retried after an hour, not on every dashboard load.
    private const FAILURE_TTL = 3600;
    private const VERSION_PATTERN = '/^\d+(\.\d+){0,3}$/';
    private const BACKENDS = ['ldap', 'mysql', 'pgsql'];

    /**
     * @return array{releases: list<array{version: string, date: string, iredmail: list<string>, backends: list<string>}>, source: string, checkedAt: ?int}
     * @throws \RuntimeException when the bundled list is missing
     * @throws \InvalidArgumentException when a list cannot be parsed
     */
    public static function load(): array
    {
        if (!Settings::getInstance()->checkUpdates) {
            return ['releases' => self::localReleases(), 'source' => self::SOURCE_LOCAL, 'checkedAt' => null];
        }

        $cache = self::readCache(self::cacheFile());
        if ($cache === null) {
            $cache = self::download();
            self::writeCache(self::cacheFile(), $cache);
        }

        $releases = self::remoteReleases($cache['json']);
        if ($releases === null) {
            return ['releases' => self::localReleases(), 'source' => self::SOURCE_OFFLINE, 'checkedAt' => $cache['checkedAt']];
        }

        return ['releases' => $releases, 'source' => self::SOURCE_REMOTE, 'checkedAt' => $cache['checkedAt']];
    }

    /**
     * The compatibility list as the dashboard and the compatibility page show it.
     * A list that cannot be read is logged and reported with `error`, so that the
     * page still renders.
     *
     * @return array{error: bool, releases?: list<array>, current?: ?array, newer?: list<array>, source?: string, checkedAt?: ?int}
     */
    public static function report(string $installedVersion): array
    {
        try {
            $list = self::load();
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            error_log('iRedPanel: ' . $e->getMessage());
            return ['error' => true];
        }

        return self::forVersion($list['releases'], $installedVersion) + [
            'releases' => $list['releases'],
            'source' => $list['source'],
            'checkedAt' => $list['checkedAt'],
            'error' => false,
        ];
    }

    /**
     * Validates a compatibility list and returns its releases, newest first.
     *
     * @return list<array{version: string, date: string, iredmail: list<string>, backends: list<string>}>
     * @throws \InvalidArgumentException when the document does not follow the format
     */
    public static function parse(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data) || !is_array($data['releases'] ?? null) || $data['releases'] === []) {
            throw new \InvalidArgumentException('compatibility list has no releases');
        }

        $releases = array_map(self::parseRelease(...), array_values($data['releases']));
        usort($releases, static fn (array $a, array $b): int => version_compare($b['version'], $a['version']));

        return $releases;
    }

    /**
     * Splits the releases into the entry of the installed version and the newer ones.
     *
     * @param list<array{version: string, date: string, iredmail: list<string>, backends: list<string>}> $releases
     * @return array{current: ?array, newer: list<array>}
     */
    public static function forVersion(array $releases, string $installedVersion): array
    {
        $current = null;
        $newer = [];
        foreach ($releases as $release) {
            if ($release['version'] === $installedVersion) {
                $current = $release;
            } elseif (version_compare($release['version'], $installedVersion, '>')) {
                $newer[] = $release;
            }
        }

        return ['current' => $current, 'newer' => $newer];
    }

    /**
     * The newest release that is newer than the installed one, for the sidebar badge.
     * Returns null when the update check is off, when the list cannot be read, or
     * when no newer release exists. It never throws, because every page calls it.
     */
    public static function newerVersion(string $installedVersion): ?string
    {
        if (!Settings::getInstance()->checkUpdates) {
            return null;
        }

        $report = self::report($installedVersion);

        return $report['newer'][0]['version'] ?? null;
    }

    /**
     * @return array{version: string, date: string, iredmail: list<string>, backends: list<string>}
     */
    private static function parseRelease(mixed $release): array
    {
        if (!is_array($release)) {
            throw new \InvalidArgumentException('release entry must be an object');
        }
        $version = self::versionString($release['version'] ?? null, 'version');
        $iredmail = self::versionList($release['iredmail'] ?? null, $version);
        $date = $release['date'] ?? '';
        if (!is_string($date) || ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1)) {
            throw new \InvalidArgumentException("release {$version}: date must be YYYY-MM-DD");
        }
        $backends = $release['backends'] ?? self::BACKENDS;
        if (!is_array($backends) || array_diff($backends, self::BACKENDS) !== []) {
            throw new \InvalidArgumentException("release {$version}: unknown backend");
        }

        return ['version' => $version, 'date' => $date, 'iredmail' => $iredmail, 'backends' => array_values($backends)];
    }

    /**
     * @return list<string>
     */
    private static function versionList(mixed $versions, string $release): array
    {
        if (!is_array($versions)) {
            throw new \InvalidArgumentException("release {$release}: iredmail must be a list");
        }
        $list = array_map(static fn (mixed $v): string => self::versionString($v, "release {$release} iredmail"), array_values($versions));
        usort($list, static fn (string $a, string $b): int => version_compare($b, $a));

        return $list;
    }

    private static function versionString(mixed $value, string $field): string
    {
        if (!is_string($value) || preg_match(self::VERSION_PATTERN, $value) !== 1) {
            throw new \InvalidArgumentException("{$field}: invalid version number");
        }

        return $value;
    }

    /**
     * @return list<array{version: string, date: string, iredmail: list<string>, backends: list<string>}>
     */
    private static function localReleases(): array
    {
        $file = dirname(__DIR__, 2) . '/compatibility.json';
        $json = is_file($file) ? file_get_contents($file) : false;
        if ($json === false) {
            throw new \RuntimeException("Cannot read {$file}");
        }

        return self::parse($json);
    }

    /**
     * Downloads the list from GitHub. The JSON is kept only when it is valid.
     *
     * @return array{json: ?string, checkedAt: int}
     */
    private static function download(): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: iRedPanel\r\nAccept: application/json\r\n",
                'timeout' => 5,
            ],
        ]);

        $json = @file_get_contents(self::REMOTE_URL, false, $context);
        if ($json === false) {
            error_log('iRedPanel: cannot download the compatibility list: ' . (error_get_last()['message'] ?? 'unknown error'));
            return ['json' => null, 'checkedAt' => time()];
        }

        try {
            self::parse($json);
        } catch (\InvalidArgumentException $e) {
            error_log('iRedPanel: the downloaded compatibility list is invalid: ' . $e->getMessage());
            return ['json' => null, 'checkedAt' => time()];
        }

        return ['json' => $json, 'checkedAt' => time()];
    }

    /**
     * @return ?list<array{version: string, date: string, iredmail: list<string>, backends: list<string>}>
     */
    private static function remoteReleases(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }
        try {
            return self::parse($json);
        } catch (\InvalidArgumentException $e) {
            error_log('iRedPanel: the cached compatibility list is invalid: ' . $e->getMessage());
            return null;
        }
    }

    private static function cacheFile(): string
    {
        return sys_get_temp_dir() . '/iredpanel_compatibility.json';
    }

    /**
     * Returns the cached download, or null when there is none or it has expired.
     * The cached JSON is validated again, because the temp directory is shared.
     *
     * @return array{json: ?string, checkedAt: int}|null
     */
    private static function readCache(string $file): ?array
    {
        $raw = is_file($file) ? file_get_contents($file) : false;
        $data = $raw === false ? null : json_decode($raw, true);
        if (!is_array($data) || !is_int($data['checkedAt'] ?? null) || !array_key_exists('json', $data)) {
            return null;
        }
        $json = is_string($data['json']) ? $data['json'] : null;
        if (time() - $data['checkedAt'] > ($json === null ? self::FAILURE_TTL : self::CACHE_TTL)) {
            return null;
        }

        return ['json' => $json, 'checkedAt' => $data['checkedAt']];
    }

    /**
     * @param array{json: ?string, checkedAt: int} $cache
     */
    private static function writeCache(string $file, array $cache): void
    {
        if (file_put_contents($file, json_encode($cache, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            error_log("iRedPanel: cannot write the compatibility cache {$file}");
        }
    }
}
