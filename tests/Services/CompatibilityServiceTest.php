<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\CompatibilityService;
use PHPUnit\Framework\TestCase;

class CompatibilityServiceTest extends TestCase
{
    private const LIST = <<<'JSON'
        {
          "schema": 1,
          "releases": [
            {"version": "1.0.2", "date": "2026-07-26", "iredmail": ["1.7.4"], "backends": ["ldap", "mysql", "pgsql"]},
            {"version": "1.1.0", "date": "2026-10-01", "iredmail": ["1.7.4", "1.8.8"]},
            {"version": "1.0.1", "date": "2026-07-25", "iredmail": ["1.7.4"]}
          ]
        }
        JSON;

    public function testBundledListIsValid(): void
    {
        // The dashboard falls back to this file, so a broken edit must fail the build.
        $releases = CompatibilityService::parse((string) file_get_contents(dirname(__DIR__, 2) . '/compatibility.json'));

        $this->assertNotEmpty($releases);
    }

    public function testBundledListCoversTheComposerVersion(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);
        $releases = CompatibilityService::parse((string) file_get_contents(dirname(__DIR__, 2) . '/compatibility.json'));

        $this->assertContains($composer['version'], array_column($releases, 'version'), 'Add the new version to compatibility.json.');
    }

    public function testReleasesAreSortedNewestFirst(): void
    {
        $releases = CompatibilityService::parse(self::LIST);

        $this->assertSame(['1.1.0', '1.0.2', '1.0.1'], array_column($releases, 'version'));
        $this->assertSame(['1.8.8', '1.7.4'], $releases[0]['iredmail']);
    }

    public function testMissingBackendsMeanAllBackends(): void
    {
        $releases = CompatibilityService::parse(self::LIST);

        $this->assertSame(['ldap', 'mysql', 'pgsql'], $releases[0]['backends']);
    }

    public function testForVersionSplitsCurrentAndNewerReleases(): void
    {
        $result = CompatibilityService::forVersion(CompatibilityService::parse(self::LIST), '1.0.2');

        $this->assertSame('1.0.2', $result['current']['version']);
        $this->assertSame(['1.1.0'], array_column($result['newer'], 'version'));
    }

    public function testUnlistedVersionHasNoCurrentEntry(): void
    {
        $result = CompatibilityService::forVersion(CompatibilityService::parse(self::LIST), '1.0.3');

        $this->assertNull($result['current']);
        $this->assertSame(['1.1.0'], array_column($result['newer'], 'version'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidLists(): array
    {
        return [
            'not json' => ['<html>'],
            'no releases' => ['{"releases": []}'],
            'markup in version' => ['{"releases": [{"version": "1.0<script>", "iredmail": ["1.7.4"]}]}'],
            'iredmail not a list' => ['{"releases": [{"version": "1.0.2", "iredmail": "1.7.4"}]}'],
            'markup in iredmail' => ['{"releases": [{"version": "1.0.2", "iredmail": ["1.7.4\""]}]}'],
            'bad date' => ['{"releases": [{"version": "1.0.2", "date": "26.07.2026", "iredmail": ["1.7.4"]}]}'],
            'unknown backend' => ['{"releases": [{"version": "1.0.2", "iredmail": ["1.7.4"], "backends": ["oracle"]}]}'],
        ];
    }

    /**
     * The list comes from the network, so anything outside the format is refused.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLists')]
    public function testInvalidListIsRefused(string $json): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CompatibilityService::parse($json);
    }
}
