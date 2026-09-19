<?php

declare(strict_types=1);

namespace App\Models;

/**
 * A directory server whose accounts the panel replicates into one hosted domain.
 * The bind password is stored encrypted (App\Utils\SecretBox).
 */
class AccountResource
{
    public const TYPES = ['ad', 'samba'];

    public const DEFAULT_USER_FILTER = '(|(objectClass=user)(objectClass=person))';
    public const DEFAULT_GROUP_FILTER = '(objectClass=group)';

    /** User property => directory attribute. An empty attribute is not replicated. */
    public const DEFAULT_USER_ATTRIBUTES = [
        'accountStatus' => 'userAccountControl',
        'cn' => 'displayName',
        'givenName' => 'givenName',
        'sn' => 'sn',
        'employeeNumber' => 'employeeID',
        'title' => 'title',
        'mobile' => 'mobile',
        'telephoneNumber' => 'telephoneNumber',
    ];

    public const STATUS_NEVER = '';
    public const STATUS_OK = 'ok';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';

    /**
     * @param array<string, string> $userAttributes User property => directory attribute
     */
    public function __construct(
        public int $id = 0,
        public string $type = 'ad',
        public string $domain = '',
        public string $host = '',
        public int $port = 636,
        public bool $tls = true,
        public bool $tlsVerify = true,
        public int $timeout = 5,
        public string $baseDn = '',
        public string $bindDn = '',
        public string $bindPassword = '',
        public string $userFilter = self::DEFAULT_USER_FILTER,
        public string $groupFilter = self::DEFAULT_GROUP_FILTER,
        public int $intervalMinutes = 5,
        public bool $replicateGroups = false,
        public string $userMailAttribute = 'userPrincipalName',
        public array $userAttributes = self::DEFAULT_USER_ATTRIBUTES,
        public string $groupMailAttribute = 'mail',
        public string $groupNameAttribute = 'cn',
        public string $groupAccessPolicy = 'domain',
        public bool $enabled = true,
        public ?int $lastRunAt = null,
        public string $lastStatus = self::STATUS_NEVER,
    ) {}

    /**
     * True when the resource is enabled and its interval has passed since the last run.
     */
    public function isDue(int $now): bool
    {
        return $this->enabled
            && ($this->lastRunAt === null || $now - $this->lastRunAt >= $this->intervalMinutes * 60);
    }

    /**
     * The directory attributes that a replication run reads for a user entry.
     *
     * @return string[]
     */
    public function userSearchAttributes(): array
    {
        $attributes = ['objectGUID', $this->userMailAttribute, ...array_values($this->userAttributes)];

        return array_values(array_unique(array_filter($attributes, static fn(string $a): bool => $a !== '')));
    }

    /**
     * @return string[]
     */
    public function groupSearchAttributes(): array
    {
        $attributes = ['objectGUID', 'member', $this->groupMailAttribute, $this->groupNameAttribute];

        return array_values(array_unique(array_filter($attributes, static fn(string $a): bool => $a !== '')));
    }

    public static function fromRow(array $row): self
    {
        $attributes = json_decode((string) ($row['user_attributes'] ?? ''), true);

        return new self(
            id: (int) $row['id'],
            type: (string) $row['type'],
            domain: (string) $row['domain'],
            host: (string) $row['host'],
            port: (int) $row['port'],
            tls: (bool) $row['tls'],
            tlsVerify: (bool) $row['tls_verify'],
            timeout: (int) $row['timeout'],
            baseDn: (string) $row['base_dn'],
            bindDn: (string) $row['bind_dn'],
            bindPassword: (string) $row['bind_password'],
            userFilter: (string) $row['user_filter'],
            groupFilter: (string) $row['group_filter'],
            intervalMinutes: (int) $row['interval_minutes'],
            replicateGroups: (bool) $row['replicate_groups'],
            userMailAttribute: (string) $row['user_mail_attribute'],
            userAttributes: is_array($attributes) ? $attributes + array_fill_keys(array_keys(self::DEFAULT_USER_ATTRIBUTES), '') : self::DEFAULT_USER_ATTRIBUTES,
            groupMailAttribute: (string) $row['group_mail_attribute'],
            groupNameAttribute: (string) $row['group_name_attribute'],
            groupAccessPolicy: (string) $row['group_access_policy'],
            enabled: (bool) $row['enabled'],
            lastRunAt: $row['last_run_at'] === null ? null : (int) $row['last_run_at'],
            lastStatus: (string) $row['last_status'],
        );
    }

    /**
     * The stored columns, without id, last_run_at and last_status (a run sets those).
     *
     * @return array<string, int|string>
     */
    public function toRow(): array
    {
        return [
            'type' => $this->type,
            'domain' => $this->domain,
            'host' => $this->host,
            'port' => $this->port,
            'tls' => (int) $this->tls,
            'tls_verify' => (int) $this->tlsVerify,
            'timeout' => $this->timeout,
            'base_dn' => $this->baseDn,
            'bind_dn' => $this->bindDn,
            'bind_password' => $this->bindPassword,
            'user_filter' => $this->userFilter,
            'group_filter' => $this->groupFilter,
            'interval_minutes' => $this->intervalMinutes,
            'replicate_groups' => (int) $this->replicateGroups,
            'user_mail_attribute' => $this->userMailAttribute,
            'user_attributes' => json_encode($this->userAttributes, JSON_THROW_ON_ERROR),
            'group_mail_attribute' => $this->groupMailAttribute,
            'group_name_attribute' => $this->groupNameAttribute,
            'group_access_policy' => $this->groupAccessPolicy,
            'enabled' => (int) $this->enabled,
        ];
    }
}
