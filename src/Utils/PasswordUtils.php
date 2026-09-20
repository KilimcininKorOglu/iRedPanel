<?php

declare(strict_types=1);

namespace App\Utils;

use App\Models\Settings;

class PasswordUtils
{
    private const LOWERCASE_CHARS = 'abcdefghjkmnpqrstuvwxyz';
    private const UPPERCASE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const DIGIT_CHARS = '23456789';
    private const SPECIAL_CHARS = '$@#%!^&*()-_+={}[]';

    /**
     * Generates a random password that complies with the current password policy.
     * Excludes visually confusing characters: 0, O, 1, l, I.
     */
    public static function generateRandomPassword(int $length = 16): string
    {
        $settings = Settings::getInstance();
        $length = max($length, $settings->passwordMinLength);

        $required = [];
        $pool = '';

        if ($settings->passwordIncludesLowercase) {
            $required[] = self::LOWERCASE_CHARS[random_int(0, strlen(self::LOWERCASE_CHARS) - 1)];
            $pool .= self::LOWERCASE_CHARS;
        }
        if ($settings->passwordIncludesUppercase) {
            $required[] = self::UPPERCASE_CHARS[random_int(0, strlen(self::UPPERCASE_CHARS) - 1)];
            $pool .= self::UPPERCASE_CHARS;
        }
        if ($settings->passwordIncludesNumbers) {
            $required[] = self::DIGIT_CHARS[random_int(0, strlen(self::DIGIT_CHARS) - 1)];
            $pool .= self::DIGIT_CHARS;
        }
        if ($settings->passwordIncludesSpecialChars) {
            $required[] = self::SPECIAL_CHARS[random_int(0, strlen(self::SPECIAL_CHARS) - 1)];
            $pool .= self::SPECIAL_CHARS;
        }

        if ($pool === '') {
            $pool = self::LOWERCASE_CHARS . self::UPPERCASE_CHARS . self::DIGIT_CHARS;
        }

        $chars = $required;
        $poolLen = strlen($pool);
        for ($i = count($chars); $i < $length; $i++) {
            $chars[] = $pool[random_int(0, $poolLen - 1)];
        }

        // Fisher-Yates shuffle with cryptographic randomness
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    /**
     * Main entry point for password hashing. Dispatches to the correct scheme.
     */
    public static function generatePasswordHash(string $password, ?string $scheme = null): string
    {
        $settings = Settings::getInstance();
        $password = trim($password);
        $scheme ??= $settings->passwordDefaultScheme;

        return match ($scheme) {
            'BCRYPT' => self::generateBcryptPassword($password),
            'SSHA512' => self::generateSsha512Password($password),
            'SHA512' => self::generateSha512Password($password),
            'SSHA' => self::generateSshaPassword($password),
            'MD5' => '{CRYPT}' . self::generateMd5Password($password),
            'CRAM-MD5' => self::generatePasswordWithDoveadmPw('CRAM-MD5', $password),
            'PLAIN-MD5' => $settings->passwordHashesUsePrefixedScheme
                ? '{PLAIN-MD5}' . self::generatePlainMd5Password($password)
                : self::generatePlainMd5Password($password),
            'NTLM' => self::generatePasswordWithDoveadmPw('NTLM', $password),
            'PLAIN' => $settings->passwordHashesUsePrefixedScheme
                ? '{PLAIN}' . $password
                : $password,
            default => $password,
        };
    }

    public static function generateBcryptPassword(string $p): string
    {
        return '{CRYPT}' . password_hash($p, PASSWORD_BCRYPT);
    }

    public static function generateMd5Password(string $p): string
    {
        $salt = substr(base64_encode(random_bytes(6)), 0, 8);
        // @: crypt() deprecated in PHP 8.0 but no native alternative for MD5-crypt format
        return @crypt($p, '$1$' . $salt . '$');
    }

    public static function generatePlainMd5Password(string $p): string
    {
        return md5(trim($p));
    }

    public static function generateSshaPassword(string $p): string
    {
        $salt = random_bytes(8);
        $hash = sha1($p . $salt, true);
        return '{SSHA}' . base64_encode($hash . $salt);
    }

    public static function generateSha512Password(string $p): string
    {
        $hash = hash('sha512', $p, true);
        return '{SHA512}' . base64_encode($hash);
    }

    public static function generateSsha512Password(string $p): string
    {
        $salt = random_bytes(8);
        $hash = hash('sha512', $p . $salt, true);
        return '{SSHA512}' . base64_encode($hash . $salt);
    }

    /**
     * Generates password hash using external doveadm command.
     * Falls back to SSHA if doveadm is not available.
     */
    public static function generatePasswordWithDoveadmPw(string $scheme, string $password): string
    {
        $settings = Settings::getInstance();
        $scheme = strtoupper($scheme);
        $password = trim($password);

        $escapedScheme = escapeshellarg($scheme);

        $proc = @proc_open(
            "doveadm pw -s {$escapedScheme} 2>/dev/null",
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        if (!is_resource($proc)) {
            error_log("doveadm not available for scheme {$scheme}, falling back to SSHA");
            return self::generateSshaPassword($password);
        }

        fwrite($pipes[0], $password . "\n" . $password . "\n");
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        if ($output === false || trim($output) === '') {
            error_log("doveadm returned empty output for scheme {$scheme}, falling back to SSHA");
            return self::generateSshaPassword($password);
        }

        $pw = trim($output);

        if (!$settings->passwordHashesUsePrefixedScheme) {
            return preg_replace('/^\{' . preg_quote($scheme, '/') . '\}/', '', $pw);
        }

        return $pw;
    }

}
