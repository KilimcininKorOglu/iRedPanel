# Changelog

All notable changes to this project will be documented in this file.

## [1.0.2] - 2026-07-26

### Added
- Multi-language UI: JSON-backed i18n core (`Translator`, `LocaleResolver`) initialized in bootstrap with template helpers (`$t()`, `$te()`)
- Language switcher UI with a `POST /language` route and per-admin language preference persisted across LDAP, MySQL, and PostgreSQL backends
- Default UI language exposed as a database-overridable panel setting
- 40-language locale coverage; all views, forms, and controller messages translated and kept at full key parity with `en_US`
- GitHub Actions CI workflow running PHPUnit and a PHP syntax check across PHP 8.1-8.4

### Changed
- Documentation updated for internationalization and the 40-language UI
- Documented Conventional Commits and commit-size guidelines in CONTRIBUTING

### Fixed
- Parse boolean fields (user, domain, spam policy) from JSON API request bodies
- Handle `json_encode` failures in API responses

### Security
- Reject malformed API JSON bodies with 400 and cap request body size before decoding
- Prefix risky CSV cells to prevent spreadsheet formula injection in exports

## [1.0.1] - 2026-07-25

### Added
- Database-backed panel settings with a web UI (`/panel-settings`)

### Changed
- Renamed the project to iRedPanel and switched the environment prefix to `IREDPANEL_`
- Relicensed the project under MIT
- Renamed the `api_keys` table to `panel_api_keys`
- Removed an ineffective catch-all filter from the MySQL `getUsers` query
- Added a `source` element to `phpunit.xml.dist` for coverage support

### Fixed
- Corrected `asMegabytes` to treat input as megabytes instead of bytes
- Added `{PLAIN-MD5}` prefix when prefixed scheme mode is enabled
- Validated `passwordDefaultScheme` in DB override and panel settings save
- Used exact domain matching instead of a LIKE suffix pattern
- Rejected `createUser` when the domain does not exist in the domain table
- Included all non-aggregate columns in MySQL admin and PostgreSQL domain `GROUP BY`
- Used backend-aware IredAdmin connection and default port for integration databases
- Routed `DeletedMailboxController` through `RepositoryFactory`
- Used CIDR-aware IP matching in the API middleware allowlist
- Validated email format in `getEmailDn` before splitting
- Checked `rowCount` after user update and threw on zero affected rows
- Added activity logging for admin create and edit operations
- Moved the rename form outside the outer user edit form

### Security
- Implemented REST API RBAC with database-backed API keys and enforced RBAC on the password verification endpoint
- Enforced password policy validation and prevented privilege escalation via the user creation API
- Prevented privilege escalation via the `domainGlobalAdmin` field
- Enforced `globalAdminRequired` on domain list and view/edit endpoints
- Restricted alias, mailing list, and activity log listings to global admins
- Restricted domain ownership verification to global admins and enforced it on domain creation
- Added transactional locking on user and domain creation to prevent TOCTOU on admin limits
- Enforced admin resource limits, domain quota, and mailbox count limits on creation and update
- Enforced domain alias count limit on alias and mailing list creation
- Checked domain authorization before database fetch in alias and mailing list views
- Prevented admin self-deletion, last global admin lockout, and bulk operations from wiping all global admins
- Rejected negative quota, limit, and domain API update values
- Required POST with CSRF token for logout and newsletter confirm, and added CSRF validation to iRedAPD POST handlers
- Blocked backslash-based open redirect in the login `next` parameter
- Escaped default branch output in the localize template filter
- Removed the confirmation token from the newsletter HTTP response
- Validated maildir path against the vmail base before deletion
- Validated the Fail2ban jail name against a configured allowlist and added IP validation on unban
- Hid global statistics from domain admins on the dashboard
- Replaced inline JS confirm dialogs with `data-confirm` attributes

## [1.0.0] - 2026-03-25

### Added
- PostgreSQL backend support (15 repository implementations, ancillary DB connections)
- Mail alias management with member/moderator CRUD, per-user aliases, and catch-all
- Mailing list management (mlmmj) with owner support and bulk operations
- Domain/user BCC settings (sender and recipient) across all three backends
- Sender-dependent relay host configuration per domain and user
- Spam policy management (global, per-domain, per-user thresholds via amavisd)
- Inbound/outbound white/blacklist management via amavisd wblist tables
- REST API v1 with 40+ endpoints for domains, users, aliases, mailing lists, admins, spam policy, throttle, and greylisting
- API key authentication with IP whitelist for REST API
- API password verification endpoint for external service integration
- Global search across domains, users, aliases, mailing lists, and admins
- Admin resource limits (max domains, users, aliases, mailing lists, quota per admin)
- Domain ownership verification via DNS TXT records
- Newsletter subscription management with public endpoints and token verification
- Last login tracking from Dovecot last_login table
- GeoIP country/city display for Fail2ban banned IPs (MaxMind GeoLite2)
- rDNS-based white/blacklist for iRedAPD
- SenderScore IP permanent whitelisting for iRedAPD
- Greylisting tracking data inspection
- Email address rename with referential integrity across all related tables
- Domain/admin statistics export (CSV and JSON)
- System settings overview page
- CSV bulk user import CLI tool
- Quarantine notification email CLI tool (cron-compatible)
- Domain disclaimer dump CLI tool
- Quarantined mail export CLI tool
- Session invalidation CLI tool

### Changed
- RepositoryFactory now uses backend-aware instantiation for Amavisd and iRedAPD controllers (was hardcoded MySQL)

### Fixed
- Amavisd and iRedAPD features now work correctly with PostgreSQL backend

## [0.9.0] - 2026-03-24

### Added
- Domain CRUD with create, edit, delete, and pagination
- Admin account management with standalone and mailbox-based admin support
- User deletion with deferred mailbox retention
- Pagination across all list views (configurable via IREDPANEL_PAGINATION_PER_PAGE)
- Mail service toggles (SMTP, POP3, IMAP, ManageSieve, SOGo + secured variants)
- Email forwarding management with keep-copy toggle
- Used quota display on user list and detail views
- Bulk user operations (enable/disable/delete)
- LDAP user creation (previously threw RuntimeException)
- Dashboard with domain/user/admin/quota statistics
- Activity logging to iRedAdmin database with admin/IP/event tracking
- Role-based access control (global admin vs domain admin)
- Domain admin login support
- Session timeout and IP restriction (CIDR notation)
- Activity log viewer with domain/event filters
- Deferred mailbox deletion with cancel/reschedule
- Domain settings (default quota, password policies, disclaimer)
- Branding support (name, logo, footer text, primary color)
- CLI tools: bulkPasswordUpdate, bulkQuotaUpdate, deleteExpiredMailboxes, exportUsers, promoteToGlobalAdmin
- Amavisd integration: quarantine viewer, release, mail log, cleanup
- Fail2ban integration: jail status, ban/unban management
- iRedAPD integration: throttle settings, greylisting, sender whitelist
- Feature flags for external integrations in navigation
- Old password verification on password change (configurable)
- Random password generator with policy compliance (server + client-side)
- Status filter tabs (All/Active/Disabled) on domain and user lists
- Log entry deletion (individual and bulk)
- Session IP change detection (IREDPANEL_SESSION_VALIDATE_IP)
- Failed login attempt tracking with activity logging
- Bulk domain operations (enable/disable/delete)
- Bulk admin operations (enable/disable/delete)
- Dashboard system information (hostname, uptime, load, software versions)
- GitHub version check with 24-hour cache (IREDPANEL_CHECK_UPDATES)
- Domain alias management with full CRUD (alias_domain table / LDAP domainAliasName)
- CLI: dumpDisclaimer (Postfix integration), dumpQuarantinedMails, invalidateSessions

### Changed
- Root redirect changed from /domains to /dashboard
- README comprehensively rewritten to document all features

### Fixed
- RBAC enforcement in UserController (domain admins restricted to managed domains)
- Activity logging added to all user operations
- Amavisd quarantine release via amavisd-release CLI
- Navigation links for Deleted Mailboxes and iRedAPD
- Configurable Amavisd cleanup retention days
- Domain deletion now cleans up alias_domain references
- Repository interfaces added for Amavisd and iRedAPD

## [0.3.0] - 2026-03-24

### Added
- PHPUnit test infrastructure with unit tests for PasswordUtils and UserPassword

### Changed
- APP_VERSION now read from Composer metadata instead of hardcoded constant
- Introduced shared `BackendConnectionException` base class for unified error handling
- Suppressed deprecated `crypt()` notices with documentation
- Documented `extract()` usage and EXTR_SKIP safety in template engine
- Removed unused `getUserDn` and `isSupportedPasswordScheme` methods
- Removed unused `name` and `templatesAutoReload` settings
- Removed stale Python reference from route comments
- Removed unimplemented CSV import button from user list

### Fixed
- Null-safe operator in user creation template prevents crash on new form
- Inverted prefix condition in doveadm password hash generation
- Login form hidden `next` field now has name attribute for redirect
- Zero quota preserved as unlimited instead of defaulting to 100 MB
- Aligned quota handling across backends for unlimited accounts
- LDAP modify operations now check return values and throw on failure
- Null-coalescing operator used for env var lookup to handle "0" values correctly
- Router returns 405 when URL matches but HTTP method does not
- `editMode` parameter validated; unknown values return 404
- Mail storage paths configurable for MySQL user creation
- User creation UI hidden when backend does not support it

### Security
- CSRF token generation and validation for all POST forms
- Session ID regenerated after successful authentication
- Redirect target validated in login to prevent open redirect
- Route uid enforced over form body in user edit
- Secure flag added to session cookie configuration
- LDAP TLS certificate verification made configurable
- Password piped via stdin to doveadm instead of command-line argument

## [0.2.0] - 2026-03-24

### Added
- MySQL/MariaDB backend support via Repository pattern
- Backend selection via `IREDPANEL_BACKEND` environment variable (`ldap` or `mysql`)
- Repository interfaces for auth, domain, and user operations
- `RepositoryFactory` for backend-driven dependency resolution
- MySQL repository implementations (auth, domain, user CRUD)
- `MysqlConnection` PDO singleton with proper error handling
- MySQL password hash verification (SSHA512, SSHA, CRYPT, SHA512, PLAIN-MD5, PLAIN)
- Extension validation at startup (ext-ldap or ext-pdo_mysql)
- Full user creation support for MySQL backend

### Changed
- Environment variable prefix renamed from `IREDADMIN_LIGHT_` to `IREDPANEL_`
- Controllers fully decoupled from LDAP — now use repository interfaces
- `User::$mailQuota` standardized to always store megabytes (LDAP converts bytes at repo boundary)
- Only active backend's settings are validated at startup
- `ext-ldap` moved from required to suggested in composer.json
- LDAP `createUser` now throws RuntimeException instead of silent no-op

### Fixed
- `{CRYPT}` password verification now uses `crypt()` to support MD5-crypt alongside bcrypt
- MySQL `getUsers` excludes catch-all mailbox entries (matching LDAP behavior)
- Bare MD5 hex hash detection in password verification fallback
- `userCreateView` now actually calls `createUser` and redirects on success
- `$error` variable properly passed to user creation template
