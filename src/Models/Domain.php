<?php

declare(strict_types=1);

namespace App\Models;

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

    public static function fromFormData(array $post): self
    {
        return new self(
            domainName: strtolower(trim($post['domainName'] ?? '')),
            description: trim($post['description'] ?? ''),
            active: (bool) ($post['active'] ?? false),
            maxQuota: max(0, (int) ($post['maxQuota'] ?? 0)),
            quota: max(0, (int) ($post['quota'] ?? 0)),
            mailboxes: max(0, (int) ($post['mailboxes'] ?? 0)),
            aliases: max(0, (int) ($post['aliases'] ?? 0)),
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
