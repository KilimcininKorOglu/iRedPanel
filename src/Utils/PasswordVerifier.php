<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Verifies plaintext passwords against stored hashes.
 * Supports all iRedMail password hash formats.
 */
class PasswordVerifier
{
    /**
     * The prefixed schemes, longest prefix first so that {PLAIN-MD5} is not read as {PLAIN}.
     */
    private const PREFIXED_SCHEMES = ['{CRYPT}', '{SSHA512}', '{SSHA}', '{SHA512}', '{PLAIN-MD5}', '{PLAIN}'];

    public static function verify(string $password, string $storedHash): bool
    {
        foreach (self::PREFIXED_SCHEMES as $prefix) {
            if (str_starts_with($storedHash, $prefix)) {
                return self::verifyPrefixed($prefix, $password, substr($storedHash, strlen($prefix)));
            }
        }

        if (str_starts_with($storedHash, '$2')) {
            return password_verify($password, $storedHash);
        }

        if (preg_match('/^[a-f0-9]{32}$/i', $storedHash) === 1) {
            return hash_equals($storedHash, md5($password));
        }

        return hash_equals($storedHash, $password);
    }

    /**
     * @param string $value the stored hash without its scheme prefix
     */
    private static function verifyPrefixed(string $prefix, string $password, string $value): bool
    {
        return match ($prefix) {
            '{CRYPT}' => hash_equals(@crypt($password, $value), $value),
            '{SSHA512}' => self::verifySalted($password, $value, 'sha512', 64),
            '{SSHA}' => self::verifySalted($password, $value, 'sha1', 20),
            '{SHA512}' => hash_equals((string) base64_decode($value), hash('sha512', $password, true)),
            '{PLAIN-MD5}' => hash_equals($value, md5($password)),
            '{PLAIN}' => $password === $value,
            default => throw new \LogicException("Unknown password scheme: {$prefix}"),
        };
    }

    /**
     * Verifies a base64 blob that holds the hash followed by its salt.
     *
     * @param int $hashLength the byte length of the raw hash in front of the salt
     */
    private static function verifySalted(string $password, string $encoded, string $algo, int $hashLength): bool
    {
        $decoded = (string) base64_decode($encoded);
        $salt = substr($decoded, $hashLength);

        return hash_equals(substr($decoded, 0, $hashLength), hash($algo, $password . $salt, true));
    }
}
