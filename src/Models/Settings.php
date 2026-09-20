<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Application settings class. Singleton.
 * Settings are read from the .env or .env.prod file, then overridden
 * by values from the panel_settings database table (if available).
 * Only the active backend's connection settings are validated.
 *
 * @phpstan-type ConnectionValues array{
 *     ldapUri: string, ldapRootDn: string, ldapUser: string, ldapPassword: string, ldapTlsVerify: bool,
 *     mysqlHost: string, mysqlPort: int, mysqlDatabase: string, mysqlUser: string, mysqlPassword: string,
 *     pgsqlHost: string, pgsqlPort: int, pgsqlDatabase: string, pgsqlUser: string, pgsqlPassword: string,
 *     vmailPath: string, storageNode: string
 * }
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

    // iRedAPD DB connection
    public readonly string $iredapdDbHost;
    public readonly int $iredapdDbPort;
    public readonly string $iredapdDbName;
    public readonly string $iredapdDbUser;
    public readonly string $iredapdDbPassword;


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
    public bool $passwordRecoveryEnabled;
    public int $sessionTimeout;
    public bool $sessionValidateIp;
    public string $allowedIpRanges;
    public bool $adminTotpRequired;

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
    public bool $activityLoggingEnabled;
    public bool $amavisdEnabled;
    public int $amavisdRemoveQuarantinedInDays;
    public int $amavisdRemoveMaillogInDays;
    // Amavisd AM.PDP socket that releases quarantined messages
    public string $amavisdQuarantineHost;
    public int $amavisdQuarantinePort;
    public bool $fail2banEnabled;
    public string $fail2banSocket;
    public string $fail2banJails;
    public bool $iredapdEnabled;
    public string $geoIpDbPath;

    // mlmmjadmin RESTful API, which manages the mlmmj mailing list spool
    public string $mlmmjadminApiUrl;
    public string $mlmmjadminApiToken;

    // Outgoing SMTP for newsletter confirmations and quarantine notifications
    public string $smtpHost;
    public string $smtpPassword;
    public int $smtpPort;
    public string $smtpSecurity;
    public bool $smtpTlsVerify;
    public string $smtpUser;
    public string $smtpFrom;

    // Base URL of the panel used in links sent by mail
    public string $publicUrl;
    public int $newsletterExpireHours;

    // DKIM selector that the domain DNS check asks for
    public string $dkimSelector;

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
        'passwordRecoveryEnabled' => 'bool',
        'sessionTimeout' => 'int',
        'sessionValidateIp' => 'bool',
        'allowedIpRanges' => 'string',
        'adminTotpRequired' => 'bool',
        'brandName' => 'string',
        'brandLogoUrl' => 'string',
        'brandFooterText' => 'string',
        'brandPrimaryColor' => 'string',
        'paginationPerPage' => 'int',
        'checkUpdates' => 'bool',
        'requireDomainOwnershipVerification' => 'bool',
        'defaultLanguage' => 'string',
        'activityLoggingEnabled' => 'bool',
        'amavisdEnabled' => 'bool',
        'amavisdRemoveQuarantinedInDays' => 'int',
        'amavisdRemoveMaillogInDays' => 'int',
        'amavisdQuarantineHost' => 'string',
        'amavisdQuarantinePort' => 'int',
        'fail2banEnabled' => 'bool',
        'fail2banSocket' => 'string',
        'fail2banJails' => 'string',
        'iredapdEnabled' => 'bool',
        'geoIpDbPath' => 'string',
        'apiEnabled' => 'bool',
        'apiKey' => 'string',
        'apiAllowedIps' => 'string',
        'smtpHost' => 'string',
        'smtpPort' => 'int',
        'smtpSecurity' => 'string',
        'smtpTlsVerify' => 'bool',
        'smtpUser' => 'string',
        'smtpPassword' => 'string',
        'smtpFrom' => 'string',
        'publicUrl' => 'string',
        'newsletterExpireHours' => 'int',
        'dkimSelector' => 'string',
        'mlmmjadminApiUrl' => 'string',
        'mlmmjadminApiToken' => 'string',
    ];

    /**
     * Overridable settings that panel_settings holds encrypted, because the panel
     * must read the value back. A row that no key can decrypt is ignored, so the
     * .env value of that setting stays in force.
     */
    public const ENCRYPTED_KEYS = ['smtpPassword', 'mlmmjadminApiToken'];

    /** The modes that IREDPANEL_SMTP_SECURITY and the settings form accept. */
    public const SMTP_SECURITY_MODES = ['none', 'starttls', 'tls'];

    /** An http(s) URL without a query or a fragment. */
    public const PUBLIC_URL_PATTERN = '#^https?://[^/?\#\s]+(/[^?\#\s]*)?$#';

    /** A host name or an IP address, with no scheme, port or path. */
    public const HOST_PATTERN = '/^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$/';

    private function __construct()
    {
        // Backend selection
        $this->backend = $this->validBackend();

        $this->secretKey = $this->envRequired('IREDPANEL_SECRET_KEY');
        // The DB-overridable settings are not readonly, so a method may write them.
        $this->loadPasswordPolicy();

        // Integration DB default port based on backend
        $defaultDbPort = $this->backend === 'pgsql' ? 5432 : 3306;

        // iredadmin database (optional, for activity logging)
        $this->iredadminDbHost = $this->env('IREDPANEL_IREDADMIN_DB_HOST', '');
        $this->iredadminDbPort = $this->envInt('IREDPANEL_IREDADMIN_DB_PORT', $defaultDbPort);
        $this->iredadminDbName = $this->env('IREDPANEL_IREDADMIN_DB_NAME', 'iredadmin');
        $this->iredadminDbUser = $this->env('IREDPANEL_IREDADMIN_DB_USER', '');
        $this->iredadminDbPassword = $this->env('IREDPANEL_IREDADMIN_DB_PASSWORD', '');

        // Amavisd integration
        $this->amavisdDbHost = $this->env('IREDPANEL_AMAVISD_DB_HOST', '');
        $this->amavisdDbPort = $this->envInt('IREDPANEL_AMAVISD_DB_PORT', $defaultDbPort);
        $this->amavisdDbName = $this->env('IREDPANEL_AMAVISD_DB_NAME', 'amavisd');
        $this->amavisdDbUser = $this->env('IREDPANEL_AMAVISD_DB_USER', '');
        $this->amavisdDbPassword = $this->env('IREDPANEL_AMAVISD_DB_PASSWORD', '');

        // iRedAPD integration
        $this->iredapdDbHost = $this->env('IREDPANEL_IREDAPD_DB_HOST', '');
        $this->iredapdDbPort = $this->envInt('IREDPANEL_IREDAPD_DB_PORT', $defaultDbPort);
        $this->iredapdDbName = $this->env('IREDPANEL_IREDAPD_DB_NAME', 'iredapd');
        $this->iredapdDbUser = $this->env('IREDPANEL_IREDAPD_DB_USER', '');
        $this->iredapdDbPassword = $this->env('IREDPANEL_IREDAPD_DB_PASSWORD', '');

        // The DB-overridable settings run last, because the Amavisd quarantine
        // host falls back to the Amavisd database host.
        $this->loadPanelSettings();
        $this->loadIntegrationSettings();
        $this->loadMailSettings();

        // Conditional backend settings. The properties are readonly, so the
        // values are computed per backend and assigned here.
        $connection = $this->backendConnection();
        $this->ldapUri = $connection['ldapUri'];
        $this->ldapRootDn = $connection['ldapRootDn'];
        $this->ldapUser = $connection['ldapUser'];
        $this->ldapPassword = $connection['ldapPassword'];
        $this->ldapTlsVerify = $connection['ldapTlsVerify'];
        $this->mysqlHost = $connection['mysqlHost'];
        $this->mysqlPort = $connection['mysqlPort'];
        $this->mysqlDatabase = $connection['mysqlDatabase'];
        $this->mysqlUser = $connection['mysqlUser'];
        $this->mysqlPassword = $connection['mysqlPassword'];
        $this->pgsqlHost = $connection['pgsqlHost'];
        $this->pgsqlPort = $connection['pgsqlPort'];
        $this->pgsqlDatabase = $connection['pgsqlDatabase'];
        $this->pgsqlUser = $connection['pgsqlUser'];
        $this->pgsqlPassword = $connection['pgsqlPassword'];
        $this->vmailPath = $connection['vmailPath'];
        $this->storageNode = $connection['storageNode'];
    }

    /**
     * The connection values of the selected backend, over the neutral values of the other two.
     *
     * @return ConnectionValues
     */
    private function backendConnection(): array
    {
        $neutral = [
            'ldapUri' => '',
            'ldapRootDn' => '',
            'ldapUser' => '',
            'ldapPassword' => '',
            'ldapTlsVerify' => false,
            'mysqlHost' => '',
            'mysqlPort' => 3306,
            'mysqlDatabase' => '',
            'mysqlUser' => '',
            'mysqlPassword' => '',
            'pgsqlHost' => '',
            'pgsqlPort' => 5432,
            'pgsqlDatabase' => '',
            'pgsqlUser' => '',
            'pgsqlPassword' => '',
            'vmailPath' => '/var/vmail',
            'storageNode' => 'vmail1',
        ];

        return match ($this->backend) {
            'ldap' => $this->ldapConnection() + $neutral,
            'pgsql' => $this->pgsqlConnection() + $neutral,
            'mysql' => $this->mysqlConnection() + $neutral,
            default => throw new \LogicException("Unknown backend: {$this->backend}"),
        };
    }

    /**
     * @return array<string, string|int|bool>
     */
    private function ldapConnection(): array
    {
        $uri = $this->envRequired('IREDPANEL_LDAP_URI');
        if (!str_starts_with($uri, 'ldap://') && !str_starts_with($uri, 'ldaps://')) {
            throw new \RuntimeException("LDAP URI must start with ldap:// or ldaps://");
        }

        return [
            'ldapUri' => $uri,
            'ldapRootDn' => $this->envRequired('IREDPANEL_LDAP_ROOT_DN'),
            'ldapUser' => $this->envRequired('IREDPANEL_LDAP_USER'),
            'ldapPassword' => $this->envRequired('IREDPANEL_LDAP_PASSWORD'),
            'ldapTlsVerify' => $this->envBool('IREDPANEL_LDAP_TLS_VERIFY', false),
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    private function pgsqlConnection(): array
    {
        return [
            'pgsqlHost' => $this->envRequired('IREDPANEL_PGSQL_HOST'),
            'pgsqlPort' => $this->envInt('IREDPANEL_PGSQL_PORT', 5432),
            'pgsqlDatabase' => $this->envRequired('IREDPANEL_PGSQL_DATABASE'),
            'pgsqlUser' => $this->envRequired('IREDPANEL_PGSQL_USER'),
            'pgsqlPassword' => $this->envRequired('IREDPANEL_PGSQL_PASSWORD'),
        ] + $this->mailboxStorage();
    }

    /**
     * @return array<string, string|int|bool>
     */
    private function mysqlConnection(): array
    {
        return [
            'mysqlHost' => $this->envRequired('IREDPANEL_MYSQL_HOST'),
            'mysqlPort' => $this->envInt('IREDPANEL_MYSQL_PORT', 3306),
            'mysqlDatabase' => $this->envRequired('IREDPANEL_MYSQL_DATABASE'),
            'mysqlUser' => $this->envRequired('IREDPANEL_MYSQL_USER'),
            'mysqlPassword' => $this->envRequired('IREDPANEL_MYSQL_PASSWORD'),
        ] + $this->mailboxStorage();
    }

    /**
     * The maildir location, which only a SQL backend reads from the environment.
     *
     * @return array<string, string>
     */
    private function mailboxStorage(): array
    {
        return [
            'vmailPath' => $this->env('IREDPANEL_VMAIL_PATH', '/var/vmail'),
            'storageNode' => $this->env('IREDPANEL_STORAGE_NODE', 'vmail1'),
        ];
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
        $this->passwordRecoveryEnabled = $this->envBool('IREDPANEL_PASSWORD_RECOVERY_ENABLED', false);
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
        $this->adminTotpRequired = $this->envBool('IREDPANEL_ADMIN_TOTP_REQUIRED', false);
        $this->apiEnabled = $this->envBool('IREDPANEL_API_ENABLED', false);
        $this->apiKey = $this->env('IREDPANEL_API_KEY', '');
        $this->apiAllowedIps = $this->env('IREDPANEL_API_ALLOWED_IPS', '');
        $this->paginationPerPage = $this->envInt('IREDPANEL_PAGINATION_PER_PAGE', 50);
        $this->checkUpdates = $this->envBool('IREDPANEL_CHECK_UPDATES', true);
        $this->geoIpDbPath = $this->env('IREDPANEL_GEOIP_DB_PATH', '');
        $this->requireDomainOwnershipVerification = $this->envBool('IREDPANEL_REQUIRE_DOMAIN_OWNERSHIP_VERIFICATION', false);
        $this->defaultLanguage = $this->supportedLanguage();
        $this->brandName = $this->env('IREDPANEL_BRAND_NAME', 'iRedPanel');
        $this->brandLogoUrl = $this->env('IREDPANEL_BRAND_LOGO_URL', '/static/logo.svg');
        $this->brandFooterText = $this->env('IREDPANEL_BRAND_FOOTER_TEXT', '');
        $this->brandPrimaryColor = $this->env('IREDPANEL_BRAND_PRIMARY_COLOR', '');
    }

    /**
     * The integration toggles and their behavior. The connection settings of the
     * same integrations stay in .env, because a wrong value there is not
     * repairable from the panel.
     */
    private function loadIntegrationSettings(): void
    {
        $this->activityLoggingEnabled = $this->envBool('IREDPANEL_ACTIVITY_LOGGING_ENABLED', true);
        $this->amavisdEnabled = $this->envBool('IREDPANEL_AMAVISD_ENABLED', false);
        $this->amavisdRemoveQuarantinedInDays = $this->envInt('IREDPANEL_AMAVISD_REMOVE_QUARANTINED_IN_DAYS', 7);
        $this->amavisdRemoveMaillogInDays = $this->envInt('IREDPANEL_AMAVISD_REMOVE_MAILLOG_IN_DAYS', 7);
        $this->amavisdQuarantineHost = $this->env('IREDPANEL_AMAVISD_QUARANTINE_HOST', $this->amavisdDbHost);
        $this->amavisdQuarantinePort = $this->envInt('IREDPANEL_AMAVISD_QUARANTINE_PORT', 9998);
        $this->fail2banEnabled = $this->envBool('IREDPANEL_FAIL2BAN_ENABLED', false);
        $this->fail2banSocket = $this->env('IREDPANEL_FAIL2BAN_SOCKET', '');
        $this->fail2banJails = $this->env('IREDPANEL_FAIL2BAN_JAILS', 'dovecot,postfix,postfix-sasl');
        $this->iredapdEnabled = $this->envBool('IREDPANEL_IREDAPD_ENABLED', false);
        $this->mlmmjadminApiUrl = rtrim($this->env('IREDPANEL_MLMMJADMIN_API_URL', ''), '/');
        $this->mlmmjadminApiToken = $this->env('IREDPANEL_MLMMJADMIN_API_TOKEN', '');
    }

    /**
     * The outgoing mail settings and the public URL of the panel. A wrong value
     * stops a notification mail, it never locks an admin out.
     */
    private function loadMailSettings(): void
    {
        $this->smtpSecurity = $this->validSmtpSecurity();
        $this->smtpHost = $this->env('IREDPANEL_SMTP_HOST', '');
        $this->smtpPort = $this->envInt('IREDPANEL_SMTP_PORT', $this->smtpSecurity === 'tls' ? 465 : 587);
        $this->smtpTlsVerify = $this->envBool('IREDPANEL_SMTP_TLS_VERIFY', true);
        $this->smtpUser = $this->env('IREDPANEL_SMTP_USER', '');
        $this->smtpPassword = $this->env('IREDPANEL_SMTP_PASSWORD', '');
        $this->smtpFrom = $this->env('IREDPANEL_SMTP_FROM', '');
        $this->publicUrl = $this->validPublicUrl();
        $this->newsletterExpireHours = max(1, $this->envInt('IREDPANEL_NEWSLETTER_EXPIRE_HOURS', 24));
        // DnsCheck refuses a value that is no DNS label, so no validation belongs here.
        $this->dkimSelector = $this->env('IREDPANEL_DKIM_SELECTOR', 'dkim');
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
        if (!in_array($security, self::SMTP_SECURITY_MODES, true)) {
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
        if ($publicUrl !== '' && !preg_match(self::PUBLIC_URL_PATTERN, $publicUrl)) {
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

        if (in_array($key, self::ENCRYPTED_KEYS, true)) {
            $plaintext = $this->decryptedOverride($key, $value);
            if ($plaintext === null) {
                return;
            }
            $value = $plaintext;
        }

        $this->$key = match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }

    /**
     * The plaintext of one encrypted row, or null when no key reads it. A row
     * written under another IREDPANEL_SECRET_KEY, and a missing ext-sodium, both
     * leave the .env value in force instead of emptying the setting.
     */
    private function decryptedOverride(string $key, string $stored): ?string
    {
        if ($stored === '') {
            return '';
        }

        try {
            return \App\Utils\SecretBox::fromSecret($this->secretKey)->decrypt($stored);
        } catch (\Throwable $e) {
            error_log("iRedPanel: cannot read the stored {$key}: " . $e->getMessage());
            return null;
        }
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
