<?php

declare(strict_types=1);

namespace App\Models;

/**
 * User data model. Maps to iRedMail user attributes.
 * mailQuota is always stored in megabytes regardless of backend.
 */
class User
{
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

        return new self(
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
            enableSmtp: in_array('smtp', $services, true) || empty($services),
            enableSmtpSecured: in_array('smtpsecured', $services, true) || empty($services),
            enablePop3: in_array('pop3', $services, true) || empty($services),
            enablePop3Secured: in_array('pop3secured', $services, true) || empty($services),
            enableImap: in_array('imap', $services, true) || empty($services),
            enableImapSecured: in_array('imapsecured', $services, true) || empty($services),
            enableManagesieve: in_array('managesieve', $services, true) || empty($services),
            enableManagesieveSecured: in_array('managesievesecured', $services, true) || empty($services),
            enableSogo: in_array('sogo', $services, true) || empty($services),
        );
    }

    /**
     * Creates a User from $_POST form data.
     */
    public static function fromFormData(array $post): self
    {
        return new self(
            uid: trim($post['uid'] ?? ''),
            // Use (bool) cast with null coalescing so JSON false is respected, not just key presence.
            accountStatus: (bool) ($post['accountStatus'] ?? false),
            mailQuota: max(0, (int) ($post['mailQuota'] ?? 100)),
            cn: trim($post['cn'] ?? ''),
            givenName: trim($post['givenName'] ?? ''),
            sn: trim($post['sn'] ?? ''),
            employeeNumber: trim($post['employeeNumber'] ?? ''),
            title: trim($post['title'] ?? ''),
            mobile: trim($post['mobile'] ?? ''),
            telephoneNumber: trim($post['telephoneNumber'] ?? ''),
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
     * Returns the list of enabled LDAP service names for this user.
     *
     * @return string[]
     */
    public function toLdapServiceList(): array
    {
        $services = ['deliver', 'lda', 'lmtp', 'forward', 'senderbcc', 'recipientbcc',
                     'internal', 'doveadm', 'lib-storage', 'indexer-worker', 'dsync',
                     'sieve', 'sievesecured', 'displayedInGlobalAddressBook'];

        if ($this->enableSmtp) $services[] = 'smtp';
        if ($this->enableSmtpSecured) $services[] = 'smtpsecured';
        if ($this->enablePop3) $services[] = 'pop3';
        if ($this->enablePop3Secured) $services[] = 'pop3secured';
        if ($this->enableImap) $services[] = 'imap';
        if ($this->enableImapSecured) $services[] = 'imapsecured';
        if ($this->enableManagesieve) $services[] = 'managesieve';
        if ($this->enableManagesieveSecured) $services[] = 'managesievesecured';
        if ($this->enableSogo) $services[] = 'sogo';

        return $services;
    }
}
