<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\TemplateEngine;

class PanelSettingsController
{
    /**
     * Setting categories for the UI tabs.
     * Each category maps to a list of setting keys from Settings::OVERRIDABLE_KEYS.
     */
    private const CATEGORIES = [
        'branding' => [
            'brandName', 'brandLogoUrl', 'brandFooterText', 'brandPrimaryColor',
        ],
        'password' => [
            'passwordMinLength', 'passwordIncludesSpecialChars', 'passwordIncludesNumbers',
            'passwordIncludesLowercase', 'passwordIncludesUppercase',
            'passwordHashesUsePrefixedScheme', 'passwordDefaultScheme',
            'requireOldPasswordOnChange',
        ],
        'session' => [
            'sessionTimeout', 'sessionValidateIp', 'allowedIpRanges',
        ],
        'display' => [
            'defaultLanguage', 'paginationPerPage', 'checkUpdates', 'requireDomainOwnershipVerification',
        ],
        'integrations' => [
            'amavisdEnabled', 'amavisdRemoveQuarantinedInDays', 'amavisdRemoveMaillogInDays',
            'fail2banEnabled', 'fail2banSocket', 'fail2banJails',
            'iredapdEnabled', 'geoIpDbPath',
        ],
        'api' => [
            'apiEnabled', 'apiKey', 'apiAllowedIps',
        ],
    ];

    public static function view(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $settings = Settings::getInstance();
        $activeTab = $_GET['tab'] ?? 'branding';
        if (!isset(self::CATEGORIES[$activeTab])) {
            $activeTab = 'branding';
        }

        $repo = RepositoryFactory::getPanelSettingsRepository();
        $repo->ensureTableExists();
        $dbSettings = $repo->getAll();

        $categoryTitles = [];
        foreach (array_keys(self::CATEGORIES) as $category) {
            $categoryTitles[$category] = Translator::translate("panelset.cat_{$category}");
        }
        $labels = [];
        foreach (self::CATEGORIES[$activeTab] as $key) {
            $labels[$key] = Translator::translate("panelset.label_{$key}");
        }

        $tpl->render('panelSettings.php', [
            'categories' => self::CATEGORIES,
            'categoryTitles' => $categoryTitles,
            'labels' => $labels,
            'overridableKeys' => Settings::OVERRIDABLE_KEYS,
            'settings' => $settings,
            'dbSettings' => $dbSettings,
            'activeTab' => $activeTab,
            'allowedSchemes' => Settings::ALLOWED_SCHEMES,
            'availableLocales' => \App\I18n\Translator::availableLocales(),
        ]);
    }

    public static function save(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $category = $_POST['category'] ?? '';
        if (!isset(self::CATEGORIES[$category])) {
            header('Location: /panel-settings');
            exit;
        }

        $toSave = [];
        $rejected = [];
        foreach (self::CATEGORIES[$category] as $key) {
            $value = self::normalize($key, Settings::OVERRIDABLE_KEYS[$key], $_POST[$key] ?? null);
            if ($value === null) {
                $rejected[] = Translator::translate("panelset.label_{$key}");
            } else {
                $toSave[$key] = $value;
            }
        }

        $errors = [];
        if ($rejected !== []) {
            $errors[] = Translator::translate('panelset.msg_invalid', ['fields' => implode(', ', $rejected)]);
        }
        // Saving ranges that exclude the admin's own address would lock the admin out.
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if (isset($toSave['allowedIpRanges']) && !Middleware::isIpAllowed($clientIp, $toSave['allowedIpRanges'])) {
            unset($toSave['allowedIpRanges']);
            $errors[] = Translator::translate('panelset.msg_ip_lockout', ['ip' => $clientIp]);
        }
        if ($errors !== []) {
            BaseController::flashError(implode(' ', $errors));
        }

        if ($toSave !== []) {
            $admin = $_SESSION['email'] ?? 'system';
            $repo = RepositoryFactory::getPanelSettingsRepository();
            $repo->ensureTableExists();
            $repo->setMany($toSave, $admin);
            Settings::invalidateCache();

            ActivityLogger::log(
                'update',
                '',
                $admin,
                "Panel settings updated: {$category} (" . implode(', ', array_keys($toSave)) . ')'
            );
            BaseController::flashSuccess(Translator::translate('panelset.msg_saved'));
        }

        header("Location: /panel-settings?tab={$category}");
        exit;
    }

    /** Lowest accepted value of the integer settings; the others accept 0. */
    private const INT_MINIMUMS = ['sessionTimeout' => 60, 'passwordMinLength' => 1, 'paginationPerPage' => 1];

    /** Patterns of the string settings that may also stay empty. */
    private const OPTIONAL_PATTERNS = [
        'fail2banSocket' => '#^(/[a-zA-Z0-9._/-]+)$#',
        'brandPrimaryColor' => Settings::COLOR_PATTERN,
        // An http(s) URL or a local path; "//host" and "/\host" would load from another host.
        'brandLogoUrl' => '#^(https?://[^\s"\'<>]+|/(?![/\\\\])[^\s"\'<>]*)$#',
    ];

    /**
     * Returns the value to store for a submitted setting, or null when the
     * submitted value is invalid.
     */
    private static function normalize(string $key, string $type, mixed $submitted): ?string
    {
        if ($type === 'bool') {
            return $submitted !== null ? 'true' : 'false';
        }
        if ($submitted !== null && !is_string($submitted)) {
            return null;
        }
        $value = trim($submitted ?? '');
        if ($type === 'int') {
            $minimum = self::INT_MINIMUMS[$key] ?? 0;
            return ctype_digit($value) && (int) $value >= $minimum ? (string) (int) $value : null;
        }
        return self::normalizeString($key, $value);
    }

    private static function normalizeString(string $key, string $value): ?string
    {
        $valid = match ($key) {
            'passwordDefaultScheme' => in_array(strtoupper($value), Settings::ALLOWED_SCHEMES, true),
            'defaultLanguage' => Translator::isSupported($value),
            'fail2banJails' => self::allMatch(self::splitList($value), '/^[a-zA-Z0-9_-]+$/'),
            'allowedIpRanges', 'apiAllowedIps' => self::allValidIpRanges(self::splitList($value)),
            'geoIpDbPath' => $value === '' || (str_ends_with($value, '.mmdb') && !str_contains($value, '..')),
            default => !isset(self::OPTIONAL_PATTERNS[$key]) || $value === ''
                || preg_match(self::OPTIONAL_PATTERNS[$key], $value) === 1,
        };
        if (!$valid) {
            return null;
        }
        return match ($key) {
            'passwordDefaultScheme' => strtoupper($value),
            'fail2banJails', 'allowedIpRanges', 'apiAllowedIps' => implode(',', self::splitList($value)),
            default => $value,
        };
    }

    /** @return string[] the non-empty, trimmed entries of a comma-separated list */
    private static function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn(string $entry) => $entry !== ''));
    }

    /** @param string[] $entries */
    private static function allMatch(array $entries, string $pattern): bool
    {
        foreach ($entries as $entry) {
            if (preg_match($pattern, $entry) !== 1) {
                return false;
            }
        }
        return true;
    }

    /** @param string[] $entries */
    private static function allValidIpRanges(array $entries): bool
    {
        foreach ($entries as $entry) {
            if (!Middleware::isValidIpRange($entry)) {
                return false;
            }
        }
        return true;
    }
}
