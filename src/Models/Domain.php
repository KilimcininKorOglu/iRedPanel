<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;
use App\Utils\ExpiryDate;
use App\Utils\FormValue;
use App\Utils\Relayhost;
use App\Utils\WholeNumber;

class Domain
{
    public function __construct(
        public string $domainName,
        public string $description = '',
        public bool $active = true,
        public int $maxQuota = 0,
        public int $quota = 0,
        public int $mailboxes = 0,
        public int $aliases = 0,
        public string $transport = 'dovecot',
        public string $settings = '',
        public ?string $created = null,
        public ?string $modified = null,
        public int $currentUserCount = 0,
        public int $currentQuotaUsed = 0,
        // iRedMail's own column (SQL `domain.disclaimer`, LDAP `disclaimer`), read by dump_disclaimer.py.
        public string $disclaimer = '',
        // Max mailing lists (SQL `domain.maillists`, LDAP accountSetting `numberOfLists`); 0 means unlimited.
        public int $lists = 0,
        // Backup MX (SQL `domain.backupmx`, LDAP `domainBackupMX`): Postfix relays the mail of the
        // domain through the transport "relay:<primary MX>" instead of delivering it locally.
        public bool $backupMx = false,
        // The primary MX of a backup MX domain as the form or the API sent it; not stored as such.
        public string $primaryMx = '',
        /** Last valid day of the domain as YYYY-MM-DD; '' means that it never expires. */
        public string $expiredDate = '',
    ) {}

    /** iRedMail's transport for local delivery. */
    public const DEFAULT_TRANSPORT = 'dovecot';

    /**
     * Copies the fields of the profile form (general tab) from $form.
     * The settings string and the disclaimer belong to the settings tab and stay unchanged.
     */
    public function applyProfile(self $form): void
    {
        $this->description = $form->description;
        $this->active = $form->active;
        $this->maxQuota = $form->maxQuota;
        $this->quota = $form->quota;
        $this->mailboxes = $form->mailboxes;
        $this->aliases = $form->aliases;
        $this->lists = $form->lists;
        $this->expiredDate = $form->expiredDate;
        $this->applyTransport($form);
    }

    /**
     * A backup MX domain relays to its primary MX; without a primary MX, Postfix looks up
     * the MX records of the domain. Turning backup MX off restores local delivery when the
     * form still carries the relay transport.
     */
    private function applyTransport(self $form): void
    {
        if ($form->backupMx) {
            $this->transport = 'relay:' . ($form->primaryMx !== '' ? $form->primaryMx : $this->domainName);
        } elseif ($this->backupMx && str_starts_with($form->transport, 'relay:')) {
            $this->transport = self::DEFAULT_TRANSPORT;
        } else {
            $this->transport = $form->transport;
        }
        $this->backupMx = $form->backupMx;
        $this->primaryMx = $this->storedPrimaryMx();
    }

    /**
     * Returns the primary MX of a backup MX domain, or '' when Postfix looks up the MX
     * records of the domain.
     */
    public function storedPrimaryMx(): string
    {
        if (!$this->backupMx || !str_starts_with($this->transport, 'relay:')) {
            return '';
        }
        $nextHop = substr($this->transport, strlen('relay:'));

        return $nextHop === $this->domainName ? '' : $nextHop;
    }

    /**
     * Checks the mailing list limit of this domain for one more list.
     *
     * @param int $currentLists the mailing lists that the domain has
     */
    public function newListError(int $currentLists): ?InvalidInputException
    {
        if ($this->lists > 0 && $currentLists >= $this->lists) {
            return new InvalidInputException(
                "Domain mailing list limit reached ({$currentLists}/{$this->lists})",
                'mlist.msg_list_limit',
                ['current' => $currentLists, 'max' => $this->lists],
            );
        }

        return null;
    }

    /**
     * Returns the stored disclaimer. Older panel versions kept it in the settings
     * string, which is read until the next settings save moves it to the column.
     */
    private static function storedDisclaimer(?string $column, string $settings): string
    {
        if ($column !== null && $column !== '') {
            return $column;
        }

        return DomainSettings::fromSettingsString($settings)->disclaimer;
    }

    /** A lowercase host name with at least one dot and an alphabetic top-level label. */
    private const NAME_PATTERN = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/';

    /**
     * Checks a lowercased domain or alias domain name.
     */
    public static function isValidName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1;
    }

    /**
     * Checks the mailbox count and quota limits of this domain for one more mailbox.
     *
     * @param int $mailQuota the quota of the new mailbox in MB
     * @return ?InvalidInputException the violated limit, or null when the mailbox fits
     */
    public function newMailboxError(int $mailQuota): ?InvalidInputException
    {
        if ($this->mailboxes > 0 && $this->currentUserCount >= $this->mailboxes) {
            return new InvalidInputException(
                "Domain mailbox limit reached ({$this->currentUserCount}/{$this->mailboxes})",
                'user.msg_mailbox_limit',
                ['current' => $this->currentUserCount, 'max' => $this->mailboxes],
            );
        }
        if ($this->maxQuota > 0 && $mailQuota > $this->maxQuota) {
            return new InvalidInputException(
                "User quota exceeds domain maximum ({$this->maxQuota} MB)",
                'user.msg_quota_exceeds_max',
                ['quota' => $mailQuota, 'max' => $this->maxQuota],
            );
        }
        if ($this->quota > 0 && ($this->currentQuotaUsed + $mailQuota) > $this->quota) {
            return new InvalidInputException(
                'Total domain quota would be exceeded',
                'user.msg_total_quota_exceeded_detail',
                ['used' => $this->currentQuotaUsed, 'quota' => $mailQuota, 'max' => $this->quota],
            );
        }

        return null;
    }

    /**
     * Checks the quota limits of this domain when a mailbox quota changes.
     *
     * @param int $oldQuota the stored quota of the mailbox in MB
     * @param int $newQuota the requested quota in MB
     * @return ?InvalidInputException the violated limit, or null when the quota fits
     */
    public function quotaChangeError(int $oldQuota, int $newQuota): ?InvalidInputException
    {
        if ($this->maxQuota > 0 && $newQuota > $this->maxQuota) {
            return new InvalidInputException(
                "User quota exceeds domain maximum ({$this->maxQuota} MB)",
                'user.msg_quota_exceeds_max',
                ['quota' => $newQuota, 'max' => $this->maxQuota],
            );
        }
        $growth = $newQuota - $oldQuota;
        if ($this->quota > 0 && $growth > 0 && ($this->currentQuotaUsed + $growth) > $this->quota) {
            return new InvalidInputException('Total domain quota would be exceeded', 'user.msg_total_quota_exceeded');
        }

        return null;
    }

    /** Form label of each quota and count limit. */
    private const LIMIT_LABELS = [
        'maxQuota' => 'domain.max_quota',
        'quota' => 'domain.domain_quota',
        'mailboxes' => 'domain.max_mailboxes',
        'aliases' => 'domain.max_aliases',
        'lists' => 'domain.max_lists',
    ];

    /**
     * Validates a quota or count limit. 0 means unlimited.
     *
     * @param string $field a key of LIMIT_LABELS
     * @throws InvalidInputException when the value is not a whole number of 0 or more
     */
    public static function validLimit(mixed $value, string $field): int
    {
        return WholeNumber::parse($value) ?? throw new InvalidInputException(
            "{$field} must be a whole number of 0 or more",
            'common.msg_invalid_whole_number',
            fieldKey: self::LIMIT_LABELS[$field],
        );
    }

    /**
     * @throws InvalidInputException when a limit is not a whole number of 0 or more, or a text field is not text
     */
    public static function fromFormData(array $post): self
    {
        $domain = new self(
            domainName: strtolower(FormValue::text($post, 'domainName')),
            description: FormValue::text($post, 'description'),
            active: (bool) ($post['active'] ?? false),
            maxQuota: self::validLimit($post['maxQuota'] ?? 0, 'maxQuota'),
            quota: self::validLimit($post['quota'] ?? 0, 'quota'),
            mailboxes: self::validLimit($post['mailboxes'] ?? 0, 'mailboxes'),
            aliases: self::validLimit($post['aliases'] ?? 0, 'aliases'),
            transport: FormValue::text($post, 'transport', self::DEFAULT_TRANSPORT),
            settings: FormValue::text($post, 'settings'),
            lists: self::validLimit($post['lists'] ?? 0, 'lists'),
            backupMx: (bool) ($post['backupMx'] ?? false),
            primaryMx: self::validPrimaryMx(FormValue::text($post, 'primaryMx')),
            expiredDate: ExpiryDate::valid($post['expiredDate'] ?? ''),
        );
        // A new domain gets the relay transport here; applyProfile() sets it on an update.
        if ($domain->backupMx && $domain->domainName !== '') {
            $domain->applyTransport(clone $domain);
            $domain->primaryMx = $domain->storedPrimaryMx();
        }

        return $domain;
    }

    /**
     * @throws InvalidInputException when Postfix cannot use the value as a next hop
     */
    private static function validPrimaryMx(string $value): string
    {
        $value = trim($value);
        if ($value !== '' && !Relayhost::isValid($value)) {
            throw new InvalidInputException(
                "Invalid primaryMx: {$value}",
                'common.msg_invalid_relayhost',
                ['relayhost' => $value],
            );
        }

        return $value;
    }

    public static function fromMysqlRow(array $row): self
    {
        $domain = new self(
            domainName: $row['domain'] ?? '',
            description: $row['description'] ?? '',
            active: (bool) ($row['active'] ?? 1),
            maxQuota: (int) ($row['maxquota'] ?? 0),
            quota: (int) ($row['quota'] ?? 0),
            mailboxes: (int) ($row['mailboxes'] ?? 0),
            aliases: (int) ($row['aliases'] ?? 0),
            transport: $row['transport'] ?? 'dovecot',
            settings: $row['settings'] ?? '',
            created: $row['created'] ?? null,
            modified: $row['modified'] ?? null,
            currentUserCount: (int) ($row['userCount'] ?? 0),
            currentQuotaUsed: (int) ($row['quotaUsed'] ?? 0),
            disclaimer: self::storedDisclaimer($row['disclaimer'] ?? null, $row['settings'] ?? ''),
            lists: (int) ($row['maillists'] ?? 0),
            backupMx: (bool) ($row['backupmx'] ?? 0),
            expiredDate: ExpiryDate::fromSql($row['expired'] ?? null),
        );
        $domain->primaryMx = $domain->storedPrimaryMx();

        return $domain;
    }

    public static function fromLdapEntry(array $entry): self
    {
        $domain = new self(
            domainName: $entry['domainName'] ?? '',
            description: $entry['cn'] ?? $entry['description'] ?? '',
            active: ($entry['accountStatus'] ?? 'active') === 'active',
            transport: $entry['mtaTransport'] ?? self::DEFAULT_TRANSPORT,
            currentUserCount: (int) ($entry['domainCurrentUserNumber'] ?? 0),
            disclaimer: $entry['disclaimer'] ?? '',
            backupMx: strtolower($entry['domainBackupMX'] ?? '') === 'yes',
            expiredDate: ExpiryDate::fromLdap($entry['expiredDate'] ?? null),
        );
        $domain->primaryMx = $domain->storedPrimaryMx();

        return $domain;
    }
}
