<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;
use App\Utils\FormValue;
use App\Utils\WholeNumber;

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

    /** Form label of each resource limit. */
    private const LIMIT_LABELS = [
        'createMaxDomains' => 'admin.max_domains',
        'createMaxUsers' => 'admin.max_users',
        'createMaxAliases' => 'admin.max_aliases',
        'createMaxLists' => 'admin.max_lists',
        'createMaxQuota' => 'admin.max_quota',
    ];

    /**
     * Validates a resource limit. -1 or an empty value means unlimited.
     *
     * @param string $field a key of LIMIT_LABELS
     * @throws InvalidInputException when the value is neither -1 nor a whole number of 0 or more
     */
    public static function validLimit(mixed $value, string $field): int
    {
        if ($value === -1 || $value === '' || $value === null || (is_string($value) && trim($value) === '-1')) {
            return -1;
        }

        return WholeNumber::parse($value) ?? throw new InvalidInputException(
            "{$field} must be -1 or a whole number of 0 or more",
            'admin.msg_invalid_limit',
            fieldKey: self::LIMIT_LABELS[$field],
        );
    }

    /**
     * Sets the resource limits from the limits form.
     *
     * @throws InvalidInputException when a limit is invalid; no limit changes then
     */
    public function applyLimits(array $post): void
    {
        $limits = [];
        foreach (array_keys(self::LIMIT_LABELS) as $field) {
            $limits[$field] = self::validLimit($post[$field] ?? -1, $field);
        }

        [$this->createMaxDomains, $this->createMaxUsers, $this->createMaxAliases, $this->createMaxLists, $this->createMaxQuota]
            = array_values($limits);
        $this->createNewDomains = isset($post['createNewDomains']);
    }

    public static function fromFormData(array $post): self
    {
        return new self(
            username: strtolower(FormValue::text($post, 'username')),
            name: FormValue::text($post, 'name'),
            active: (bool) ($post['active'] ?? false),
            isGlobalAdmin: (bool) ($post['isGlobalAdmin'] ?? false),
        );
    }

    public static function fromMysqlRow(array $row, bool $isMailboxAdmin = false): self
    {
        $admin = new self(
            username: $row['username'] ?? '',
            name: $row['name'] ?? '',
            active: (bool) ($row['active'] ?? 1),
            isGlobalAdmin: (bool) ($row['isGlobalAdmin'] ?? 0),
            isMailboxAdmin: $isMailboxAdmin,
            created: $row['created'] ?? null,
            passwordLastChange: $row['passwordlastchange'] ?? null,
        );
        $admin->applySettings(self::parseSettings($row['settings'] ?? ''));

        return $admin;
    }

    /**
     * @param array<string, string> $entry first values by attribute name; `accountSetting`
     *        holds all "key:value" values joined with ';'
     */
    public static function fromLdapEntry(array $entry, bool $isMailboxAdmin = false): self
    {
        $admin = new self(
            username: $entry['mail'] ?? '',
            name: $entry['cn'] ?? '',
            active: ($entry['accountStatus'] ?? 'active') === 'active',
            isGlobalAdmin: ($entry['domainGlobalAdmin'] ?? '') === 'yes',
            isMailboxAdmin: $isMailboxAdmin,
        );
        $admin->applySettings(self::parseSettings($entry['accountSetting'] ?? ''));

        return $admin;
    }

    /**
     * Returns the limits as LDAP accountSetting values in the iRedAdmin-Pro "key:value" form.
     *
     * @return string[]
     */
    public function toLdapAccountSetting(): array
    {
        $values = [];
        foreach (json_decode($this->toSettingsJson(), true) as $key => $value) {
            $values[] = $key . ':' . (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
        }

        return $values;
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

    /**
     * @param array<string, mixed> $settings
     */
    private function applySettings(array $settings): void
    {
        $this->createMaxDomains = (int) ($settings['create_max_domains'] ?? -1);
        $this->createMaxUsers = (int) ($settings['create_max_users'] ?? -1);
        $this->createMaxAliases = (int) ($settings['create_max_aliases'] ?? -1);
        $this->createMaxLists = (int) ($settings['create_max_lists'] ?? -1);
        $this->createMaxQuota = (int) ($settings['create_max_quota'] ?? -1);
        // The key:value form stores "yes"/"no", which a (bool) cast reads as true.
        $this->createNewDomains = filter_var($settings['create_new_domains'] ?? true, FILTER_VALIDATE_BOOLEAN);
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
