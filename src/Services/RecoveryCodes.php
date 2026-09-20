<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The one-time recovery codes of a two-factor setup. A code is stored as its
 * SHA-256 hash, because it is only ever compared, never shown again.
 */
final class RecoveryCodes
{
    /** How many codes a setup produces. */
    public const COUNT = 10;

    /**
     * New plain codes, in the `a1b2-c3d4` shape that is easy to write down.
     *
     * @return list<string>
     */
    public static function generate(): array
    {
        $codes = [];
        for ($i = 0; $i < self::COUNT; $i++) {
            $raw = bin2hex(random_bytes(4));
            $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4);
        }

        return $codes;
    }

    /**
     * The stored form of a set of plain codes.
     *
     * @param list<string> $codes
     */
    public static function encode(array $codes): string
    {
        return json_encode(array_map(self::hash(...), $codes), JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string> the hashes of a stored value, empty when it is malformed
     */
    public static function decode(string $stored): array
    {
        $decoded = json_decode($stored, true);

        return is_array($decoded) ? array_values(array_map(strval(...), $decoded)) : [];
    }

    /**
     * The hashes without the one that the code matches, or null when no hash matches.
     *
     * @param list<string> $hashes
     * @return list<string>|null
     */
    public static function remove(array $hashes, string $code): ?array
    {
        $used = self::hash($code);
        $left = array_values(array_filter($hashes, static fn(string $hash): bool => !hash_equals($hash, $used)));

        return count($left) === count($hashes) ? null : $left;
    }

    /**
     * A code is compared without its separators and in lower case, so that it
     * may be typed in any shape.
     */
    private static function hash(string $code): string
    {
        return hash('sha256', strtolower(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? ''));
    }
}
