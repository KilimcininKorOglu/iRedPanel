<?php

declare(strict_types=1);

namespace App\I18n;

use App\Models\Settings;

/**
 * Resolves the active locale from the request context and persists a
 * user choice to a cookie. Resolution order (first supported value wins):
 *   $_SESSION['lang'] -> cookie(iredpanel_lang) -> Settings::defaultLanguage
 *   -> Translator::FALLBACK_LOCALE
 * Every candidate is validated against the supported-locale whitelist, so a
 * tampered cookie or session value can never reach the filesystem layer.
 */
class LocaleResolver
{
    public const COOKIE_NAME = 'iredpanel_lang';

    /** One year, in seconds. */
    private const COOKIE_LIFETIME = 31536000;

    public static function resolve(): string
    {
        $sessionLang = $_SESSION['lang'] ?? null;
        if (is_string($sessionLang) && Translator::isSupported($sessionLang)) {
            return $sessionLang;
        }

        $cookieLang = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (is_string($cookieLang) && Translator::isSupported($cookieLang)) {
            return $cookieLang;
        }

        $default = Settings::getInstance()->defaultLanguage;
        if (Translator::isSupported($default)) {
            return $default;
        }

        return Translator::FALLBACK_LOCALE;
    }

    /**
     * Writes the chosen locale to a cookie. Rejects unsupported locales so a
     * bad value is never persisted. Mirrors the session cookie's SameSite/secure
     * flags. Safe to call before headers are sent.
     */
    public static function persistCookie(string $locale): void
    {
        if (!Translator::isSupported($locale)) {
            return;
        }

        setcookie(self::COOKIE_NAME, $locale, [
            'expires' => time() + self::COOKIE_LIFETIME,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
}
