<?php

declare(strict_types=1);

namespace App\Models;

use App\Utils\FormValue;

class Admin
{
    public function __construct(
        public string $username,
        public string $name = '',
        public bool $active = true,
        public bool $isGlobalAdmin = false,
        public bool $isMailboxAdmin = false,
        public ?string $created = null,
        public ?string $passwordLastChange = null,
        public int $createMaxDomains = -1,
        public int $createMaxUsers = -1,
        public int $createMaxAliases = -1,
        public int $createMaxLists = -1,
        public int $createMaxQuota = -1,
        public bool $createNewDomains = true,
    ) {}

    public static function fromFormData(array $post): self
    {
        return new self(
            username: strtolower(FormValue::text($post, 'username')),
            name: FormValue::text($post, 'name'),
            active: isset($post['active']),
            isGlobalAdmin: isset($post['isGlobalAdmin']),
        );
    }

    public static function fromMysqlRow(array $row, bool $isMailboxAdmin = false): self
    {
        $settings = self::parseSettings($row['settings'] ?? '');

        return new self(
            username: $row['username'] ?? '',
            name: $row['name'] ?? '',
            active: (bool) ($row['active'] ?? 1),
            isGlobalAdmin: (bool) ($row['isGlobalAdmin'] ?? 0),
            isMailboxAdmin: $isMailboxAdmin,
            created: $row['created'] ?? null,
            passwordLastChange: $row['passwordlastchange'] ?? null,
            createMaxDomains: (int) ($settings['create_max_domains'] ?? -1),
            createMaxUsers: (int) ($settings['create_max_users'] ?? -1),
            createMaxAliases: (int) ($settings['create_max_aliases'] ?? -1),
            createMaxLists: (int) ($settings['create_max_lists'] ?? -1),
            createMaxQuota: (int) ($settings['create_max_quota'] ?? -1),
            createNewDomains: (bool) ($settings['create_new_domains'] ?? true),
        );
    }

    public static function fromLdapEntry(array $entry, bool $isMailboxAdmin = false): self
    {
        return new self(
            username: $entry['mail'] ?? '',
            name: $entry['cn'] ?? '',
            active: ($entry['accountStatus'] ?? 'active') === 'active',
            isGlobalAdmin: ($entry['domainGlobalAdmin'] ?? '') === 'yes',
            isMailboxAdmin: $isMailboxAdmin,
        );
    }

    public function toSettingsJson(): string
    {
        return json_encode([
            'create_max_domains' => $this->createMaxDomains,
            'create_max_users' => $this->createMaxUsers,
            'create_max_aliases' => $this->createMaxAliases,
            'create_max_lists' => $this->createMaxLists,
            'create_max_quota' => $this->createMaxQuota,
            'create_new_domains' => $this->createNewDomains,
        ]);
    }

    private static function parseSettings(string $settings): array
    {
        if ($settings === '') {
            return [];
        }

        $decoded = json_decode($settings, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // iRedAdmin-Pro uses key:value;key:value format
        $result = [];
        foreach (explode(';', $settings) as $pair) {
            $pair = trim($pair);
            if (str_contains($pair, ':')) {
                [$key, $value] = explode(':', $pair, 2);
                $result[trim($key)] = trim($value);
            }
        }
        return $result;
    }
}
