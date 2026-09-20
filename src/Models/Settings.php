<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Application settings class. Singleton.
 * Settings are read from the .env or .env.prod file, then overridden
 * by values from the panel_settings database table (if available).
 * Only the active backend's connection settings are validated.
 */
class Settings
{
    /** A CSS color value: hex, a named color, rgb(a) or hsl(a). */
    public const COLOR_PATTERN = '/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]+|rgba?\(\s*[\d.,\s\/]+\)|hsla?\(\s*[\d.,%\s\/]+\))$/';

    private static ?self $instance = null;
    private bool $dbOverridesLoaded = false;

    // --- Immutable: must stay in .env (connection, bootstrap) ---
    public readonly string $backend;
    public readonly string $secretKey;

    // iredadmin database settings (for activity logging + panel_settings)
    public readonly bool $activityLoggingEnabled;
    public readonly string $iredadminDbHost;
    public readonly int $iredadminDbPort;
    public readonly string $iredadminDbName;
    public readonly string $iredadminDbUser;
    public readonly string $iredadminDbPassword;

    // Amavisd DB connection
    public readonly string $amavisdDbHost;
    public readonly int $amavisdDbPort;
    public readonly string $amavisdDbName;
    public readonly string $amavisdDbUser;
    public readonly string $amavisdDbPassword;
    // Amavisd AM.PDP socket that releases quarantined messages
    public readonly string $amavisdQuarantineHost;
    public readonly int $amavisdQuarantinePort;

    // iRedAPD DB connection
    public readonly string $iredapdDbHost;
    public readonly int $iredapdDbPort;
    public readonly string $iredapdDbName;
    public readonly string $iredapdDbUser;
    public readonly string $iredapdDbPassword;

    // mlmmjadmin RESTful API, which manages the mlmmj mailing list spool
    public readonly string $mlmmjadminApiUrl;
    public readonly string $mlmmjadminApiToken;

    // Outgoing SMTP for newsletter confirmations and quarantine notifications
    public readonly string $smtpHost;
    public readonly int $smtpPort;
    public readonly string $smtpSecurity;
    public readonly bool $smtpTlsVerify;
    public readonly string $smtpUser;
    public readonly string $smtpPassword;
    public readonly string $smtpFrom;

    // Base URL of the panel used in links sent by mail
    public readonly string $publicUrl;
    public readonly int $newsletterExpireHours;

    // LDAP settings (populated only when backend=ldap)
    public readonly string $ldapUri;
    public readonly string $ldapRootDn;
    public readonly string $ldapUser;
    public readonly string $ldapPassword;
    public readonly bool $ldapTlsVerify;

    // MySQL settings (populated only when backend=mysql)
    public readonly string $mysqlHost;
    public readonly int $mysqlPort;
    public readonly string $mysqlDatabase;
    public readonly string $mysqlUser;
    public readonly string $mysqlPassword;
    public readonly string $vmailPath;
    public readonly string $storageNode;

    // PostgreSQL settings (populated only when backend=pgsql)
    public readonly string $pgsqlHost;
    public readonly int $pgsqlPort;
    public readonly string $pgsqlDatabase;
    public readonly string $pgsqlUser;
    public readonly string $pgsqlPassword;

    // --- Mutable: can be overridden from panel_settings DB table ---

    // Password policy
    public int $passwordMinLength;
    public bool $passwordIncludesSpecialChars;
    public bool $passwordIncludesNumbers;
    public bool $passwordIncludesLowercase;
    public bool $passwordIncludesUppercase;
    public bool $passwordHashesUsePrefixedScheme;
    public string $passwordDefaultScheme;

    // Session & security
    public bool $requireOldPasswordOnChange;
    public int $sessionTimeout;
    public bool $sessionValidateIp;
    public string $allowedIpRanges;

    // Branding
    public string $brandName;
    public string $brandLogoUrl;
    public string $brandFooterText;
    public string $brandPrimaryColor;

    // Display & behavior
    public int $paginationPerPage;
    public bool $checkUpdates;
    public bool $requireDomainOwnershipVerification;
    public string $defaultLanguage;

    // Integration toggles & behavior
    public bool $amavisdEnabled;
    public int $amavisdRemoveQuarantinedInDays;
    public int $amavisdRemoveMaillogInDays;
    public bool $fail2banEnabled;
    public string $fail2banSocket;
    public string $fail2banJails;
    public bool $iredapdEnabled;
    public string $geoIpDbPath;

    // REST API
    public bool $apiEnabled;
    public string $apiKey;
    public string $apiAllowedIps;

    public const ALLOWED_SCHEMES = [
        'PLAIN', 'CRYPT', 'MD5', 'PLAIN-MD5', 'SHA', 'SSHA',
        'SHA512', 'SSHA512', 'SHA512-CRYPT', 'BCRYPT', 'CRAM-MD5', 'NTLM',
    ];

    /**
     * Property names that can be overridden from the panel_settings table.
     * Maps setting_key (DB) → property name + type for casting.
     */
    public const OVERRIDABLE_KEYS = [
        'passwordMinLength' => 'int',
        'passwordIncludesSpecialChars' => 'bool',
        'passwordIncludesNumbers' => 'bool',
        'passwordIncludesLowercase' => 'bool',
        'passwordIncludesUppercase' => 'bool',
        'passwordHashesUsePrefixedScheme' => 'bool',
        'passwordDefaultScheme' => 'string',
        'requireOldPasswordOnChange' => 'bool',
        'sessionTimeout' => 'int',
        'sessionValidateIp' => 'bool',
        'allowedIpRanges' => 'string',
        'brandName' => 'string',
        'brandLogoUrl' => 'string',
        'brandFooterText' => 'string',
        'brandPrimaryColor' => 'string',
        'paginationPerPage' => 'int',
        'checkUpdates' => 'bool',
        'requireDomainOwnershipVerification' => 'bool',
        'defaultLanguage' => 'string',
        'amavisdEnabled' => 'bool',
        'amavisdRemoveQuarantinedInDays' => 'int',
        'amavisdRemoveMaillogInDays' => 'int',
        'fail2banEnabled' => 'bool',
        'fail2banSocket' => 'string',
        'fail2banJails' => 'string',
        'iredapdEnabled' => 'bool',
        'geoIpDbPath' => 'string',
        'apiEnabled' => 'bool',
        'apiKey' => 'string',
        'apiAllowedIps' => 'string',
    ];

    private function __construct()
    {
        // Backend selection
        $this->backend = $this->validBackend();

        $this->secretKey = $this->envRequired('IREDPANEL_SECRET_KEY');
        // The DB-overridable settings are not readonly, so a method may write them.
        $this->loadPasswordPolicy();
        $this->loadPanelSettings();

        // Integration DB default port based on backend
        $defaultDbPort = $this->backend === 'pgsql' ? 5432 : 3306;

        // iredadmin database (optional, for activity logging)
        $this->activityLoggingEnabled = $this->envBool('IREDPANEL_ACTIVITY_LOGGING_ENABLED', true);
        $this->iredadminDbHost = $this->env('IREDPANEL_IREDADMIN_DB_HOST', '');
        $this->iredadminDbPort = $this->envInt('IREDPANEL_IREDADMIN_DB_PORT', $defaultDbPort);
        $this->iredadminDbName = $this->env('IREDPANEL_IREDADMIN_DB_NAME', 'iredadmin');
        $this->iredadminDbUser = $this->env('IREDPANEL_IREDADMIN_DB_USER', '');
        $this->iredadminDbPassword = $this->env('IREDPANEL_IREDADMIN_DB_PASSWORD', '');

        // Amavisd integration
        $this->amavisdEnabled = $this->envBool('IREDPANEL_AMAVISD_ENABLED', false);
        $this->amavisdRemoveQuarantinedInDays = $this->envInt('IREDPANEL_AMAVISD_REMOVE_QUARANTINED_IN_DAYS', 7);
        $this->amavisdRemoveMaillogInDays = $this->envInt('IREDPANEL_AMAVISD_REMOVE_MAILLOG_IN_DAYS', 7);
        $this->amavisdDbHost = $this->env('IREDPANEL_AMAVISD_DB_HOST', '');
        $this->amavisdDbPort = $this->envInt('IREDPANEL_AMAVISD_DB_PORT', $defaultDbPort);
        $this->amavisdDbName = $this->env('IREDPANEL_AMAVISD_DB_NAME', 'amavisd');
        $this->amavisdDbUser = $this->env('IREDPANEL_AMAVISD_DB_USER', '');
        $this->amavisdDbPassword = $this->env('IREDPANEL_AMAVISD_DB_PASSWORD', '');
        $this->amavisdQuarantineHost = $this->env('IREDPANEL_AMAVISD_QUARANTINE_HOST', $this->amavisdDbHost);
        $this->amavisdQuarantinePort = $this->envInt('IREDPANEL_AMAVISD_QUARANTINE_PORT', 9998);

        // Fail2ban integration
        $this->fail2banEnabled = $this->envBool('IREDPANEL_FAIL2BAN_ENABLED', false);
        $this->fail2banSocket = $this->env('IREDPANEL_FAIL2BAN_SOCKET', '');
        $this->fail2banJails = $this->env('IREDPANEL_FAIL2BAN_JAILS', 'dovecot,postfix,postfix-sasl');

        // iRedAPD integration
        $this->iredapdEnabled = $this->envBool('IREDPANEL_IREDAPD_ENABLED', false);
        $this->iredapdDbHost = $this->env('IREDPANEL_IREDAPD_DB_HOST', '');
        $this->iredapdDbPort = $this->envInt('IREDPANEL_IREDAPD_DB_PORT', $defaultDbPort);
        $this->iredapdDbName = $this->env('IREDPANEL_IREDAPD_DB_NAME', 'iredapd');
        $this->iredapdDbUser = $this->env('IREDPANEL_IREDAPD_DB_USER', '');
        $this->iredapdDbPassword = $this->env('IREDPANEL_IREDAPD_DB_PASSWORD', '');

        // mlmmjadmin API, SMTP and public URL. Readonly properties may only be
        // assigned here, so these stay in the constructor.
        $this->mlmmjadminApiUrl = rtrim($this->env('IREDPANEL_MLMMJADMIN_API_URL', ''), '/');
        $this->mlmmjadminApiToken = $this->env('IREDPANEL_MLMMJADMIN_API_TOKEN', '');

        $this->smtpSecurity = $this->validSmtpSecurity();
        $this->smtpHost = $this->env('IREDPANEL_SMTP_HOST', '');
        $this->smtpPort = $this->envInt('IREDPANEL_SMTP_PORT', $this->smtpSecurity === 'tls' ? 465 : 587);
        $this->smtpTlsVerify = $this->envBool('IREDPANEL_SMTP_TLS_VERIFY', true);
        $this->smtpUser = $this->env('IREDPANEL_SMTP_USER', '');
        $this->smtpPassword = $this->env('IREDPANEL_SMTP_PASSWORD', '');
        $this->smtpFrom = $this->env('IREDPANEL_SMTP_FROM', '');

        $this->publicUrl = $this->validPublicUrl();
        $this->newsletterExpireHours = max(1, $this->envInt('IREDPANEL_NEWSLETTER_EXPIRE_HOURS', 24));

        // Conditional backend settings
        if ($this->backend === 'ldap') {
            $this->ldapUri = $this->envRequired('IREDPANEL_LDAP_URI');
            $this->ldapRootDn = $this->envRequired('IREDPANEL_LDAP_ROOT_DN');
            $this->ldapUser = $this->envRequired('IREDPANEL_LDAP_USER');
            $this->ldapPassword = $this->envRequired('IREDPANEL_LDAP_PASSWORD');
            $this->ldapTlsVerify = $this->envBool('IREDPANEL_LDAP_TLS_VERIFY', false);

            if (!str_starts_with($this->ldapUri, 'ldap://') && !str_starts_with($this->ldapUri, 'ldaps://')) {
                throw new \RuntimeException("LDAP URI must start with ldap:// or ldaps://");
            }

            $this->mysqlHost = '';
            $this->mysqlPort = 3306;
            $this->mysqlDatabase = '';
            $this->mysqlUser = '';
            $this->mysqlPassword = '';
            $this->pgsqlHost = '';
            $this->pgsqlPort = 5432;
            $this->pgsqlDatabase = '';
            $this->pgsqlUser = '';
            $this->pgsqlPassword = '';
            $this->vmailPath = '/var/vmail';
            $this->storageNode = 'vmail1';
        } elseif ($this->backend === 'pgsql') {
            $this->pgsqlHost = $this->envRequired('IREDPANEL_PGSQL_HOST');
            $this->pgsqlPort = $this->envInt('IREDPANEL_PGSQL_PORT', 5432);
            $this->pgsqlDatabase = $this->envRequired('IREDPANEL_PGSQL_DATABASE');
            $this->pgsqlUser = $this->envRequired('IREDPANEL_PGSQL_USER');
            $this->pgsqlPassword = $this->envRequired('IREDPANEL_PGSQL_PASSWORD');
            $this->vmailPath = $this->env('IREDPANEL_VMAIL_PATH', '/var/vmail');
            $this->storageNode = $this->env('IREDPANEL_STORAGE_NODE', 'vmail1');

            $this->mysqlHost = '';
            $this->mysqlPort = 3306;
            $this->mysqlDatabase = '';
            $this->mysqlUser = '';
            $this->mysqlPassword = '';
            $this->ldapUri = '';
            $this->ldapRootDn = '';
            $this->ldapUser = '';
            $this->ldapPassword = '';
            $this->ldapTlsVerify = false;
        } else {
            $this->mysqlHost = $this->envRequired('IREDPANEL_MYSQL_HOST');
            $this->mysqlPort = $this->envInt('IREDPANEL_MYSQL_PORT', 3306);
            $this->mysqlDatabase = $this->envRequired('IREDPANEL_MYSQL_DATABASE');
            $this->mysqlUser = $this->envRequired('IREDPANEL_MYSQL_USER');
            $this->mysqlPassword = $this->envRequired('IREDPANEL_MYSQL_PASSWORD');
            $this->vmailPath = $this->env('IREDPANEL_VMAIL_PATH', '/var/vmail');
            $this->storageNode = $this->env('IREDPANEL_STORAGE_NODE', 'vmail1');

            $this->pgsqlHost = '';
            $this->pgsqlPort = 5432;
            $this->pgsqlDatabase = '';
            $this->pgsqlUser = '';
            $this->pgsqlPassword = '';
            $this->ldapUri = '';
            $this->ldapRootDn = '';
            $this->ldapUser = '';
            $this->ldapPassword = '';
            $this->ldapTlsVerify = false;
        }
    }

    /**
     * The password policy of the panel. Every value can be overridden from panel_settings.
     */
    private function loadPasswordPolicy(): void
    {
        $this->passwordMinLength = $this->envInt('IREDPANEL_PASSWORD_MIN_LENGTH', 8);
        $this->passwordIncludesSpecialChars = $this->envBool('IREDPANEL_PASSWORD_INCLUDES_SPECIAL_CHARS', true);
        $this->passwordIncludesNumbers = $this->envBool('IREDPANEL_PASSWORD_INCLUDES_NUMBERS', true);
        $this->passwordIncludesLowercase = $this->envBool('IREDPANEL_PASSWORD_INCLUDES_LOWERCASE', true);
        $this->passwordIncludesUppercase = $this->envBool('IREDPANEL_PASSWORD_INCLUDES_UPPERCASE', true);
        $this->passwordHashesUsePrefixedScheme = $this->envBool('IREDPANEL_PASSWORD_HASHES_USE_PREFIXED_SCHEME', true);
        $this->passwordDefaultScheme = $this->validPasswordScheme();
        $this->requireOldPasswordOnChange = $this->envBool('IREDPANEL_REQUIRE_OLD_PASSWORD_ON_CHANGE', false);
    }

    /**
     * The session, API, display and branding settings. Every value can be overridden
     * from panel_settings.
     */
    private function loadPanelSettings(): void
    {
        $this->sessionTimeout = $this->envInt('IREDPANEL_SESSION_TIMEOUT', 1800);
        $this->sessionValidateIp = $this->envBool('IREDPANEL_SESSION_VALIDATE_IP', false);
        $this->allowedIpRanges = $this->env('IREDPANEL_ALLOWED_IP_RANGES', '');
        $this->apiEnabled = $this->envBool('IREDPANEL_API_ENABLED', false);
        $this->apiKey = $this->env('IREDPANEL_API_KEY', '');
        $this->apiAllowedIps = $this->env('IREDPANEL_API_ALLOWED_IPS', '');
        $this->paginationPerPage = $this->envInt('IREDPANEL_PAGINATION_PER_PAGE', 50);
        $this->checkUpdates = $this->envBool('IREDPANEL_CHECK_UPDATES', true);
        $this->geoIpDbPath = $this->env('IREDPANEL_GEOIP_DB_PATH', '');
        $this->requireDomainOwnershipVerification = $this->envBool('IREDPANEL_REQUIRE_DOMAIN_OWNERSHIP_VERIFICATION', false);
        $this->defaultLanguage = $this->supportedLanguage();
        $this->brandName = $this->env('IREDPANEL_BRAND_NAME', 'iRedPanel');
        $this->brandLogoUrl = $this->env('IREDPANEL_BRAND_LOGO_URL', '/static/logo-iredmail.png');
        $this->brandFooterText = $this->env('IREDPANEL_BRAND_FOOTER_TEXT', '');
        $this->brandPrimaryColor = $this->env('IREDPANEL_BRAND_PRIMARY_COLOR', '');
    }

    /**
     * @throws \RuntimeException when IREDPANEL_BACKEND names no supported backend
     */
    private function validBackend(): string
    {
        $backend = strtolower($this->env('IREDPANEL_BACKEND', 'ldap'));
        if (!in_array($backend, ['ldap', 'mysql', 'pgsql'], true)) {
            throw new \RuntimeException("Unsupported backend: {$backend}. Must be 'ldap', 'mysql', or 'pgsql'");
        }

        return $backend;
    }

    /**
     * @throws \RuntimeException when the scheme is not one that PasswordUtils writes
     */
    private function validPasswordScheme(): string
    {
        $scheme = strtoupper($this->env('IREDPANEL_PASSWORD_DEFAULT_SCHEME', 'SSHA512'));
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new \RuntimeException("Unsupported password scheme: $scheme");
        }

        return $scheme;
    }

    /**
     * The configured UI language, or the fallback locale when it has no locale file.
     */
    private function supportedLanguage(): string
    {
        $language = $this->env('IREDPANEL_DEFAULT_LANGUAGE', \App\I18n\Translator::FALLBACK_LOCALE);

        return \App\I18n\Translator::isSupported($language) ? $language : \App\I18n\Translator::FALLBACK_LOCALE;
    }

    /**
     * @throws \RuntimeException when IREDPANEL_SMTP_SECURITY names no supported mode
     */
    private function validSmtpSecurity(): string
    {
        $security = strtolower($this->env('IREDPANEL_SMTP_SECURITY', 'starttls'));
        if (!in_array($security, ['none', 'starttls', 'tls'], true)) {
            throw new \RuntimeException("Unsupported SMTP security: {$security}. Must be 'none', 'starttls', or 'tls'");
        }

        return $security;
    }

    /**
     * @throws \RuntimeException when IREDPANEL_PUBLIC_URL is no plain http(s) URL
     */
    private function validPublicUrl(): string
    {
        $publicUrl = rtrim($this->env('IREDPANEL_PUBLIC_URL', ''), '/');
        if ($publicUrl !== '' && !preg_match('#^https?://[^/?\#\s]+(/[^?\#\s]*)?$#', $publicUrl)) {
            throw new \RuntimeException('IREDPANEL_PUBLIC_URL must be an http(s) URL without query, for example https://panel.example.com');
        }

        return $publicUrl;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->loadDatabaseOverrides();
        }
        return self::$instance;
    }

    /**
     * Loads overridable settings from the panel_settings table in the iredadmin database.
     * Falls back silently to .env values if the database is not available.
     */
    private function loadDatabaseOverrides(): void
    {
        if ($this->dbOverridesLoaded) {
            return;
        }
        $this->dbOverridesLoaded = true;

        if ($this->iredadminDbHost === '') {
            return;
        }

        try {
            $connectionClass = $this->backend === 'pgsql'
                ? \App\Repositories\Pgsql\IredadminPgsqlConnection::class
                : \App\Repositories\Mysql\IredadminConnection::class;

            $pdo = $connectionClass::getInstance()->getPdo();
            if ($pdo === null) {
                return;
            }

            $stmt = $pdo->query("SELECT setting_key, setting_value FROM panel_settings");
            if ($stmt === false) {
                return;
            }

            while ($row = $stmt->fetch()) {
                $this->applyOverride((string) $row['setting_key'], (string) $row['setting_value']);
            }
        } catch (\PDOException) {
            // Silently fall back to .env values
        }
    }

    /**
     * Writes one panel_settings row over the .env value. An unknown key and an
     * unsupported password scheme are ignored, so a stale row cannot break the panel.
     */
    private function applyOverride(string $key, string $value): void
    {
        $type = self::OVERRIDABLE_KEYS[$key] ?? null;
        if ($type === null) {
            return;
        }

        if ($key === 'passwordDefaultScheme') {
            $value = strtoupper($value);
            if (!in_array($value, self::ALLOWED_SCHEMES, true)) {
                return;
            }
        }

        $this->$key = match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }

    /**
     * Forces the singleton to reload settings from both .env and database
     * on the next getInstance() call. Used after saving panel settings.
     */
    public static function invalidateCache(): void
    {
        self::$instance = null;
    }

    private function rawEnv(string $key): string|false
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    }

    public function env(string $key, string $default = ''): string
    {
        $value = $this->rawEnv($key);
        return $value !== false ? $value : $default;
    }

    private function envRequired(string $key): string
    {
        $value = $this->rawEnv($key);
        if ($value === false || $value === '') {
            throw new \RuntimeException("Required environment variable $key is not set");
        }
        return $value;
    }

    private function envBool(string $key, bool $default): bool
    {
        $value = $this->rawEnv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function envInt(string $key, int $default): int
    {
        $value = $this->rawEnv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return (int) $value;
    }
}
