<?php

declare(strict_types=1);

namespace App\Utils;

use App\Exceptions\InvalidInputException;

/**
 * Time-based one-time passwords (RFC 6238) with the parameters that every
 * authenticator app uses by default: SHA-1, 6 digits and a 30 second period.
 */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;

    /** A code of the step before and after the current one is accepted, for clock drift. */
    public const WINDOW = 1;

    /** RFC 4648 base32, the alphabet that the otpauth URI carries. */
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * A new base32 secret. 20 bytes is the length that RFC 4226 recommends.
     */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::encodeBase32(random_bytes($bytes));
    }

    /**
     * The code of the step that holds the given Unix time.
     *
     * @throws InvalidInputException when the secret is not base32
     */
    public static function code(string $secret, int $time): string
    {
        $counter = intdiv($time, self::PERIOD);
        $hash = hash_hmac('sha1', pack('J', $counter), self::decodeBase32($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((int) unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Whether the code belongs to the current step or to a neighbouring one.
     *
     * @throws InvalidInputException when the secret is not base32
     */
    public static function verify(string $secret, string $code, int $now): bool
    {
        $digits = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($digits) !== self::DIGITS) {
            return false;
        }

        for ($step = -self::WINDOW; $step <= self::WINDOW; $step++) {
            if (hash_equals(self::code($secret, $now + $step * self::PERIOD), $digits)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The otpauth URI that an authenticator app reads from the QR code.
     */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    /**
     * The secret in groups of four characters, so that it can be typed by hand.
     */
    public static function readable(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    public static function encodeBase32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    /**
     * @throws InvalidInputException when the value holds a character outside the alphabet
     */
    public static function decodeBase32(string $secret): string
    {
        $clean = strtoupper(str_replace([' ', '='], '', $secret));
        if ($clean === '') {
            throw new InvalidInputException('TOTP secret is empty', 'totp.msg_invalid_secret');
        }

        $bits = '';
        foreach (str_split($clean) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                throw new InvalidInputException('TOTP secret is not base32', 'totp.msg_invalid_secret');
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
