<?php

declare(strict_types=1);

namespace App\I18n;

/**
 * JSON-backed translation store. Static singleton, loaded once per request.
 * Translations live in locales/{locale}.json with nested-namespace keys
 * accessed via dot notation, e.g. $t('nav.dashboard').
 */
class Translator
{
    /** Supported locales mapped to their display names (used by the language switcher). */
    public const AVAILABLE = [
        'en_US' => 'English',
        'tr_TR' => 'Türkçe',
        'de_DE' => 'Deutsch',
        'es_ES' => 'Español',
        'fr_FR' => 'Français',
        'it_IT' => 'Italiano',
        'pt_PT' => 'Português',
        'nl_NL' => 'Nederlands',
        'ru_RU' => 'Русский',
        'zh_CN' => '简体中文',
        'ja_JP' => '日本語',
        'pl_PL' => 'Polski',
        'uk_UA' => 'Українська',
        'cs_CZ' => 'Čeština',
        'sv_SE' => 'Svenska',
        'el_GR' => 'Ελληνικά',
        'ro_RO' => 'Română',
        'ko_KR' => '한국어',
        'zh_TW' => '繁體中文',
        'vi_VN' => 'Tiếng Việt',
        'id_ID' => 'Bahasa Indonesia',
        'th_TH' => 'ไทย',
        'da_DK' => 'Dansk',
        'fi_FI' => 'Suomi',
        'nb_NO' => 'Norsk bokmål',
        'hu_HU' => 'Magyar',
        'sk_SK' => 'Slovenčina',
        'bg_BG' => 'Български',
        'pt_BR' => 'Português brasileiro',
        'hr_HR' => 'Hrvatski',
        'sr_RS' => 'Српски',
        'et_EE' => 'Eesti',
        'sl_SI' => 'Slovenščina',
        'ca_ES' => 'Català',
        'az_AZ' => 'Azərbaycan',
        'is_IS' => 'Íslenska',
        'nn_NO' => 'Norsk nynorsk',
        'bs_BA' => 'Bosanski',
        'gl_ES' => 'Galego',
    ];

    /** Base/fallback locale — must contain every key. */
    public const FALLBACK_LOCALE = 'en_US';

    private static string $locale = self::FALLBACK_LOCALE;

    /** Active-locale messages, flattened to dot-notation keys. */
    private static array $messages = [];

    /** Fallback-locale messages, flattened to dot-notation keys. */
    private static array $fallback = [];

    private static bool $initialized = false;

    /** Directory holding the {locale}.json files. Overridable for testing. */
    private static ?string $localeDir = null;

    /**
     * Loads the given locale plus the fallback locale into memory.
     * An unknown locale silently falls back to FALLBACK_LOCALE.
     */
    public static function init(string $locale): void
    {
        if (!isset(self::AVAILABLE[$locale])) {
            $locale = self::FALLBACK_LOCALE;
        }

        self::$locale = $locale;
        self::$fallback = self::loadLocale(self::FALLBACK_LOCALE);
        self::$messages = $locale === self::FALLBACK_LOCALE
            ? self::$fallback
            : self::loadLocale($locale);
        self::$initialized = true;
    }

    /**
     * Translates a dot-notation key. Falls back from active locale to the
     * base locale, then to the key itself. Substitutes :name placeholders
     * from $params. Output is NOT HTML-escaped — escape at the call site.
     *
     * @param array<string,string|int> $params
     */
    public static function translate(string $key, array $params = []): string
    {
        if (!self::$initialized) {
            self::init(self::FALLBACK_LOCALE);
        }

        $message = self::$messages[$key] ?? self::$fallback[$key] ?? $key;

        foreach ($params as $name => $value) {
            $message = str_replace(':' . $name, (string) $value, $message);
        }

        return $message;
    }

    public static function currentLocale(): string
    {
        return self::$locale;
    }

    /** @return array<string,string> locale code => display name */
    public static function availableLocales(): array
    {
        return self::AVAILABLE;
    }

    public static function isSupported(string $locale): bool
    {
        return isset(self::AVAILABLE[$locale]);
    }

    /** Overrides the locale directory (test seam). Pass null to reset. */
    public static function setLocaleDir(?string $dir): void
    {
        self::$localeDir = $dir === null ? null : rtrim($dir, '/');
        self::$initialized = false;
    }

    private static function localeDir(): string
    {
        return self::$localeDir ?? \dirname(__DIR__, 2) . '/locales';
    }

    /**
     * Reads locales/{locale}.json and flattens it to dot-notation keys.
     * On a missing/unreadable/invalid file, logs and returns an empty array
     * so the panel never crashes; fallback resolution takes over.
     *
     * @return array<string,string>
     */
    private static function loadLocale(string $locale): array
    {
        $path = self::localeDir() . '/' . $locale . '.json';
        if (!is_file($path)) {
            error_log("Translator: locale file not found: {$path}");
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            error_log("Translator: could not read locale file: {$path}");
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            error_log("Translator: invalid JSON in locale file: {$path}");
            return [];
        }

        return self::flatten($decoded);
    }

    /**
     * Recursively flattens a nested array into dot-notation keys.
     * Non-scalar leaves are skipped.
     *
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $compound = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $result += self::flatten($value, $compound);
            } elseif (is_scalar($value)) {
                $result[$compound] = (string) $value;
            }
        }
        return $result;
    }
}
