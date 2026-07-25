<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
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

    /**
     * Human-readable labels for setting keys.
     */
    private const LABELS = [
        'brandName' => 'Panel Name',
        'brandLogoUrl' => 'Logo URL',
        'brandFooterText' => 'Footer Text',
        'brandPrimaryColor' => 'Primary Color (CSS)',
        'passwordMinLength' => 'Minimum Password Length',
        'passwordIncludesSpecialChars' => 'Require Special Characters',
        'passwordIncludesNumbers' => 'Require Numbers',
        'passwordIncludesLowercase' => 'Require Lowercase',
        'passwordIncludesUppercase' => 'Require Uppercase',
        'passwordHashesUsePrefixedScheme' => 'Use {SCHEME} Prefix in Hashes',
        'passwordDefaultScheme' => 'Default Hashing Scheme',
        'requireOldPasswordOnChange' => 'Require Old Password on Change',
        'sessionTimeout' => 'Session Timeout (seconds)',
        'sessionValidateIp' => 'Invalidate Session on IP Change',
        'allowedIpRanges' => 'Allowed IP Ranges (CIDR)',
        'defaultLanguage' => 'Default Language',
        'paginationPerPage' => 'Items Per Page',
        'checkUpdates' => 'Check for Updates on Dashboard',
        'requireDomainOwnershipVerification' => 'Require Domain Ownership Verification',
        'amavisdEnabled' => 'Amavisd Integration',
        'amavisdRemoveQuarantinedInDays' => 'Quarantine Retention (days)',
        'amavisdRemoveMaillogInDays' => 'Mail Log Retention (days)',
        'fail2banEnabled' => 'Fail2ban Integration',
        'fail2banSocket' => 'Fail2ban Socket Path',
        'fail2banJails' => 'Fail2ban Jails (comma-separated)',
        'iredapdEnabled' => 'iRedAPD Integration',
        'geoIpDbPath' => 'GeoIP Database Path (.mmdb)',
        'apiEnabled' => 'REST API',
        'apiKey' => 'Legacy API Key',
        'apiAllowedIps' => 'API Allowed IPs (comma-separated)',
    ];

    private const CATEGORY_TITLES = [
        'branding' => 'Branding',
        'password' => 'Password Policy',
        'session' => 'Session & Security',
        'display' => 'Display & Behavior',
        'integrations' => 'Integrations',
        'api' => 'REST API',
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

        $tpl->render('panelSettings.php', [
            'categories' => self::CATEGORIES,
            'categoryTitles' => self::CATEGORY_TITLES,
            'labels' => self::LABELS,
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

        $keys = self::CATEGORIES[$category];
        $repo = RepositoryFactory::getPanelSettingsRepository();
        $repo->ensureTableExists();
        $admin = $_SESSION['email'] ?? 'system';

        $toSave = [];
        foreach ($keys as $key) {
            $type = Settings::OVERRIDABLE_KEYS[$key] ?? null;
            if ($type === null) {
                continue;
            }

            if ($type === 'bool') {
                $toSave[$key] = isset($_POST[$key]) ? 'true' : 'false';
            } elseif ($type === 'int') {
                $value = max(0, (int) ($_POST[$key] ?? '0'));
                if ($key === 'sessionTimeout') {
                    $value = max(60, $value);
                }
                if ($key === 'passwordMinLength') {
                    $value = max(1, $value);
                }
                if ($key === 'paginationPerPage') {
                    $value = max(1, $value);
                }
                $toSave[$key] = (string) $value;
            } else {
                $value = trim($_POST[$key] ?? '');
                if ($key === 'passwordDefaultScheme') {
                    $value = strtoupper($value);
                    if (!in_array($value, Settings::ALLOWED_SCHEMES, true)) {
                        continue;
                    }
                }
                if ($key === 'defaultLanguage' && !\App\I18n\Translator::isSupported($value)) {
                    continue;
                }
                if ($key === 'fail2banJails') {
                    $jails = array_filter(array_map('trim', explode(',', $value)));
                    foreach ($jails as $jail) {
                        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $jail)) {
                            continue 2;
                        }
                    }
                    $value = implode(',', $jails);
                }
                if ($key === 'fail2banSocket' && $value !== '') {
                    if (!preg_match('#^(/[a-zA-Z0-9._/-]+)$#', $value)) {
                        continue;
                    }
                }
                if ($key === 'brandPrimaryColor' && $value !== '') {
                    if (!preg_match('/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]+|rgba?\(\s*[\d.,\s\/]+\)|hsla?\(\s*[\d.,%\s\/]+\))$/', $value)) {
                        continue;
                    }
                }
                if ($key === 'geoIpDbPath' && $value !== '') {
                    if (!str_ends_with($value, '.mmdb') || str_contains($value, '..')) {
                        continue;
                    }
                }
                $toSave[$key] = $value;
            }
        }

        if (!empty($toSave)) {
            $repo->setMany($toSave, $admin);
            Settings::invalidateCache();

            ActivityLogger::log(
                'update',
                '',
                $admin,
                "Panel settings updated: {$category} (" . implode(', ', array_keys($toSave)) . ')'
            );
        }

        $_SESSION['flash_success'] = 'Settings saved successfully.';
        header("Location: /panel-settings?tab={$category}");
        exit;
    }
}
