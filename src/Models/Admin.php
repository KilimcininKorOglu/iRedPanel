<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;
use App\Utils\FormValue;
use App\Utils\SettingsString;
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
        public bool $createNewDomains = false,
        /** Preferred UI language (xx_YY); '' uses the default language. */
        public string $language = '',
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

    /**
     * Sets the limits that a JSON body contains; the other limits keep their value.
     *
     * @return bool whether the body contains a limit
     * @throws InvalidInputException when a limit is invalid; no limit changes then
     */
    public function applyLimitsFromJson(array $data): bool
    {
        $fields = [...array_keys(self::LIMIT_LABELS), 'createNewDomains'];
        if (array_intersect($fields, array_keys($data)) === []) {
            return false;
        }

        $post = [];
        foreach (array_keys(self::LIMIT_LABELS) as $field) {
            $post[$field] = $data[$field] ?? $this->{$field};
        }
        if ((bool) ($data['createNewDomains'] ?? $this->createNewDomains)) {
            $post['createNewDomains'] = 'on';
        }
        $this->applyLimits($post);

        return true;
    }

    public static function fromFormData(array $post): self
    {
        return new self(
            username: strtolower(FormValue::text($post, 'username')),
            name: FormValue::text($post, 'name'),
            active: (bool) ($post['active'] ?? false),
            isGlobalAdmin: (bool) ($post['isGlobalAdmin'] ?? false),
            language: (string) User::validLanguage(FormValue::text($post, 'language')),
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
            language: (string) ($row['language'] ?? ''),
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
            language: $entry['preferredLanguage'] ?? '',
        );
        $admin->applySettings(self::parseSettings($entry['accountSetting'] ?? ''));

        return $admin;
    }

    /** Keys of the admin settings that the panel writes; every other stored key stays. */
    public const SETTING_KEYS = [
        'create_max_domains', 'create_max_users', 'create_max_aliases', 'create_max_lists', 'create_max_quota',
        'create_new_domains',
    ];

    /**
     * Returns the settings in the iRedAdmin form. An unlimited (-1) limit is not written,
     * because iRedAdmin reads -1 as "not allowed". iRedAdmin reads create_new_domains
     * from the presence of the key, so it is written only when domain creation is allowed.
     *
     * @return array<string, string>
     */
    public function settingValues(): array
    {
        $values = [];
        foreach (array_keys(self::LIMIT_LABELS) as $field) {
            if ($this->{$field} >= 0) {
                $values[self::settingKey($field)] = (string) $this->{$field};
            }
        }
        if ($this->createNewDomains) {
            $values['create_new_domains'] = 'yes';
        }

        return $values;
    }

    /**
     * Returns the settings as LDAP accountSetting values in the iRedAdmin "key:value" form.
     *
     * @return string[]
     */
    public function toLdapAccountSetting(): array
    {
        $values = [];
        foreach ($this->settingValues() as $key => $value) {
            $values[] = "{$key}:{$value}";
        }

        return $values;
    }

    /**
     * Returns the settings column of the admin with the panel keys written over the stored
     * value. A JSON value that an older panel version wrote becomes the iRedAdmin form.
     */
    public function mergedSettings(string $stored): string
    {
        return SettingsString::merge(self::parseSettings($stored), $this->settingValues(), self::SETTING_KEYS);
    }

    /** createMaxDomains => create_max_domains */
    private static function settingKey(string $field): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', $field));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function applySettings(array $settings): void
    {
        foreach (array_keys(self::LIMIT_LABELS) as $field) {
            $this->{$field} = (int) ($settings[self::settingKey($field)] ?? -1);
        }
        // iRedAdmin allows domain creation when the key is present; an older panel version
        // also wrote "no" or false, which filter_var() reads as false.
        $this->createNewDomains = filter_var($settings['create_new_domains'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseSettings(string $settings): array
    {
        // An older panel version stored JSON in the SQL settings column.
        $decoded = json_decode($settings, true);

        return is_array($decoded) ? $decoded : SettingsString::parse($settings);
    }
}
