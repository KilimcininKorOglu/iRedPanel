# iRedPanel

A full-featured PHP web application for managing [iRedMail](https://www.iredmail.org/) mail servers. Supports OpenLDAP, MySQL/MariaDB, and PostgreSQL backends with optional Amavisd, Fail2ban, and iRedAPD integrations. Includes a REST API, mail alias and mailing list management, spam policy control, and role-based admin access.

Built with vanilla PHP 8.1+ -- no framework, no ORM, no template engine dependency. Uses `vlucas/phpdotenv` for environment configuration and the Chota CSS framework for UI.

```
kilimcininkoroglu/iredpanel v1.0.0
```

### Supported Backends

| iRedMail Backend | Supported |
|------------------|-----------|
| OpenLDAP         | Yes       |
| MySQL/MariaDB    | Yes       |
| PostgreSQL       | Yes       |

Select the backend via `IREDPANEL_BACKEND` environment variable (`ldap`, `mysql`, or `pgsql`).

## Requirements

- PHP 8.1 or higher
- PHP LDAP extension (`ext-ldap`) -- for LDAP backend
- PHP PDO MySQL extension (`ext-pdo_mysql`) -- for MySQL backend
- PHP PDO PostgreSQL extension (`ext-pdo_pgsql`) -- for PostgreSQL backend
- [Composer](https://getcomposer.org/) (included as `composer.phar`)
- An iRedMail server with OpenLDAP, MySQL/MariaDB, or PostgreSQL backend

## Installation

```bash
git clone https://github.com/KilimcininKorOglu/iRedPanel.git
cd iRedPanel
php composer.phar install
cp .env.example .env
```

Edit `.env` with your backend choice and connection details, then start the application (see [Running](#running)).

## Configuration

All settings use the `IREDPANEL_` prefix and are loaded from `.env` or `.env.prod` via [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv).

### Backend Selection

```env
IREDPANEL_BACKEND=ldap    # or "mysql" or "pgsql"
```

### General Settings

| Variable     | Required | Description            |
|--------------|----------|------------------------|
| `SECRET_KEY` | Yes      | Application secret key |

### LDAP Settings (required when `BACKEND=ldap`)

| Variable          | Default | Description                               | Example                    |
|-------------------|---------|-------------------------------------------|----------------------------|
| `LDAP_URI`        | -       | LDAP server URI (`ldap://` or `ldaps://`) | `ldaps://ldap.example.com` |
| `LDAP_ROOT_DN`    | -       | LDAP root DN                              | `dc=example,dc=com`        |
| `LDAP_USER`       | -       | Admin email or CN for LDAP bind           | `postmaster@example.com`   |
| `LDAP_PASSWORD`   | -       | Admin password for LDAP bind              | `secret`                   |
| `LDAP_TLS_VERIFY` | `false` | Verify TLS certificate on LDAP connection | `true`                     |

### MySQL Settings (required when `BACKEND=mysql`)

| Variable         | Default      | Description                         |
|------------------|--------------|-------------------------------------|
| `MYSQL_HOST`     | -            | MySQL server hostname               |
| `MYSQL_PORT`     | `3306`       | MySQL server port                   |
| `MYSQL_DATABASE` | -            | Database name (e.g. `vmail`)        |
| `MYSQL_USER`     | -            | Database user                       |
| `MYSQL_PASSWORD` | -            | Database password                   |
| `VMAIL_PATH`     | `/var/vmail` | Mail storage base path              |
| `STORAGE_NODE`   | `vmail1`     | Storage node name for new mailboxes |

### PostgreSQL Settings (required when `BACKEND=pgsql`)

| Variable         | Default  | Description                         |
|------------------|----------|-------------------------------------|
| `PGSQL_HOST`     | -        | PostgreSQL server hostname          |
| `PGSQL_PORT`     | `5432`   | PostgreSQL server port              |
| `PGSQL_DATABASE` | -        | Database name (e.g. `vmail`)        |
| `PGSQL_USER`     | -        | Database user                       |
| `PGSQL_PASSWORD` | -        | Database password                   |
| `STORAGE_NODE`   | `vmail1` | Storage node name for new mailboxes |

### Optional Settings

These settings serve as initial defaults. Once the iRedAdmin database is configured, they can also be managed from the **Panel Settings** UI (`/panel-settings`). Database values take precedence over `.env` values.

| Variable                                | Default   | Description                                  |
|-----------------------------------------|-----------|----------------------------------------------|
| `PASSWORD_MIN_LENGTH`                   | `8`       | Minimum password length                      |
| `PASSWORD_INCLUDES_SPECIAL_CHARS`       | `true`    | Require special characters in passwords      |
| `PASSWORD_INCLUDES_NUMBERS`             | `true`    | Require digits in passwords                  |
| `PASSWORD_INCLUDES_LOWERCASE`           | `true`    | Require lowercase letters in passwords       |
| `PASSWORD_INCLUDES_UPPERCASE`           | `true`    | Require uppercase letters in passwords       |
| `PASSWORD_HASHES_USE_PREFIXED_SCHEME`   | `true`    | Use `{SCHEME}` prefix in password hashes     |
| `PASSWORD_DEFAULT_SCHEME`               | `SSHA512` | Default password hashing scheme              |
| `REQUIRE_OLD_PASSWORD_ON_CHANGE`        | `false`   | Require current password for password change |
| `PAGINATION_PER_PAGE`                   | `50`      | Items per page on list views                 |
| `SESSION_TIMEOUT`                       | `1800`    | Session timeout in seconds                   |
| `ALLOWED_IP_RANGES`                     | -         | Comma-separated CIDR ranges for panel access |
| `SESSION_VALIDATE_IP`                   | `false`   | Invalidate session on client IP change       |
| `CHECK_UPDATES`                         | `true`    | Check GitHub for new versions on dashboard   |
| `GEOIP_DB_PATH`                         | -         | Path to MaxMind GeoLite2-City .mmdb file     |
| `REQUIRE_DOMAIN_OWNERSHIP_VERIFICATION` | `false`   | Require DNS TXT verification for new domains |
| `NEWSLETTER_EXPIRE_HOURS`               | `24`      | Token expiry for newsletter confirmations    |

### Branding

| Variable              | Default                     | Description                |
|-----------------------|-----------------------------|----------------------------|
| `BRAND_NAME`          | `iRedPanel`                 | Panel name in UI and title |
| `BRAND_LOGO_URL`      | `/static/logo-iredmail.png` | Logo URL in navigation     |
| `BRAND_FOOTER_TEXT`   | -                           | Custom footer text         |
| `BRAND_PRIMARY_COLOR` | -                           | CSS primary color override |

### REST API (optional)

| Variable          | Default | Description                                   |
|-------------------|---------|-----------------------------------------------|
| `API_ENABLED`     | `false` | Enable REST API at `/api/v1/*`                |
| `API_KEY`         | -       | API authentication key (sent via X-API-Key)   |
| `API_ALLOWED_IPS` | -       | Comma-separated IPs allowed to access the API |

### Activity Logging (optional)

Connects to the `iredadmin` database for admin activity logging, panel settings storage, and system settings.

**Note:** Integration database port defaults are `3306` (MySQL). When using `BACKEND=pgsql`, set `*_DB_PORT` to `5432` for each integration.

| Variable                   | Default     | Description                 |
|----------------------------|-------------|-----------------------------|
| `ACTIVITY_LOGGING_ENABLED` | `true`      | Enable/disable activity log |
| `IREDADMIN_DB_HOST`        | -           | iRedAdmin database host     |
| `IREDADMIN_DB_PORT`        | `3306`      | iRedAdmin database port     |
| `IREDADMIN_DB_NAME`        | `iredadmin` | iRedAdmin database name     |
| `IREDADMIN_DB_USER`        | -           | iRedAdmin database user     |
| `IREDADMIN_DB_PASSWORD`    | -           | iRedAdmin database password |

### Amavisd Integration (optional)

Enables quarantine viewer, mail log, spam policy management, and white/blacklist management.

| Variable                             | Default   | Description               |
|--------------------------------------|-----------|---------------------------|
| `AMAVISD_ENABLED`                    | `false`   | Enable Amavisd features   |
| `AMAVISD_DB_HOST`                    | -         | Amavisd database host     |
| `AMAVISD_DB_PORT`                    | `3306`    | Amavisd database port     |
| `AMAVISD_DB_NAME`                    | `amavisd` | Amavisd database name     |
| `AMAVISD_DB_USER`                    | -         | Amavisd database user     |
| `AMAVISD_DB_PASSWORD`                | -         | Amavisd database password |
| `AMAVISD_REMOVE_QUARANTINED_IN_DAYS` | `7`       | Quarantine retention days |
| `AMAVISD_REMOVE_MAILLOG_IN_DAYS`     | `7`       | Mail log retention days   |

### Fail2ban Integration (optional)

| Variable           | Default                        | Description                   |
|--------------------|--------------------------------|-------------------------------|
| `FAIL2BAN_ENABLED` | `false`                        | Enable ban/unban management   |
| `FAIL2BAN_SOCKET`  | -                              | Custom fail2ban-client socket |
| `FAIL2BAN_JAILS`   | `dovecot,postfix,postfix-sasl` | Comma-separated jail names    |

### iRedAPD Integration (optional)

Enables throttle settings, greylisting, rDNS white/blacklist, and SenderScore whitelist.

| Variable              | Default   | Description               |
|-----------------------|-----------|---------------------------|
| `IREDAPD_ENABLED`     | `false`   | Enable iRedAPD features   |
| `IREDAPD_DB_HOST`     | -         | iRedAPD database host     |
| `IREDAPD_DB_PORT`     | `3306`    | iRedAPD database port     |
| `IREDAPD_DB_NAME`     | `iredapd` | iRedAPD database name     |
| `IREDAPD_DB_USER`     | -         | iRedAPD database user     |
| `IREDAPD_DB_PASSWORD` | -         | iRedAPD database password |

### Password Schemes

Schemes with full hash generation support:

`SSHA512`, `SHA512`, `SSHA`, `BCRYPT`, `MD5`, `PLAIN-MD5`, `PLAIN`

Schemes that require the external `doveadm` command (falls back to SSHA if unavailable):

`CRAM-MD5`, `NTLM`

Schemes recognized for reading existing hashes but not available for generation:

`SHA`, `CRYPT`, `SHA512-CRYPT`

## Running

### Development Server

```bash
php -S localhost:8080 -t public/
```

Open `http://localhost:8080` in your browser. You will be redirected to the dashboard.

### Apache

Point the document root to the `public/` directory. The included `.htaccess` handles URL rewriting.

```apache
<VirtualHost *:80>
    DocumentRoot /path/to/iredpanel/public
    <Directory /path/to/iredpanel/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Nginx

```nginx
server {
    listen 80;
    root /path/to/iredpanel/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Features

### Domain Management
- Domain CRUD across all three backends (LDAP, MySQL, PostgreSQL)
- Domain settings: default user quota, password length rules, disclaimer text
- Domain alias management (alias domain pointing to target domain)
- Catch-all address configuration per domain
- BCC settings (sender and recipient) per domain
- Sender-dependent relay host per domain
- Domain ownership verification via DNS TXT records
- Enable/disable domains with bulk operations
- Paginated domain list with status filter

### User Management
- User CRUD with full profile editing (name, quota, phone, employee ID, etc.)
- User creation on all three backends
- Mail service toggles: SMTP, POP3, IMAP, ManageSieve, SOGo (+ TLS variants)
- Email forwarding with keep-copy option
- Per-user alias addresses (multiple emails for one mailbox)
- Per-user BCC settings (sender and recipient)
- Per-user relay host configuration
- Email address rename with referential integrity across all tables
- Used quota display (from Dovecot `used_quota` table)
- Last login tracking (from Dovecot `last_login` table)
- Bulk operations: enable, disable, delete multiple users
- Alphabetic filtering and sortable columns
- Paginated user list with random password generation

### Mail Alias Management
- Distribution list CRUD with member management
- Alias moderator management (per-alias access control)
- Access policies: public, domain, membersOnly, moderatorsOnly
- Bulk operations: enable, disable, delete multiple aliases
- Paginated alias list with domain filter

### Mailing List Management (mlmmj)
- Mailing list CRUD with automatic mlmmj transport configuration
- List owner management
- Access policy, max message size, and max members settings
- Bulk operations: enable, disable, delete

### Admin Management
- Standalone and mailbox-based admin accounts
- Admin CRUD with password management
- Domain assignment: assign/revoke domains per admin
- Resource limits per admin: max domains, users, aliases, mailing lists, quota
- Global admin promotion/revocation

### Spam Policy Management
- Global, per-domain, and per-user spam thresholds (tag, tag2, kill levels)
- Bypass controls: virus checks, spam checks
- Delivery options: virus lover, spam lover, banned files lover
- Subject tag customization
- Policy overview listing all configured policies

### White/Blacklist Management
- Inbound whitelist/blacklist (sender-based)
- Outbound whitelist/blacklist (recipient-based)
- Per-account (global, domain, user) entry management
- rDNS-based white/blacklist for iRedAPD
- SenderScore IP permanent whitelisting

### Global Search
- Full-text search across domains, users, aliases, mailing lists, and admins
- Account type and status filtering
- RBAC-aware: domain admins see only their managed domains

### Dashboard
- Domain, user, and admin counts (total/active/disabled)
- Quota allocation and usage statistics
- System information (hostname, uptime, load, versions)
- GitHub version check for updates

### Activity Logging
- Admin operation logging to `iredadmin.log` table
- Log viewer with domain and event type filters
- Log deletion (individual and bulk)

### Deferred Mailbox Deletion (MySQL only)
- Deleted user mailboxes recorded in `deleted_mailboxes` table
- Management UI: view, cancel, reschedule pending deletions

### External Integrations
- **Amavisd**: Quarantine viewer with release/delete, mail log, spam policy, white/blacklist, configurable cleanup
- **Fail2ban**: Jail status, ban/unban IPs with optional GeoIP country/city display
- **iRedAPD**: Per-account throttle settings, greylisting toggle, sender whitelist, greylisting tracking data, rDNS white/blacklist, SenderScore whitelist

### Export
- Domain user export (CSV and JSON formats)
- Admin statistics export (CSV and JSON formats)

### Newsletter Subscription
- Public subscribe/unsubscribe endpoints for mailing lists
- Token-based email verification with configurable expiration
- No authentication required (public-facing)

### Domain Ownership Verification
- DNS TXT record verification for new domains
- Verification code generation and tracking
- Global admin force-verify option

### REST API

Full CRUD REST API at `/api/v1/*` with API key authentication and IP whitelist.

**Resources:** domains, users, aliases, mailing lists, admins, domain aliases, spam policy, white/blacklist, throttle, greylisting.

**Authentication:** `X-API-Key` header with optional IP restriction.

**Example:**

```bash
# List domains
curl -H "X-API-Key: your-key" http://localhost:8080/api/v1/domains

# Create user
curl -X POST -H "X-API-Key: your-key" -H "Content-Type: application/json" \
  -d '{"uid":"john","password":"P@ss123","mailQuota":1024}' \
  http://localhost:8080/api/v1/domains/example.com/users

# Verify password
curl -X POST -H "X-API-Key: your-key" -H "Content-Type: application/json" \
  -d '{"password":"test"}' \
  http://localhost:8080/api/v1/verify-password/user/john@example.com
```

### Panel Settings
- 29 behavioral settings editable via web UI at `/panel-settings` (global admin only)
- Categories: Branding, Password Policy, Session & Security, Display, Integrations, REST API
- Stored in `panel_settings` table in iredadmin database (key-value)
- Priority: database value > `.env` value > hardcoded default
- Graceful fallback to `.env` when iRedAdmin database is not configured
- Activity logging for all settings changes

### Branding
- Configurable panel name, logo, footer text, and primary color
- CSS custom property override via `BRAND_PRIMARY_COLOR`
- Editable via Panel Settings UI or `.env`

### Security
- CSRF token validation on all POST forms
- Session ID regeneration after login (session fixation prevention)
- Open redirect prevention on login redirects
- Secure cookie flag (conditional on HTTPS)
- Configurable LDAP TLS certificate verification
- Route parameter enforcement over form body values
- Password piped via stdin to external commands (not CLI arguments)
- Session timeout with configurable duration
- Session IP change detection (configurable)
- IP restriction via CIDR ranges
- Role-based access control (global admin vs domain admin)
- API key authentication with timing-safe comparison

### CLI Tools

```bash
php cli/bulkPasswordUpdate.php --file=passwords.csv         # Bulk password update
php cli/bulkQuotaUpdate.php --file=quotas.csv                # Bulk quota update
php cli/importUsers.php /path/to/users.csv                   # Bulk user import from CSV
php cli/deleteExpiredMailboxes.php [--dry-run]                # Cron: cleanup mailboxes
php cli/exportUsers.php --domain=example.com                 # Export users to CSV
php cli/promoteToGlobalAdmin.php --email=admin@example.com   # Promote to global admin
php cli/cleanupAmavisdDb.php [--quarantine-days=7]           # Cron: Amavisd cleanup
php cli/dumpDisclaimer.php                                   # Dump domain disclaimers
php cli/dumpQuarantinedMails.php                             # Export quarantined messages
php cli/invalidateSessions.php                               # Invalidate all sessions
php cli/notifyQuarantinedRecipients.php [--force-all]        # Cron: quarantine notifications
```

### Limitations

- **i18n**: English only

## Authentication

Authentication depends on the selected backend:

**LDAP backend**: Binds to the LDAP server with user credentials and verifies the `domainGlobalAdmin=yes` attribute.

**MySQL/PostgreSQL backend**: Verifies credentials against the `admin` table (standalone admins) or `mailbox` table (mailbox-based admins). Checks `domain_admins` for role assignment.

### Role-Based Access Control

| Role         | Access                                                |
|--------------|-------------------------------------------------------|
| Global admin | Full access to all domains, users, admins, and system |
| Domain admin | Access only to assigned domains and their users       |

Global admins are identified by `domain='ALL'` in `domain_admins` (MySQL/PostgreSQL) or `domainGlobalAdmin=yes` (LDAP). Domain admins see only their managed domains in the domain list and can only manage users within those domains.

Session cookies are configured with `httponly=true`, `samesite=Lax`, and `secure=true` (when served over HTTPS). Session IDs are regenerated after successful login. Sessions expire after `SESSION_TIMEOUT` seconds of inactivity.

## Architecture

The application uses a **Repository pattern** to abstract data access. Controllers interact with repository interfaces, and the `RepositoryFactory` returns the correct implementation based on `IREDPANEL_BACKEND`.

```
Controller → RepositoryInterface → LdapRepository  (when BACKEND=ldap)
                                 → MysqlRepository  (when BACKEND=mysql)
                                 → PgsqlRepository  (when BACKEND=pgsql)
```

External integrations (Amavisd, iRedAPD) connect to their own databases via dedicated PDO singletons. Fail2ban communicates via the `fail2ban-client` CLI. The REST API uses dedicated controllers under `App\Api\` namespace with API key middleware.

### Database Connections

| Connection            | Database    | Purpose                                                        |
|-----------------------|-------------|----------------------------------------------------------------|
| `MysqlConnection`     | `vmail`     | Mail domains, users, admins, aliases, BCC, relay               |
| `PgsqlConnection`     | `vmail`     | Same as MySQL (PostgreSQL variant)                             |
| `IredadminConnection` | `iredadmin` | Activity logging, domain ownership, newsletter, panel settings |
| `AmavisdConnection`   | `amavisd`   | Quarantine, mail log, spam policy, white/blacklist             |
| `IredapdConnection`   | `iredapd`   | Throttle, greylisting, rDNS, SenderScore                       |

### Repository Interfaces (22)

| Interface                            | Purpose                              |
|--------------------------------------|--------------------------------------|
| `AuthRepositoryInterface`            | Authentication + RBAC                |
| `DomainRepositoryInterface`          | Domain CRUD                          |
| `UserRepositoryInterface`            | User CRUD + rename                   |
| `AdminRepositoryInterface`           | Admin CRUD + resource limits         |
| `ForwardingRepositoryInterface`      | Email forwarding                     |
| `QuotaRepositoryInterface`           | Used quota queries                   |
| `DashboardRepositoryInterface`       | Dashboard statistics                 |
| `DomainAliasRepositoryInterface`     | Domain alias CRUD                    |
| `DeletedMailboxRepositoryInterface`  | Deferred mailbox deletion            |
| `AliasRepositoryInterface`           | Mail alias + catch-all + per-user    |
| `BccRepositoryInterface`             | Domain/user BCC settings             |
| `RelayRepositoryInterface`           | Sender-dependent relay               |
| `MailingListRepositoryInterface`     | Mailing list CRUD + owners           |
| `SpamPolicyRepositoryInterface`      | Spam thresholds per account          |
| `WhiteBlacklistRepositoryInterface`  | Inbound/outbound white/blacklist     |
| `LastLoginRepositoryInterface`       | Dovecot last login tracking          |
| `SearchRepositoryInterface`          | Global search                        |
| `DomainOwnershipRepositoryInterface` | DNS domain verification              |
| `AmavisdRepositoryInterface`         | Quarantine and mail log              |
| `IredapdRepositoryInterface`         | Throttle, greylist, rDNS, SS         |
| `ApiKeyRepositoryInterface`          | DB-backed API key management         |
| `PanelSettingsRepositoryInterface`   | DB-backed panel settings (key-value) |

### Field Mapping (LDAP vs MySQL/PostgreSQL)

| User Model Field    | LDAP Attribute      | MySQL/PgSQL Column      |
|---------------------|---------------------|-------------------------|
| `uid`               | `uid`               | `username` (before `@`) |
| `accountStatus`     | `accountStatus`     | `active` (1/0)          |
| `mailQuota`         | `mailQuota` (bytes) | `quota` (MB)            |
| `cn`                | `cn`                | `name`                  |
| `givenName`         | `givenName`         | `first_name`            |
| `sn`                | `sn`                | `last_name`             |
| `employeeNumber`    | `employeeNumber`    | `employeeid`            |
| `title`             | `title`             | `rank`                  |
| `mobile`            | `mobile`            | `mobile`                |
| `telephoneNumber`   | `telephoneNumber`   | `phone`                 |
| `domainGlobalAdmin` | `domainGlobalAdmin` | `isglobaladmin` (1/0)   |

## Project Structure

```
composer.json                          Dependencies and PSR-4 autoloading
phpunit.xml.dist                       PHPUnit configuration
.env.example                           Environment variable template
cli/
  bootstrap.php                        CLI autoload (no session)
  bulkPasswordUpdate.php               Bulk password update from CSV
  bulkQuotaUpdate.php                  Bulk quota update from CSV
  importUsers.php                      Bulk user import from CSV
  deleteExpiredMailboxes.php           Cron: mailbox directory cleanup
  exportUsers.php                      Export users to CSV
  promoteToGlobalAdmin.php             Promote user to global admin
  cleanupAmavisdDb.php                 Cron: Amavisd database cleanup
  dumpDisclaimer.php                   Dump domain disclaimers to files
  dumpQuarantinedMails.php             Export quarantined messages
  invalidateSessions.php               Invalidate all active sessions
  notifyQuarantinedRecipients.php      Cron: quarantine email notifications
public/
  index.php                            Front controller (109 routes)
  .htaccess                            Apache URL rewrite rules
  static/                              Chota CSS framework, custom styles, logo
src/
  bootstrap.php                        Autoloading, dotenv, session, extension check
  Router.php                           Regex-based URL router (GET/POST/PUT/DELETE)
  Middleware.php                       Auth guard, RBAC, session timeout, IP restriction
  CsrfProtection.php                  CSRF token generation and validation
  TemplateEngine.php                   Layout inheritance, branding, feature flags
  TemplateFilters.php                  localize() and asMegabytes() helpers
  Exceptions/
    BackendConnectionException.php     Shared base for backend connection errors
  Models/
    Settings.php                       Singleton config (env + DB overrides via panel_settings)
    LdapConnection.php                 LDAP connection singleton (TLS/STARTTLS)
    User.php                           Mail user data model (20+ fields, quota in MB)
    UserPassword.php                   Password validation (7 rules)
    Domain.php                         Domain data model
    Admin.php                          Admin data model with resource limits
    Alias.php                          Mail alias data model
    MailingList.php                    Mailing list data model
    SpamPolicy.php                    Spam policy data model
    DomainAlias.php                    Domain alias data model
    DomainSettings.php                 Per-domain settings (key:value format)
    PaginatedResult.php                Pagination wrapper
    DeletedMailbox.php                 Deferred deletion record
    ApiKey.php                         API key data model (RBAC, domains)
  Repositories/
    22 interfaces                      Repository contracts
    RepositoryFactory.php              Returns backend-specific implementations
    Ldap/                              LDAP implementations
    Mysql/                             MySQL implementations + connection singletons
    Pgsql/                             PostgreSQL implementations + connection singletons
  Api/
    ApiMiddleware.php                  API key authentication + IP whitelist
    ApiResponse.php                    JSON response helpers
    DomainApiController.php            Domain API endpoints
    UserApiController.php              User API + password verification
    AliasApiController.php             Alias API endpoints
    MailingListApiController.php       Mailing list API endpoints
    AdminApiController.php             Admin API endpoints
    DomainAliasApiController.php       Domain alias API endpoints
    SpamPolicyApiController.php        Spam policy API endpoints
    WhiteBlacklistApiController.php    White/blacklist API endpoints
    ThrottleApiController.php          Throttle API endpoints
    GreylistApiController.php          Greylist API endpoints
  Services/
    ActivityLogger.php                 Admin activity logging facade
    Fail2banService.php                Fail2ban CLI wrapper
    GeoIpService.php                   MaxMind GeoLite2 integration
    ExportService.php                  CSV/JSON export service
    VersionChecker.php                 GitHub version check (24h cache)
  Controllers/
    AuthController.php                 Login, logout, RBAC session setup
    DashboardController.php            Dashboard statistics + system info
    DomainController.php               Domain CRUD, catch-all, BCC, relay
    AdminController.php                Admin CRUD, domain assignment, resource limits
    UserController.php                 User CRUD, services, forwarding, aliases, BCC, relay, rename
    AliasController.php                Mail alias CRUD, members, moderators
    MailingListController.php          Mailing list CRUD, owners
    SpamPolicyController.php           Spam policy management
    WhiteBlacklistController.php       White/blacklist management
    SearchController.php               Global search
    SystemSettingsController.php       System settings overview + last logins
    ExportController.php               CSV/JSON export
    NewsletterController.php           Public newsletter subscription
    DomainAliasController.php          Domain alias CRUD
    LogController.php                  Activity log viewer
    DeletedMailboxController.php       Deferred deletion management
    AmavisdController.php              Quarantine + mail log
    Fail2banController.php             Ban/unban management + GeoIP
    IredapdController.php              Throttle, greylisting, rDNS, SenderScore
    PanelSettingsController.php        DB-backed panel settings editor
    BaseController.php                 404 error page handler
  Utils/
    LdapUtils.php                      DN construction, LDAP modify helpers
    PasswordUtils.php                  Password hashing (10+ schemes) + random generation
    PasswordVerifier.php               Password verification utility
    SystemInfo.php                     Hostname, uptime, load, version info
templates/                             42 native PHP templates
tests/
  bootstrap.php                        Test environment setup
  Utils/PasswordUtilsTest.php          Password hashing scheme tests
  Models/                              Model factory method tests
```

## Testing

```bash
php composer.phar install
vendor/bin/phpunit
```

44 tests covering password hashing schemes, password validation rules, and model factory methods. PHPUnit 13 is used as the test framework.

## License

This project is released into the public domain under the Unlicense.
