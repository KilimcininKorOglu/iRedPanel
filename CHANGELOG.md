# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- A domain and an admin carry an expiry date as well (`domain.expired` and `admin.expired` in SQL, the `expiredDate` attribute in LDAP): the last day the account works. The field sits on the domain general page and the admin general page, and the REST API reads and writes it as `expiredDate`. An admin that is a mailbox stores the date in `mailbox.expired`, as iRedMail does. An empty field means that the account never expires, which is the default
- A mailbox carries an expiry date (`mailbox.expired` in SQL, the `expiredDate` attribute in LDAP): the last day it works. The field sits on the user profile page and the REST API reads and writes it as `expiredDate`. An empty field means that the mailbox never expires, which is the default
- The iRedAPD pages carry an SRS exclude domains tab: the domains whose sender address iRedAPD keeps when it forwards a message, instead of rewriting it with SRS. The list is the whole table (`iredapd.srs_exclude_domains`), so a domain that the form no longer carries loses its exclusion, and only a global admin opens the page. The list is empty by default
- A mailbox carries a Shared Folders tab: the mailboxes that may open its IMAP folders, the mailboxes that share their folders with it, and whether it shares with every account of the server. A row is removed with one button, which ends that share, because Dovecot reads `share_folder` and `anyone_shares` as the authoritative list. The panel adds no row, because only the user shares a folder. With the SQL backends the tables live in the vmail database, with LDAP in the iredadmin database, and without that database the tab stays hidden
- A spam policy carries the Amavisd banned rule names (`policy.banned_rulenames`): the five rules that iRedMail defines in `%banned_rules` of `amavisd.conf` are checkboxes, and a server that defines own rules takes them in a free field. A rule name lets an account receive attachment types that the banned file list blocks, for example `ALLOW_MS_OFFICE` for Office documents. Without a name the account inherits the rule names of the next policy, and a list longer than the 64 characters of the column is refused instead of being cut. The REST API reads and writes the list as `bannedRulenames`
- A mailbox carries its own disclaimer text on a new Disclaimer tab (`mailbox.disclaimer` in SQL, the `disclaimer` attribute in LDAP), next to the disclaimer that a domain already had. `php cli/dumpDisclaimer.php --output=...` now also writes `<user@domain>.txt` and `<user@domain>.html`, and removes them again when the text is emptied, so the Amavisd map `@disclaimer_options_bysender_maps` can carry a per-address entry. The REST API reads and writes the text as `disclaimer`, and a global admin closes the tab for the domain admins of a domain like any other user page
- A domain carries a DNS tab that checks the records the mail needs: MX, the A and AAAA records of every MX host, SPF, DKIM, DMARC and the PTR record of every MX address, each with its answer and the records that were found. The check reads DNS only, so a domain admin opens it for a managed domain as well. The DKIM row asks `<selector>._domainkey.<domain>` with the new panel setting `dkimSelector` (Panel Settings, Mail tab, default `dkim`) and reports only whether a DKIM record exists there, because the private key lives in `/var/lib/dkim/` on the mail server. A run stops after 8 seconds and marks the remaining checks as not checked, so a silent resolver never holds the page
- The iRedAPD pages carry an SMTP Sessions tab: every SMTP session that iRedAPD answered, newest first, filtered by action, by address (sender, recipient or SASL user) or by client IP, with a detail page that shows the whole row. A row adds its sender to the inbound white or black list of the recipient, or its rDNS name to the iRedAPD rDNS white/blacklist; the addresses come from the stored session, never from the form. iRedAPD writes `iredapd.smtp_sessions` while `LOG_SMTP_SESSIONS` is on, which is its default, and its own job removes the old rows, so the panel only reads the table
- An admin protects the sign-in with a one-time code (TOTP, RFC 6238) on the new Two-Factor Auth page: the setup shows a QR code that the browser draws itself, the secret in readable form and 10 one-time recovery codes. A confirmed setup asks for a code after every password, and a recovery code replaces the app once. The secret lives in the iredadmin table `panel_admin_totp`, encrypted with the panel secret key, and the recovery codes are stored as hashes. The panel setting `adminTotpRequired` (Panel Settings, Session tab) holds every admin on the setup page until the setup is finished, and `php cli/disableAdminTotp.php --email=...` removes the setup of an admin who lost both the app and the codes. A mailbox user of self-service is never asked for a code
- A mailbox user who cannot sign in asks for a reset link on `/forgot-password`. The panel mails a link to the recovery address of the mailbox, and the link opens a page that sets a new password against the domain password policy. The pending requests live in the iredadmin table `panel_password_resets`, which holds only the SHA-256 hash of the token; a link expires after 2 hours, a repeated request within 5 minutes sends no second mail, and a used link stops working. The pages answer the same way for a known and an unknown address. The panel setting `passwordRecoveryEnabled` (Panel Settings, Password tab) publishes the pages and the sign-in page link, and the feature needs the public URL, the SMTP settings and the iredadmin database
- A user profile carries a department and a birthday, next to the fields the panel already managed. Both columns exist in the iRedMail schema of all three backends (`mailbox.department` and `mailbox.birthday` in SQL, `departmentNumber` and `birthday` in LDAP), so no schema change is needed. The birthday is a date field in the form and an empty value stores the column default
- The user list and the password tab of a user show the date of the last password change. The LDAP backend now writes `shadowLastChange` on every password change and on mailbox creation, as iRedAdmin does; the SQL backends already wrote `mailbox.passwordlastchange`. A mailbox whose backend holds no date shows None
- A mailbox carries a login IP restriction (`mailbox.allow_nets` in SQL, `allowNets` in LDAP): Dovecot then accepts a login only from the listed IP addresses and CIDR ranges. An empty value allows every address and writes SQL NULL, because Dovecot refuses every login of a mailbox whose value is an empty string
- A quarantined message opens on its own page, in the admin quarantine and in the self-service quarantine: the headers a reader needs (including `X-Spam-Status`), the raw header block and the body, all as plain text. A button downloads the whole message as an `.eml` file. An admin reads only a message that waits for one of its domains, and a mailbox user only a message that waits for the mailbox
- The admin quarantine and the self-service quarantine release or delete several messages in one request: a checkbox per row, a select-all box in the header and a bulk action menu, as the other list pages have
- A mailbox carries a recovery address (`mailbox.recovery_email` in SQL, `recoveryEmail` in LDAP). An admin sets it on the user page, the user sets it on the self-service profile page, and the REST API reads and writes it as `recoveryEmail`
- Self-service has a Sent Mails page next to Received Mails: it lists the mail that Amavisd saw from the address of the user, one row per recipient, with the spam score and the content type. A domain admin opens or closes it with the new `sent` user preference
- A quarantine row whitelists or blacklists its sender in one click, in the admin quarantine and in the self-service quarantine. The sender and the recipients come from the stored message, never from the form, so an admin writes the entry only for the recipients of its own domains and a mailbox user only for the own address
- The search page finds a mailbox through its per-account alias addresses, on all three backends (`forwardings.is_alias` in SQL, `shadowAddress` in LDAP). An admin who only knows the alias address no longer has to open every mailbox
- A domain admin can no longer set a mailbox quota below the size the mailbox already stores, in the web form and in the REST API. Dovecot would otherwise refuse every new message without an explanation. A global admin may still set any quota, and an unlimited quota stays allowed for everyone

## [1.0.5] - 2026-09-20

### Added
- The sidebar shows a global admin that a newer iRedPanel release exists, on every page instead of only on the dashboard. The badge links to the compatibility page, and it stays hidden when the update check is off or the installed release is the newest one
- Panel Settings has an Outgoing Mail tab and manages the SMTP connection, the public URL and the newsletter link lifetime, so a mail server move no longer needs an `.env` edit and a container restart
- The Integrations tab manages activity logging, the Amavisd quarantine host and port and the mlmmjadmin API URL, next to the integration toggles it already held
- The SMTP password and the mlmmjadmin API token are editable from the panel. They are stored encrypted with `IREDPANEL_SECRET_KEY` in `panel_settings`, and an installation without that key keeps the `.env` value

### Security
- A stored secret is never written back into the settings page. The REST API key, the SMTP password and the mlmmjadmin API token render as empty password fields; an empty field keeps the stored value and a checkbox clears it

## [1.0.4] - 2026-09-20

### Changed
- `composer.lock` is tracked and `config.platform.php` pins the resolution to the `require` floor of 8.1.0, so every install gets the same PHP 8.1-compatible set and a dependency audit sees the versions that CI and the servers actually get
- Every method is at or below a cyclomatic complexity of 10: the user and domain page controllers delegate to one method per tab, `Settings` loads its values through single-purpose helpers, the search repositories loop a table map, and the API controllers, `Middleware` and `DirectoryClient` extract their validation blocks
- `PasswordVerifier::verify()` resolves the prefixed hash schemes through one `match` instead of nine sequential prefix checks, and the two salted schemes share one helper
- `Settings` builds the connection values of the selected backend outside the constructor, over a neutral set for the other two backends
- The code uses the PHP 8.1 idioms throughout: first-class callables, readonly properties, `never` return types and enum-style constants
- Every static analysis error outside the templates is cleared, including the redundant null checks that shadowed a real branch

### Security
- Every GitHub Actions step is pinned to a commit SHA instead of a moving tag

## [1.0.3] - 2026-09-20

### Added
- Account pickers (Tom Select) on address fields: alias members and moderators, mailing list owners, moderators and subscribers, forwarding, BCC, catch-all, admin creation, and the Amavisd and iRedAPD account fields
- `GET /ajax/accounts` session endpoint for the pickers, limited to the domains of a domain admin
- `compatibility.json` lists the iRedMail versions that each iRedPanel release is compatible with (1.0.2: iRedMail 1.7.4, 1.8.0, 1.8.1, 1.8.2, 1.8.3, 1.8.4, 1.8.5, 1.8.6, 1.8.7, 1.8.8)
- **System > iRedMail Compatibility** page (`/compatibility`) lists every release from `compatibility.json`; the dashboard shows the compatible iRedMail versions of the installed release and a newer release with its iRedMail versions
- Installation guides for the OpenLDAP, MariaDB/MySQL and PostgreSQL backends in English and Turkish (`docs/install/`), each with a native and a Docker method
- `cli/deleteUnmatchedThrottles.php [--dry-run]` deletes the iRedAPD throttle rows of CIDR and `user@*` accounts
- Account replication from Active Directory and Samba AD (**System > Account Resources**, global admin): users, and optionally groups as mail aliases, are created, updated, renamed and disabled in one hosted domain. Each resource has Connection, Replication, Users and Groups settings, a connection test with a preview, "Replicate now" and a replication log per run
- `cli/replicateAccounts.php [--resource=ID] [--force] [--dry-run] [--allow-mass-disable]` runs the due resources from cron every minute
- Replicated accounts show an "Active Directory" badge; the directory-owned fields are read-only in the web UI and the REST API answers 409 when a request changes them
- `App\Utils\SecretBox` encrypts secrets that the panel reads back (libsodium, key derived from `IREDPANEL_SECRET_KEY`)
- REST API: `GET /api/v1/domains/{domain}` returns and `PUT` sets `catchall`, `senderBcc`, `recipientBcc` and `relayhost` (null removes the value)
- REST API: `GET /api/v1/users/{email}` returns and `PUT` sets `forwardings`, `keepCopy`, `senderBcc`, `recipientBcc` and `relayhost`; `addForwardings` and `removeForwardings` change the forwarding list. A domain key gets 403 when the user page of a field is closed
- Disabled mail services per domain (Settings tab and REST API `disabledMailServices`): a new mailbox from the web form, the REST API, `cli/importUsers.php` or account replication starts with these services off, as in iRedAdmin (SQL `disabled_mail_services`, LDAP `disabledMailService`)
- Mailing list limit per domain (`lists`: SQL `domain.maillists`, LDAP `numberOfLists`), checked when the web form or the REST API creates a list
- REST API: `GET /api/v1/domains/{domain}` returns and `POST`/`PUT` set `defaultUserQuota`, `minPasswordLength`, `maxPasswordLength`, `disclaimer` and `disabledMailServices`
- Backup MX per domain (General tab and REST API `backupMx`, `primaryMx`): the domain gets the transport `relay:<primary MX>` (SQL `domain.backupmx`, LDAP `domainBackupMX`), and turning it off restores `dovecot`
- Page toggles per domain (Settings tab and REST API `disabledDomainProfiles`, `disabledUserProfiles`, `disabledUserPreferences`, `selfService`), stored with the iRedAdmin keys (SQL `disabled_domain_profiles`, `disabled_user_profiles`, `disabled_user_preferences`, `enabled_services`; LDAP `disabledDomainProfile`, `disabledUserProfile`, `disabledUserPreference`, domain `enabledService=self-service`)
- Self-service for mailbox users (`/self`): when the domain has self-service on, a mailbox user logs in on `/login` and manages the own name and language, password (the current password is always required), forwarding, white/blacklist and spam policy, and sees the own quarantined and received mail. The user releases or deletes a quarantined message for the own address only, and blocks the sender of a received message. The domain admin closes single preferences; the Amavisd pages need the Amavisd integration. A self-service session gets a redirect to `/self` (GET) or 403 (other methods) on every admin page
- User language (General tab and REST API `language`), stored in `mailbox.language` (SQL) and `preferredLanguage` (LDAP); a self-service login applies it
- Mail alias rename in the same domain (alias page and REST API `POST /api/v1/aliases/{address}/rename` with `newAddress`): the members, the moderators, the forwardings and BCC settings that point to the alias, and its Amavisd and iRedAPD settings follow the new address
- REST API: `POST /api/v1/users/{email}/rename` with `newEmail` renames a mailbox in the same domain (global key), as the web UI does; both rename endpoints answer 409 when the address is in use or the directory manages the account
- Domain admins per domain (Admins tab of the domain page and REST API `PUT /api/v1/domains/{domain}/admins` with `addAdmins`, `removeAdmins`, `removeAllAdmins`; `GET /api/v1/domains/{domain}` returns `admins`). A global admin adds an existing admin or a mailbox; a domain admin or a domain key adds and removes only the mailboxes of its own domains, as in iRedAdmin-Pro. A mailbox that is added becomes a domain admin (SQL `mailbox.isadmin`, LDAP `enabledService=domainadmin`) and loses the flag with its last domain
- A domain admin lists, creates, deletes, enables and disables the mail aliases and mailing lists of its own domains, as in iRedAdmin-Pro. The menu shows both pages, the domain filter and the create form offer only the managed domains, and a request for another domain is refused
- Admin creation limits apply as in iRedAdmin-Pro: a domain admin with domain creation allowed creates domains and becomes their admin, and the domain, mailbox, alias and mailing list limits and the total mailbox quota (MB) bound what it creates in its domains. A mailbox with unlimited quota is refused when the quota is limited, and a lower quota is always allowed. The limits never bind a global admin, and the domain create form of a domain admin has no domain limit fields
- Admin language and creation limits when an admin is created: the admin create form and `POST /api/v1/admins` accept `language` and the resource limits. The General tab of an admin and `PUT /api/v1/admins/{email}` change the language, and `GET` returns it (SQL `admin.language` or `mailbox.language`, LDAP `preferredLanguage`)
- Days to keep a deleted mailbox (user list, domain list and REST API query parameter `keepMailboxDays` of `DELETE /api/v1/users/{email}` and `DELETE /api/v1/domains/{domain}`), as iRedAdmin `keep_mailbox_days`: the value sets `deleted_mailboxes.delete_date`, which `cli/deleteExpiredMailboxes.php` reads. A domain admin or a domain key chooses 1 to 365 days, a global admin or a global key also 730 or 1095 days or 0 (forever). Without the value the REST API keeps the mailbox forever, as before
- A domain admin opens the quarantine and the mail log of its own domains. The domain admin releases and deletes only the copies of the recipients in the selected domain, and the quarantine cleanup stays global admin only. Two admin permissions close these pages for one domain admin (Limits tab, admin create form and REST API `disableViewingMailLog`, `disableManagingQuarantinedMails`), stored with the iRedAdmin keys (SQL settings `disable_viewing_mail_log:yes` and `disable_managing_quarantined_mails:yes`, LDAP `disabledService=view_mail_log` and `disabledService=manage_quarantined_mails`)
- Bulk user update: the user list applies a language, a password or a transport to the selected mailboxes, and `PUT /api/v1/domains/{domain}/users` writes `accountStatus`, `password`, `language` or `transport` to every mailbox of the domain, or to the mailboxes in `users`. The transport needs a global admin or a global key, the password passes the domain password policy, and a status change of a replicated account is refused before the first write
- Per-user Postfix transport (Relay tab of a mailbox and REST API `transport` of `GET`/`PUT /api/v1/users/{email}`), stored in `mailbox.transport` (SQL) and `mtaTransport` (LDAP). An empty value uses the transport of the domain. Only a global admin or a global key sets it, because a wrong transport loses mail
- REST API: `GET /api/v1/users/{email}` returns `aliases`, and `PUT` sets the per-user aliases (`aliases`, `addAliases`, `removeAliases`) and the mail services by their iRedMail names (`services`, `addServices`, `removeServices`), as iRedAdmin-Pro `aliases`/`addAlias`/`removeAlias` and `services`/`addService`/`removeService`. An alias of another domain, an address in use and an unknown service are refused before anything is written
- List filters in the REST API, as iRedAdmin-Pro `disabled_only`, `email_only` and `name_only`: `?disabledOnly=yes` returns only the disabled accounts of `GET /api/v1/domains`, `/domains/{domain}/users`, `/aliases` and `/mailing-lists`; `?emailOnly=yes` (`?nameOnly=yes` for domains) returns the addresses instead of the profiles. The mail alias and mailing list pages of the web UI get the status filter that the user and domain lists already have
- Spam policy: bypass of the banned-file and bad-header checks, a quarantine choice per type (Amavisd default, quarantine, do not quarantine, written to `spam_quarantine_to`, `virus_quarantine_to`, `banned_quarantine_to` and `bad_header_quarantine_to`), and a switch that adds the X-Spam headers to every message by setting the tag level to -999. `DELETE /api/v1/spam-policy/{account}` removes the policy, as the page button does, and `PUT` now changes only the fields that the body carries
- Mailing list mlmmj options in the web UI and the REST API: the list page has a card for the subject prefix, the plain-text and HTML footer, the custom and removed headers, the extra addresses, the subscription moderators, and 16 switches (closed list, moderation, archive, digest, nomail, old posts, notifications). `GET /api/v1/mailing-lists/{address}` returns them under `options` and `PUT` writes the fields it carries; `?withSubscribers=yes` adds the subscribers
- Subscription version and confirmation mail when subscribers are added (list page and `POST /api/v1/mailing-lists/{address}/subscribers` with `subscription` of `normal`, `digest` or `nomail` and `requireConfirm`). Without a confirmation the address becomes a member at once, as before
- Keep or remove the mlmmj archive on delete: the list page and the bulk form have a checked "keep the archive" box, and `DELETE /api/v1/mailing-lists/{address}?keepArchive=no` removes the messages of the list as well
- REST API: `PUT /api/v1/aliases/{address}` changes the members of a mail alias one by one with `addMembers` and `removeMembers`, next to the full `members` list. The two forms are refused together, and an invalid address stops the request before anything is written
- LDIF export on the LDAP backend: the user list of a domain downloads the LDAP subtree of that domain, and **System Settings** downloads the whole tree (global admin). `GET /api/v1/ldif` and `GET /api/v1/ldif/{domain}` answer with LDIF text. The file carries every attribute, including the password hashes, and the page says so
- Mail lists on the LDAP backend (**Accounts > Mail Lists**): a group account whose members the admin manages, with an access policy (`public`, `domain`, `subdomain`, `membersOnly`, `moderatorsOnly`, `allowedOnly`), moderators, allowed senders and a maximum message size. A member address outside the served domains becomes a `mailExternalUser` entry, and it goes with the last list that holds it. `GET, POST /api/v1/mail-lists` and `GET, PUT, DELETE /api/v1/mail-lists/{address}` serve the same data, with `addMembers` and `removeMembers`; a SQL backend answers 400 and hides the menu item
- Greylisting: the page lists every account that has an own setting and removes one account's setting with its whitelisted senders, and the new **Whitelisted domains** tab manages the domains whose SPF records the iRedAPD job resolves into whitelisted senders, with the resolved senders per domain. `GET /api/v1/greylist` lists the accounts, `DELETE /api/v1/greylist/{account}` removes a setting, `PUT` changes the senders with `addSenders` and `removeSenders`, and `GET`/`PUT /api/v1/greylist-whitelist-domains` reads and writes the domains
- White/blacklist: the page adds several addresses at once through the account picker, filters the lists with the "All", "Whitelist" and "Blacklist" pills, and removes every shown entry of one direction with one button. `GET /api/v1/wblist/{account}?wb=W` returns one kind, `POST` takes a `senders` array next to `sender`, `DELETE` takes `senders` or `{"all": true}` with an optional `wb` filter, and every answer carries the number of entries it changed. One invalid address stops the whole request
- A help icon next to every labelled field of the admin panel, the login page and the self-service pages: the icon opens a popover that says what the field does, what a change to it causes and what the default is. The 31 hints that stood under a field moved into the popover, and the texts exist in all 40 locales
- New mailbox options in the user create form and `POST /api/v1/domains/{domain}/users`, as iRedAdmin-Pro `password_hash`, `maildir`, `mailboxFormat`, `mailboxFolder` and `language`: `passwordHash` stores a given hash (one of the iRedMail schemes, not checked by the password policy, refused together with a password); `mailboxFormat` (`maildir`, `mdbox`, `sdbox`) and `mailboxFolder` set the Dovecot mailbox (SQL `mailboxformat`, `mailboxfolder`, LDAP `mailboxFormat`, `mailboxFolder`); `maildir` sets an absolute mailbox path (global admin or global key only), refused when another mailbox uses the path or a parent directory of it. The create form also sets the name and the language

### Changed
- The dashboard reads new releases from `compatibility.json` on GitHub (24-hour cache) and falls back to the bundled copy; this replaces the GitHub release check
- The dashboard no longer shows an iRedMail version row, which read `/etc/iredmail-release` and showed N/A when the panel ran on another host or in a container
- New web UI: Bootstrap 5.3 black dark theme with a left sidebar menu and an offcanvas menu on small screens
- Delete and bulk confirmations use SweetAlert2 dialogs, and flash messages show as toasts
- Bootstrap, Bootstrap Icons, SweetAlert2 and Tom Select are vendored under `public/static/vendor/`; Chota CSS is removed
- The search page type filter uses toggle chips
- A domain admin now edits the Settings, Catch-all, BCC and Relay pages of a managed domain, except the pages that the global admin closed; the General page (limits, transport, backup MX) and the page toggles stay global admin only. A domain admin cannot lower the min password length below the global minimum
- A domain API key can set the fields of these pages with `PUT /api/v1/domains/{domain}`, and a closed domain or user page answers 403 for a domain key

### Fixed
- LDAP: the alias and mailing list counts of an admin were always 0, because they read a property that the page result does not have
- LDAP: the stored language of an admin (`preferredLanguage`) was never read, so a login did not apply it
- Admin creation limits were stored as JSON, which iRedAdmin cannot read, and a save replaced the other settings of the admin. They are now stored in the iRedAdmin form (`create_max_users:10;`, `create_new_domains:yes`), the other settings stay, an unlimited value is not written, and the limits of a mailbox admin go to `mailbox.settings` instead of the `admin` table. Stored JSON is still read and is converted on the next save
- `composer install` failed on PHP 8.1 to 8.3, because PHPUnit 13 needs PHP 8.4.1 and `composer.lock` is not tracked; PHPUnit 10.5 to 13 is now accepted
- CI failed in `composer validate --strict` on the intentional `version` field
- The throttle page and `/api/v1/throttle/{account}` accepted CIDR networks and `user@*` addresses, which the iRedAPD throttle plugin never matches; these accounts are now rejected
- A save on the domain Settings tab deleted the `domain.settings` keys that the panel does not manage, for example the `default_language`, `timezone` and `disabled_domain_profiles` keys of iRedAdmin; these keys are now kept

### Security
- `PUT /api/v1/domains/{domain}` accepted a domain-scoped API key, so that key could raise the mailbox, alias and quota limits of its own domain; the endpoint now requires a global key, as the web panel lets only a global admin edit a domain

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
