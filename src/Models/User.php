<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;
use App\Middleware;
use App\Utils\FormValue;
use App\Utils\NameList;
use App\Utils\WholeNumber;

/**
 * User data model. Maps to iRedMail user attributes.
 * mailQuota is always stored in megabytes regardless of backend.
 */
class User
{
    /**
     * enabledService values of a mailbox that no toggle controls. Dovecot and Postfix
     * accept a mailbox only with `mail`, and Dovecot checks `<service><secured|tls>` per login.
     */
    private const LDAP_BASE_SERVICES = [
        'mail', 'deliver', 'lda', 'lmtp', 'forward', 'senderbcc', 'recipientbcc',
        'internal', 'doveadm', 'lib-storage', 'indexer-worker', 'dsync',
        'shadowaddress', 'displayedInGlobalAddressBook',
    ];

    /**
     * enabledService values per toggle. A TLS login checks `*tls`, and ManageSieve logs in
     * as the service `sieve`, so each toggle also covers these values.
     */
    private const LDAP_TOGGLE_SERVICES = [
        'enableSmtp' => ['smtp'],
        'enableSmtpSecured' => ['smtpsecured', 'smtptls'],
        'enablePop3' => ['pop3'],
        'enablePop3Secured' => ['pop3secured', 'pop3tls'],
        'enableImap' => ['imap'],
        'enableImapSecured' => ['imapsecured', 'imaptls'],
        'enableManagesieve' => ['managesieve', 'sieve'],
        'enableManagesieveSecured' => ['managesievesecured', 'sievesecured', 'sievetls'],
        'enableSogo' => ['sogo'],
    ];

    /**
     * Mail service name (as in the disabled_mail_services domain setting and in the
     * REST API) => toggle property.
     */
    public const SERVICE_TOGGLES = [
        'smtp' => 'enableSmtp',
        'smtpsecured' => 'enableSmtpSecured',
        'pop3' => 'enablePop3',
        'pop3secured' => 'enablePop3Secured',
        'imap' => 'enableImap',
        'imapsecured' => 'enableImapSecured',
        'managesieve' => 'enableManagesieve',
        'managesievesecured' => 'enableManagesieveSecured',
        'sogo' => 'enableSogo',
    ];

    /** Toggle property => label in the web UI. */
    public const SERVICE_LABELS = [
        'enableSmtp' => 'SMTP', 'enableSmtpSecured' => 'SMTP (TLS)',
        'enablePop3' => 'POP3', 'enablePop3Secured' => 'POP3 (TLS)',
        'enableImap' => 'IMAP', 'enableImapSecured' => 'IMAP (TLS)',
        'enableManagesieve' => 'ManageSieve', 'enableManagesieveSecured' => 'ManageSieve (TLS)',
        'enableSogo' => 'SOGo Webmail',
    ];

    public function __construct(
        public string $uid,
        public bool $accountStatus = false,
        public int $mailQuota = 100,
        public string $cn = '',
        public string $givenName = '',
        public string $sn = '',
        public string $employeeNumber = '',
        public string $title = '',
        public string $department = '',
        /** Birthday as YYYY-MM-DD; '' means not set. */
        public string $birthday = '',
        public string $mobile = '',
        public string $telephoneNumber = '',
        /** Comma-separated IP addresses and CIDR ranges the mailbox may log in from; '' allows every address. */
        public string $allowNets = '',
        public bool $domainGlobalAdmin = false,
        // Mail service toggles
        public bool $enableSmtp = true,
        public bool $enableSmtpSecured = true,
        public bool $enablePop3 = true,
        public bool $enablePop3Secured = true,
        public bool $enableImap = true,
        public bool $enableImapSecured = true,
        public bool $enableManagesieve = true,
        public bool $enableManagesieveSecured = true,
        public bool $enableSogo = true,
        /** Preferred UI language (xx_YY); '' uses the default, null means not loaded and keeps the stored value on update. */
        public ?string $language = null,
        /** Date of the last password change as YYYY-MM-DD; null when the backend holds none. Read only. */
        public ?string $passwordLastChange = null,
    ) {}

    /**
     * Checks a comma-separated list of IP addresses and CIDR ranges.
     *
     * @return string the entries without duplicates, separated by a comma
     * @throws InvalidInputException naming the first entry that is not an address or a range
     */
    public static function validAllowNets(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (!is_string($value)) {
            throw new InvalidInputException('allowNets must be text', 'user.msg_invalid_allow_nets');
        }
        $entries = array_values(array_unique(array_filter(array_map(trim(...), explode(',', $value)))));
        foreach ($entries as $entry) {
            if (!Middleware::isValidIpRange($entry)) {
                throw new InvalidInputException(
                    "allowNets entry '{$entry}' is not an IP address or a CIDR range",
                    'user.msg_invalid_allow_nets',
                );
            }
        }

        return implode(',', $entries);
    }

    /**
     * The value of the SQL `allow_nets` column. The column is nullable, and Dovecot
     * refuses every login of a mailbox whose value is an empty string.
     */
    public function allowNetsSql(): ?string
    {
        return $this->allowNets === '' ? null : $this->allowNets;
    }

    /** The value that the SQL `birthday` column holds when no birthday is set. */
    public const EMPTY_BIRTHDAY = '0001-01-01';

    /**
     * @throws InvalidInputException when the value is not '' or a date in YYYY-MM-DD format
     */
    public static function validBirthday(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            [$year, $month, $day] = array_map(intval(...), explode('-', $value));
            if (checkdate($month, $day, $year)) {
                return $value;
            }
        }

        throw new InvalidInputException('birthday must be a date in YYYY-MM-DD format', 'user.msg_invalid_birthday');
    }

    /**
     * Converts a stored SQL `passwordlastchange` value to a date. The MySQL column
     * default is the epoch, which means that no password change was ever recorded.
     */
    public static function passwordChangeFromSql(mixed $stored): ?string
    {
        $date = substr((string) ($stored ?? ''), 0, 10);

        return $date === '' || $date === '1970-01-01' ? null : $date;
    }

    /**
     * Converts the LDAP `shadowLastChange` value (days since the epoch) to a date.
     * A missing or zero value means the backend holds no date.
     */
    public static function passwordChangeFromShadow(mixed $days): ?string
    {
        $value = (int) ($days ?? 0);

        return $value > 0 ? gmdate('Y-m-d', $value * 86400) : null;
    }

    /** The `shadowLastChange` value of today: the days since the epoch. */
    public static function shadowToday(): string
    {
        return (string) intdiv(time(), 86400);
    }

    /** Converts a stored SQL birthday to the model value; the column default means not set. */
    public static function birthdayFromSql(mixed $stored): string
    {
        $value = (string) ($stored ?? '');

        return $value === self::EMPTY_BIRTHDAY ? '' : $value;
    }

    /** Converts the model value to the SQL column value; the column is NOT NULL. */
    public function birthdaySql(): string
    {
        return $this->birthday === '' ? self::EMPTY_BIRTHDAY : $this->birthday;
    }

    /**
     * @throws InvalidInputException when the value is not '' or a locale code such as de_DE
     */
    public static function validLanguage(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (!is_string($value) || preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $value) !== 1) {
            throw new InvalidInputException('language must be a locale code such as en_US', 'user.msg_invalid_language');
        }

        return $value;
    }

    /**
     * Copies the mail service toggles from another User. The general profile
     * form does not submit them, so an update from that form must keep them.
     */
    public function copyServicesFrom(User $other): void
    {
        $this->enableSmtp = $other->enableSmtp;
        $this->enableSmtpSecured = $other->enableSmtpSecured;
        $this->enablePop3 = $other->enablePop3;
        $this->enablePop3Secured = $other->enablePop3Secured;
        $this->enableImap = $other->enableImap;
        $this->enableImapSecured = $other->enableImapSecured;
        $this->enableManagesieve = $other->enableManagesieve;
        $this->enableManagesieveSecured = $other->enableManagesieveSecured;
        $this->enableSogo = $other->enableSogo;
    }

    /**
     * Sets the services of a new mailbox: every service on except the disabled
     * mail services of the domain.
     *
     * @param string[] $disabled iRedMail service names
     */
    public function setNewMailboxServices(array $disabled): void
    {
        $this->setEnabledServiceNames(array_diff(array_keys(self::SERVICE_TOGGLES), $disabled));
    }

    /**
     * Turns the named services on and every other service off.
     *
     * @param string[] $names iRedMail service names, as validServiceNames() returns them
     */
    public function setEnabledServiceNames(array $names): void
    {
        foreach (self::SERVICE_TOGGLES as $service => $toggle) {
            $this->$toggle = in_array($service, $names, true);
        }
    }

    /**
     * Returns the iRedMail names of the enabled services.
     *
     * @return list<string>
     */
    public function enabledServiceNames(): array
    {
        return array_keys(array_filter(self::SERVICE_TOGGLES, fn (string $toggle): bool => $this->$toggle));
    }

    /**
     * Checks a list of iRedMail service names.
     *
     * @return list<string> the names without duplicates
     * @throws InvalidInputException naming the first unknown service
     */
    public static function validServiceNames(mixed $names): array
    {
        return NameList::valid($names, array_keys(self::SERVICE_TOGGLES), 'mail service', 'user.msg_unknown_service', 'service');
    }

    /**
     * Parameters for the SQL service columns that Dovecot checks besides the ones named after the toggles.
     * Dovecot reads `enable<service><secured|tls>`: a TLS login checks the `*tls` column, and
     * ManageSieve logs in as the service `sieve`, so a toggle must also set these columns to take effect.
     *
     * @return array<string, int>
     */
    public function dovecotServiceParams(): array
    {
        return [
            'enablePop3Tls' => (int) $this->enablePop3Secured,
            'enableImapTls' => (int) $this->enableImapSecured,
            'enableSieve' => (int) $this->enableManagesieve,
            'enableSieveSecured' => (int) $this->enableManagesieveSecured,
            'enableSieveTls' => (int) $this->enableManagesieveSecured,
        ];
    }

    /**
     * Parameters for every SQL service column that the panel writes. The column
     * name is the lowercased parameter name.
     *
     * @return array<string, int>
     */
    public function sqlServiceParams(): array
    {
        $params = [];
        foreach (self::SERVICE_TOGGLES as $toggle) {
            $params[$toggle] = (int) $this->$toggle;
        }

        return $params + $this->dovecotServiceParams();
    }

    /**
     * Returns true when any of the given SQL service columns is enabled, so a
     * toggle shows every login path that Dovecot still accepts. A missing column counts as enabled.
     */
    public static function anySqlServiceEnabled(array $row, string ...$columns): bool
    {
        foreach ($columns as $column) {
            if ((bool) ($row[$column] ?? 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates a User from a normalized LDAP entry array.
     * Converts LDAP quota (bytes) to megabytes at the model boundary.
     */
    public static function fromLdapEntry(array $entry): self
    {
        $quotaBytes = (int) ($entry['mailQuota'] ?? 0);
        $quotaMb = $quotaBytes > 0 ? (int) ($quotaBytes / 1048576) : 0;

        // Parse enabledService multi-valued attribute
        $services = [];
        if (isset($entry['enabledService'])) {
            $services = is_array($entry['enabledService']) ? $entry['enabledService'] : [$entry['enabledService']];
        }

        $user = new self(
            uid: $entry['uid'] ?? '',
            accountStatus: ($entry['accountStatus'] ?? '') === 'active',
            mailQuota: $quotaMb,
            cn: $entry['cn'] ?? '',
            givenName: $entry['givenName'] ?? '',
            sn: $entry['sn'] ?? '',
            employeeNumber: $entry['employeeNumber'] ?? '',
            title: $entry['title'] ?? '',
            department: $entry['departmentNumber'] ?? '',
            birthday: $entry['birthday'] ?? '',
            mobile: $entry['mobile'] ?? '',
            telephoneNumber: $entry['telephoneNumber'] ?? '',
            allowNets: $entry['allowNets'] ?? '',
            domainGlobalAdmin: ($entry['domainGlobalAdmin'] ?? '') === 'yes',
            language: $entry['preferredLanguage'] ?? null,
            passwordLastChange: self::passwordChangeFromShadow($entry['shadowLastChange'] ?? null),
        );
        $user->applyLdapServices($services);

        return $user;
    }

    /**
     * Validates a mailbox quota in MB. 0 means unlimited.
     *
     * @throws InvalidInputException when the value is not a whole number of 0 or more
     */
    public static function validMailQuota(mixed $value): int
    {
        return WholeNumber::parse($value)
            ?? throw new InvalidInputException('mailQuota must be a whole number of 0 or more', 'user.msg_invalid_quota');
    }

    /**
     * Creates a User from $_POST form data.
     *
     * @throws InvalidInputException when mailQuota is not a whole number of 0 or more, or a text field is not text
     */
    public static function fromFormData(array $post): self
    {
        return new self(
            uid: FormValue::text($post, 'uid'),
            // Use (bool) cast with null coalescing so JSON false is respected, not just key presence.
            accountStatus: (bool) ($post['accountStatus'] ?? false),
            mailQuota: self::validMailQuota($post['mailQuota'] ?? 100),
            cn: FormValue::text($post, 'cn'),
            givenName: FormValue::text($post, 'givenName'),
            sn: FormValue::text($post, 'sn'),
            employeeNumber: FormValue::text($post, 'employeeNumber'),
            title: FormValue::text($post, 'title'),
            department: FormValue::text($post, 'department'),
            birthday: self::validBirthday($post['birthday'] ?? ''),
            mobile: FormValue::text($post, 'mobile'),
            telephoneNumber: FormValue::text($post, 'telephoneNumber'),
            allowNets: self::validAllowNets($post['allowNets'] ?? ''),
            domainGlobalAdmin: (bool) ($post['domainGlobalAdmin'] ?? false),
            enableSmtp: (bool) ($post['enableSmtp'] ?? false),
            enableSmtpSecured: (bool) ($post['enableSmtpSecured'] ?? false),
            enablePop3: (bool) ($post['enablePop3'] ?? false),
            enablePop3Secured: (bool) ($post['enablePop3Secured'] ?? false),
            enableImap: (bool) ($post['enableImap'] ?? false),
            enableImapSecured: (bool) ($post['enableImapSecured'] ?? false),
            enableManagesieve: (bool) ($post['enableManagesieve'] ?? false),
            enableManagesieveSecured: (bool) ($post['enableManagesieveSecured'] ?? false),
            enableSogo: (bool) ($post['enableSogo'] ?? false),
            language: self::validLanguage($post['language'] ?? null),
        );
    }

    /**
     * Returns the enabledService values of this user: `mail`, the values of $current that no
     * toggle controls, and the values of every enabled toggle.
     *
     * @param string[] $current the stored values, so values the panel does not manage are kept
     * @return string[]
     */
    public function toLdapServiceList(array $current = self::LDAP_BASE_SERVICES): array
    {
        // Without `mail` Dovecot and Postfix ignore the mailbox; older panel versions omitted it.
        $services = array_diff(['mail', ...$current], array_merge(...array_values(self::LDAP_TOGGLE_SERVICES)));
        foreach (self::LDAP_TOGGLE_SERVICES as $toggle => $values) {
            if ($this->$toggle) {
                array_push($services, ...$values);
            }
        }

        return array_values(array_unique($services));
    }

    /**
     * The enabledService values of a new mailbox: every service on, as iRedMail creates it
     * and as the SQL column defaults do.
     *
     * @return string[]
     */
    public static function defaultLdapServices(): array
    {
        return (new self(''))->toLdapServiceList();
    }

    /**
     * Sets the toggles from enabledService values. A toggle is on when Dovecot or Postfix
     * still accepts any of its values; an entry without values has every service on.
     *
     * @param string[] $services
     */
    private function applyLdapServices(array $services): void
    {
        foreach (self::LDAP_TOGGLE_SERVICES as $toggle => $values) {
            $this->$toggle = $services === [] || array_intersect($values, $services) !== [];
        }
    }
}
