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
use App\Utils\SecretBox;

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
            'requireOldPasswordOnChange', 'passwordRecoveryEnabled',
        ],
        'session' => [
            'sessionTimeout', 'sessionValidateIp', 'allowedIpRanges', 'adminTotpRequired',
        ],
        'display' => [
            'defaultLanguage', 'paginationPerPage', 'checkUpdates', 'requireDomainOwnershipVerification',
        ],
        'integrations' => [
            'activityLoggingEnabled',
            'amavisdEnabled', 'amavisdRemoveQuarantinedInDays', 'amavisdRemoveMaillogInDays',
            'amavisdQuarantineHost', 'amavisdQuarantinePort',
            'fail2banEnabled', 'fail2banSocket', 'fail2banJails',
            'iredapdEnabled', 'geoIpDbPath',
            'mlmmjadminApiUrl', 'mlmmjadminApiToken',
        ],
        'mail' => [
            'smtpHost', 'smtpPort', 'smtpSecurity', 'smtpTlsVerify',
            'smtpUser', 'smtpPassword', 'smtpFrom', 'publicUrl', 'newsletterExpireHours',
            'dkimSelector',
        ],
        'api' => [
            'apiEnabled', 'apiKey', 'apiAllowedIps',
        ],
    ];

    /**
     * Settings whose value is never written back to the page. The form sends an
     * empty field when the admin does not change them, and a checkbox clears them.
     */
    public const SECRET_KEYS = ['apiKey', 'smtpPassword', 'mlmmjadminApiToken'];

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

        $secretStored = [];
        foreach (self::SECRET_KEYS as $key) {
            $secretStored[$key] = $settings->$key !== '';
        }

        $tpl->render('panelSettings.php', [
            'categories' => self::CATEGORIES,
            'secretKeys' => self::SECRET_KEYS,
            'secretStored' => $secretStored,
            'categoryTitles' => $categoryTitles,
            'labels' => $labels,
            'overridableKeys' => Settings::OVERRIDABLE_KEYS,
            'settings' => $settings,
            'dbSettings' => $dbSettings,
            'activeTab' => $activeTab,
            'allowedSchemes' => Settings::ALLOWED_SCHEMES,
            'smtpSecurityModes' => Settings::SMTP_SECURITY_MODES,
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

        ['values' => $toSave, 'rejected' => $rejected] = self::submitted($category);

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

    /**
     * The values of one category as the form submitted them, and the labels of the
     * values that no rule accepts.
     *
     * @return array{values: array<string, string>, rejected: list<string>}
     */
    private static function submitted(string $category): array
    {
        $values = [];
        $rejected = [];
        foreach (self::CATEGORIES[$category] as $key) {
            $type = Settings::OVERRIDABLE_KEYS[$key];
            $value = in_array($key, self::SECRET_KEYS, true)
                ? self::submittedSecret($key, $type)
                : self::normalize($key, $type, $_POST[$key] ?? null);

            if ($value === self::SECRET_UNCHANGED) {
                continue;
            }
            if ($value !== null && in_array($key, Settings::ENCRYPTED_KEYS, true)) {
                $value = self::encrypted($value);
            }
            if ($value === null) {
                $rejected[] = Translator::translate("panelset.label_{$key}");
                continue;
            }
            $values[$key] = $value;
        }

        return ['values' => $values, 'rejected' => $rejected];
    }

    /**
     * The value as panel_settings stores it for an encrypted setting, or null when
     * this installation cannot encrypt, which would otherwise write the secret in
     * plaintext. An empty value clears the row and needs no key.
     */
    private static function encrypted(string $value): ?string
    {
        if ($value === '') {
            return '';
        }

        try {
            return SecretBox::fromSettings()->encrypt($value);
        } catch (\Throwable $e) {
            error_log('iRedPanel: cannot encrypt a panel setting: ' . $e->getMessage());
            return null;
        }
    }

    /** Marks a secret field that the admin left empty, so the stored value stays. */
    private const SECRET_UNCHANGED = "\0unchanged";

    /**
     * An empty secret field keeps the stored value, because the form never shows it.
     * The clear checkbox is the only way to remove one.
     */
    private static function submittedSecret(string $key, string $type): ?string
    {
        if (isset($_POST[$key . '_clear'])) {
            return '';
        }
        $submitted = $_POST[$key] ?? '';
        if (!is_string($submitted) || trim($submitted) === '') {
            return self::SECRET_UNCHANGED;
        }

        return self::normalize($key, $type, $submitted);
    }

    /** Lowest accepted value of the integer settings; the others accept 0. */
    private const INT_MINIMUMS = [
        'sessionTimeout' => 60,
        'passwordMinLength' => 1,
        'paginationPerPage' => 1,
        'newsletterExpireHours' => 1,
        'smtpPort' => 1,
        'amavisdQuarantinePort' => 1,
    ];

    /** Highest accepted value of the integer settings that name a TCP port. */
    private const INT_MAXIMUMS = ['smtpPort' => 65535, 'amavisdQuarantinePort' => 65535];

    /** Patterns of the string settings that may also stay empty. */
    private const OPTIONAL_PATTERNS = [
        'fail2banSocket' => '#^(/[a-zA-Z0-9._/-]+)$#',
        'brandPrimaryColor' => Settings::COLOR_PATTERN,
        // An http(s) URL or a local path; "//host" and "/\host" would load from another host.
        'brandLogoUrl' => '#^(https?://[^\s"\'<>]+|/(?![/\\\\])[^\s"\'<>]*)$#',
        'publicUrl' => Settings::PUBLIC_URL_PATTERN,
        'mlmmjadminApiUrl' => Settings::PUBLIC_URL_PATTERN,
        'smtpHost' => Settings::HOST_PATTERN,
        'amavisdQuarantineHost' => Settings::HOST_PATTERN,
        'smtpFrom' => '/^[^@\s]+@[^@\s]+\.[^@\s]+$/',
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
            $maximum = self::INT_MAXIMUMS[$key] ?? PHP_INT_MAX;
            $valid = ctype_digit($value) && (int) $value >= $minimum && (int) $value <= $maximum;
            return $valid ? (string) (int) $value : null;
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
            'smtpSecurity' => in_array(strtolower($value), Settings::SMTP_SECURITY_MODES, true),
            default => !isset(self::OPTIONAL_PATTERNS[$key]) || $value === ''
                || preg_match(self::OPTIONAL_PATTERNS[$key], $value) === 1,
        };
        if (!$valid) {
            return null;
        }
        return match ($key) {
            'passwordDefaultScheme' => strtoupper($value),
            'smtpSecurity' => strtolower($value),
            'publicUrl', 'mlmmjadminApiUrl' => rtrim($value, '/'),
            'fail2banJails', 'allowedIpRanges', 'apiAllowedIps' => implode(',', self::splitList($value)),
            default => $value,
        };
    }

    /** @return string[] the non-empty, trimmed entries of a comma-separated list */
    private static function splitList(string $value): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', $value)), fn(string $entry) => $entry !== ''));
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
