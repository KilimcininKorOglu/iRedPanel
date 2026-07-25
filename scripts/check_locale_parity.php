<?php

declare(strict_types=1);

/**
 * Verifies every locales/*.json has exact key parity with en_US.json.
 * Usage: php scripts/check_locale_parity.php [locale ...]
 */
$dir = __DIR__ . '/../locales';
$base = json_decode((string) file_get_contents($dir . '/en_US.json'), true, 512, JSON_THROW_ON_ERROR);

$flatten = static function (array $a, string $prefix = '') use (&$flatten): array {
    $out = [];
    foreach ($a as $k => $v) {
        $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
        if (is_array($v)) {
            $out += $flatten($v, $key);
        } else {
            $out[$key] = $v;
        }
    }
    return $out;
};

$baseKeys = $flatten($base);
$targets = $argv;
array_shift($targets);
if ($targets === []) {
    $targets = array_map(
        static fn (string $p): string => basename($p, '.json'),
        glob($dir . '/*.json') ?: []
    );
}

$fail = false;
foreach ($targets as $locale) {
    if ($locale === 'en_US') {
        continue;
    }
    $file = $dir . '/' . $locale . '.json';
    if (!is_file($file)) {
        echo "MISSING FILE: {$locale}.json\n";
        $fail = true;
        continue;
    }
    $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $keys = $flatten($data);
    $missing = array_diff_key($baseKeys, $keys);
    $extra = array_diff_key($keys, $baseKeys);
    if ($missing === [] && $extra === []) {
        echo "OK {$locale}: " . count($keys) . " keys\n";
        continue;
    }
    $fail = true;
    foreach (array_keys($missing) as $k) {
        echo "  [{$locale}] MISSING: {$k}\n";
    }
    foreach (array_keys($extra) as $k) {
        echo "  [{$locale}] EXTRA:   {$k}\n";
    }
}

exit($fail ? 1 : 0);
