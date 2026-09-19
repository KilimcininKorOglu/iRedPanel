<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;
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
    ) {}

    /** A lowercase host name with at least one dot and an alphabetic top-level label. */
    private const NAME_PATTERN = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/';

    /**
     * Checks a lowercased domain or alias domain name.
     */
    public static function isValidName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1;
    }

    /** Form label of each quota and count limit. */
    private const LIMIT_LABELS = [
        'maxQuota' => 'domain.max_quota',
        'quota' => 'domain.domain_quota',
        'mailboxes' => 'domain.max_mailboxes',
        'aliases' => 'domain.max_aliases',
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
     * @throws InvalidInputException when a limit is not a whole number of 0 or more
     */
    public static function fromFormData(array $post): self
    {
        return new self(
            domainName: strtolower(trim($post['domainName'] ?? '')),
            description: trim($post['description'] ?? ''),
            active: (bool) ($post['active'] ?? false),
            maxQuota: self::validLimit($post['maxQuota'] ?? 0, 'maxQuota'),
            quota: self::validLimit($post['quota'] ?? 0, 'quota'),
            mailboxes: self::validLimit($post['mailboxes'] ?? 0, 'mailboxes'),
            aliases: self::validLimit($post['aliases'] ?? 0, 'aliases'),
            transport: trim($post['transport'] ?? 'dovecot'),
            settings: $post['settings'] ?? '',
        );
    }

    public static function fromMysqlRow(array $row): self
    {
        return new self(
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
        );
    }

    public static function fromLdapEntry(array $entry): self
    {
        return new self(
            domainName: $entry['domainName'] ?? '',
            description: $entry['cn'] ?? $entry['description'] ?? '',
            active: ($entry['accountStatus'] ?? 'active') === 'active',
            currentUserCount: (int) ($entry['domainCurrentUserNumber'] ?? 0),
        );
    }
}
