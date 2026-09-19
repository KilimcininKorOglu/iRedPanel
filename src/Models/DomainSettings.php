<?php

declare(strict_types=1);

namespace App\Models;

use App\Utils\FormValue;

/**
 * Represents per-domain settings stored in domain.settings column (MySQL)
 * or accountSetting attribute (LDAP) as key:value; pairs.
 */
class DomainSettings
{
    public function __construct(
        public int $defaultUserQuota = 0,
        public int $minPasswordLength = 0,
        public int $maxPasswordLength = 0,
        public string $disclaimer = '',
        public array $disabledMailServices = [],
    ) {}

    /**
     * Parse from iRedMail's "key:value;key:value;" format (MySQL domain.settings column).
     */
    public static function fromSettingsString(string $settings): self
    {
        $result = new self();

        if (empty($settings)) {
            return $result;
        }

        $pairs = array_filter(explode(';', $settings));
        foreach ($pairs as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$key, $value] = $parts;
            $key = trim($key);
            $value = trim($value);

            match ($key) {
                'default_user_quota' => $result->defaultUserQuota = (int) $value,
                'min_passwd_length' => $result->minPasswordLength = (int) $value,
                'max_passwd_length' => $result->maxPasswordLength = (int) $value,
                'disclaimer' => $result->disclaimer = $value,
                'disabled_mail_services' => $result->disabledMailServices = array_filter(explode(',', $value)),
                default => null,
            };
        }

        return $result;
    }

    /**
     * Serialize to iRedMail's "key:value;key:value;" format.
     */
    public function toSettingsString(): string
    {
        $parts = [];

        if ($this->defaultUserQuota > 0) {
            $parts[] = "default_user_quota:{$this->defaultUserQuota}";
        }
        if ($this->minPasswordLength > 0) {
            $parts[] = "min_passwd_length:{$this->minPasswordLength}";
        }
        if ($this->maxPasswordLength > 0) {
            $parts[] = "max_passwd_length:{$this->maxPasswordLength}";
        }
        if ($this->disclaimer !== '') {
            $parts[] = "disclaimer:{$this->disclaimer}";
        }
        if (!empty($this->disabledMailServices)) {
            $parts[] = "disabled_mail_services:" . implode(',', $this->disabledMailServices);
        }

        return empty($parts) ? '' : implode(';', $parts) . ';';
    }

    public static function fromFormData(array $post): self
    {
        return new self(
            defaultUserQuota: (int) ($post['defaultUserQuota'] ?? 0),
            minPasswordLength: (int) ($post['minPasswordLength'] ?? 0),
            maxPasswordLength: (int) ($post['maxPasswordLength'] ?? 0),
            disclaimer: FormValue::text($post, 'disclaimer'),
            disabledMailServices: $post['disabledMailServices'] ?? [],
        );
    }

    /**
     * Parse from LDAP multi-valued accountSetting attribute.
     * Each value is a "key:value" string.
     *
     * @param string[] $values
     */
    public static function fromLdapAccountSetting(array $values): self
    {
        return self::fromSettingsString(implode(';', $values) . ';');
    }

    /**
     * Serialize to LDAP multi-valued accountSetting attribute.
     *
     * @return string[]
     */
    public function toLdapAccountSetting(): array
    {
        $parts = [];

        if ($this->defaultUserQuota > 0) {
            $parts[] = "default_user_quota:{$this->defaultUserQuota}";
        }
        if ($this->minPasswordLength > 0) {
            $parts[] = "min_passwd_length:{$this->minPasswordLength}";
        }
        if ($this->maxPasswordLength > 0) {
            $parts[] = "max_passwd_length:{$this->maxPasswordLength}";
        }
        if ($this->disclaimer !== '') {
            $parts[] = "disclaimer:{$this->disclaimer}";
        }
        if (!empty($this->disabledMailServices)) {
            $parts[] = "disabled_mail_services:" . implode(',', $this->disabledMailServices);
        }

        return $parts;
    }
}
