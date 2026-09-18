<?php

declare(strict_types=1);

namespace App\Models;

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
     * Validates password and password_repeat fields.
     *
     * A domain minimum length above 0 replaces the global minimum, and a domain
     * maximum length above 0 caps the length.
     *
     * @return array<string, string> Field-name to error-message pairs (empty if valid)
     */
    public static function validate(string $password, string $passwordRepeat, ?DomainSettings $domainSettings = null): array
    {
        $settings = Settings::getInstance();
        $minLength = ($domainSettings?->minPasswordLength ?? 0) > 0
            ? $domainSettings->minPasswordLength
            : $settings->passwordMinLength;
        $maxLength = $domainSettings?->maxPasswordLength ?? 0;
        $errors = [];

        // Validate password field
        $passwordErrors = self::validateSingle($password, $settings, $minLength, $maxLength);
        if (!empty($passwordErrors)) {
            $errors['password'] = $passwordErrors;
        }

        // Validate password_repeat field
        $repeatErrors = self::validateSingle($passwordRepeat, $settings, $minLength, $maxLength);
        if (!empty($repeatErrors)) {
            $errors['password_repeat'] = $repeatErrors;
        }

        // Check match (only if both pass individual validation or at least password passed)
        if ($password !== $passwordRepeat && !isset($errors['password_repeat'])) {
            $errors['password_repeat'] = 'Password and password confirmation do not match';
        }

        return $errors;
    }

    private static function validateSingle(string $password, Settings $settings, int $minLength, int $maxLength): string
    {
        for ($i = 0; $i < strlen($password); $i++) {
            $ord = ord($password[$i]);
            if ($ord < 32 || $ord > 126) {
                return 'Password must contain only ASCII characters';
            }
        }

        if (strlen($password) < $minLength) {
            return "Password must be at least {$minLength} characters long";
        }

        if ($maxLength > 0 && strlen($password) > $maxLength) {
            return "Password must be at most {$maxLength} characters long";
        }

        return self::validateComposition($password, $settings);
    }

    private static function validateComposition(string $password, Settings $settings): string
    {
        if ($settings->passwordIncludesNumbers && !preg_match('/\d/', $password)) {
            return 'Password must contain at least one digit';
        }

        if ($settings->passwordIncludesUppercase && !preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter';
        }

        if ($settings->passwordIncludesLowercase && !preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter';
        }

        if ($settings->passwordIncludesSpecialChars
            && strpbrk($password, implode('', self::SPECIAL_CHARS)) === false) {
            $chars = implode('', self::SPECIAL_CHARS);
            return "Password must contain at least one special character ($chars)";
        }

        return '';
    }
}
