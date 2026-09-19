<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;
use App\I18n\Translator;

/**
 * Password validation model. Returns validation errors as associative array.
 */
class UserPassword
{
    private const SPECIAL_CHARS = [
        '$', '@', '#', '%', '!', '^', '&', '*',
        '(', ')', '-', '_', '+', '=', '{', '}', '[', ']',
    ];

    /**
     * English messages for REST API consumers; the web UI uses the
     * common.msg_password_* translations of the same keys.
     */
    private const ENGLISH = [
        'ascii' => 'Password must contain only ASCII characters',
        'min_length' => 'Password must be at least :min characters long',
        'max_length' => 'Password must be at most :max characters long',
        'digit' => 'Password must contain at least one digit',
        'uppercase' => 'Password must contain at least one uppercase letter',
        'lowercase' => 'Password must contain at least one lowercase letter',
        'special' => 'Password must contain at least one special character (:chars)',
        'mismatch' => 'Password and password confirmation do not match',
    ];

    /** The password schemes that a given password hash may use ("{SSHA512}..."). */
    public const HASH_SCHEMES = [
        'PLAIN', 'CRYPT', 'MD5', 'PLAIN-MD5', 'SHA', 'SSHA', 'SHA512', 'SSHA512',
        'SHA512-CRYPT', 'BCRYPT', 'CRAM-MD5', 'NTLM',
    ];

    /**
     * Checks a given password hash of a new mailbox. The password policy cannot check a hash.
     *
     * @throws InvalidInputException when a password is also given, or the scheme is not supported
     */
    public static function acceptedHash(string $hash, string $password): string
    {
        if ($password !== '') {
            throw new InvalidInputException('password conflicts with passwordHash', 'user.msg_password_or_hash');
        }
        $scheme = preg_match('/^\{([A-Za-z0-9-]+)\}./', $hash, $match) === 1 ? strtoupper($match[1]) : '';
        if (!in_array($scheme, self::HASH_SCHEMES, true) || preg_match('/\s/', $hash) === 1) {
            throw new InvalidInputException(
                'passwordHash must start with a supported scheme: {' . implode('}, {', self::HASH_SCHEMES) . '}',
                'user.msg_invalid_password_hash',
            );
        }

        return $hash;
    }

    /**
     * Validates password and password_repeat fields and returns English messages.
     *
     * A domain minimum length above 0 replaces the global minimum, and a domain
     * maximum length above 0 caps the length.
     *
     * @return array<string, string> Field-name to error-message pairs (empty if valid)
     */
    public static function validate(string $password, string $passwordRepeat, ?DomainSettings $domainSettings = null): array
    {
        return array_map(
            static function (array $violation): string {
                $message = self::ENGLISH[$violation[0]];
                foreach ($violation[1] as $name => $value) {
                    $message = str_replace(':' . $name, $value, $message);
                }
                return $message;
            },
            self::violations($password, $passwordRepeat, $domainSettings),
        );
    }

    /**
     * Same as validate(), with the messages in the current UI language.
     *
     * @return array<string, string> Field-name to error-message pairs (empty if valid)
     */
    public static function validateLocalized(string $password, string $passwordRepeat, ?DomainSettings $domainSettings = null): array
    {
        return array_map(
            static fn(array $violation): string => Translator::translate("common.msg_password_{$violation[0]}", $violation[1]),
            self::violations($password, $passwordRepeat, $domainSettings),
        );
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>}> Field-name to [message key, params] pairs
     */
    private static function violations(string $password, string $passwordRepeat, ?DomainSettings $domainSettings): array
    {
        $settings = Settings::getInstance();
        $minLength = ($domainSettings?->minPasswordLength ?? 0) > 0
            ? $domainSettings->minPasswordLength
            : $settings->passwordMinLength;
        $maxLength = $domainSettings?->maxPasswordLength ?? 0;
        $violations = [];

        $passwordViolation = self::validateSingle($password, $settings, $minLength, $maxLength);
        if ($passwordViolation !== null) {
            $violations['password'] = $passwordViolation;
        }

        // The policy applies to the password field only; the repeat field reports only a mismatch.
        if ($password !== $passwordRepeat) {
            $violations['password_repeat'] = ['mismatch', []];
        }

        return $violations;
    }

    /**
     * @return array{0: string, 1: array<string, string>}|null
     */
    private static function validateSingle(string $password, Settings $settings, int $minLength, int $maxLength): ?array
    {
        if (preg_match('/[^\x20-\x7E]/', $password) === 1) {
            return ['ascii', []];
        }

        if (strlen($password) < $minLength) {
            return ['min_length', ['min' => (string) $minLength]];
        }

        if ($maxLength > 0 && strlen($password) > $maxLength) {
            return ['max_length', ['max' => (string) $maxLength]];
        }

        return self::validateComposition($password, $settings);
    }

    /**
     * @return array{0: string, 1: array<string, string>}|null
     */
    private static function validateComposition(string $password, Settings $settings): ?array
    {
        if ($settings->passwordIncludesNumbers && !preg_match('/\d/', $password)) {
            return ['digit', []];
        }

        if ($settings->passwordIncludesUppercase && !preg_match('/[A-Z]/', $password)) {
            return ['uppercase', []];
        }

        if ($settings->passwordIncludesLowercase && !preg_match('/[a-z]/', $password)) {
            return ['lowercase', []];
        }

        $chars = implode('', self::SPECIAL_CHARS);
        if ($settings->passwordIncludesSpecialChars && strpbrk($password, $chars) === false) {
            return ['special', ['chars' => $chars]];
        }

        return null;
    }
}
