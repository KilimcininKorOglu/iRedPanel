<?php

declare(strict_types=1);

namespace App\Models;

class ApiKey
{
    public function __construct(
        public int $id = 0,
        public string $apiKey = '',
        public string $label = '',
        public string $role = 'global',
        public string $domains = '',
        public bool $readOnly = false,
        public bool $active = true,
        public ?string $createdAt = null,
    ) {}

    /**
     * Returns true if this key has global admin access.
     */
    public function isGlobal(): bool
    {
        return $this->role === 'global';
    }

    /**
     * Returns true if this key has access to the given domain.
     */
    public function hasDomainAccess(string $domain): bool
    {
        if ($this->isGlobal()) {
            return true;
        }

        return in_array($domain, $this->domainList(), true);
    }

    /**
     * @return list<string> the domains of a domain key
     */
    public function domainList(): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', $this->domains))));
    }

    /**
     * Returns true if this key allows write operations.
     */
    public function canWrite(): bool
    {
        return !$this->readOnly;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            apiKey: $row['api_key'] ?? '',
            label: $row['label'] ?? '',
            role: $row['role'] ?? 'global',
            domains: $row['domains'] ?? '',
            readOnly: (bool) ($row['read_only'] ?? 0),
            active: (bool) ($row['active'] ?? 1),
            createdAt: $row['created_at'] ?? null,
        );
    }
}
