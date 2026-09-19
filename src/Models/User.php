<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;
use App\Utils\FormValue;
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
     * iRedMail service name (as in the disabled_mail_services domain setting and
     * the iRedAdmin-Pro API) => toggle property.
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
        public string $mobile = '',
        public string $telephoneNumber = '',
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
    ) {}

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
     * mail services of the domain, as iRedAdmin creates a mailbox.
     *
     * @param string[] $disabled iRedMail service names
     */
    public function setNewMailboxServices(array $disabled): void
    {
        foreach (self::SERVICE_TOGGLES as $service => $toggle) {
            $this->$toggle = !in_array($service, $disabled, true);
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
        if (!is_array($names)) {
            throw new InvalidInputException('Mail services must be a list', 'user.msg_unknown_service', ['service' => gettype($names)]);
        }
        foreach ($names as $name) {
            if (!is_string($name) || !isset(self::SERVICE_TOGGLES[$name])) {
                $shown = is_string($name) ? $name : gettype($name);
                throw new InvalidInputException("Unknown mail service: {$shown}", 'user.msg_unknown_service', ['service' => $shown]);
            }
        }

        return array_values(array_unique($names));
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
            mobile: $entry['mobile'] ?? '',
            telephoneNumber: $entry['telephoneNumber'] ?? '',
            domainGlobalAdmin: ($entry['domainGlobalAdmin'] ?? '') === 'yes',
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
            mobile: FormValue::text($post, 'mobile'),
            telephoneNumber: FormValue::text($post, 'telephoneNumber'),
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
