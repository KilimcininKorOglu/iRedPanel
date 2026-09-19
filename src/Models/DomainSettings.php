<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;
use App\Utils\FormValue;
use App\Utils\WholeNumber;

/**
 * Represents per-domain settings stored in the domain.settings column as key:value; pairs.
 * LdapAccountSetting maps them to the LDAP accountSetting attribute.
 */
class DomainSettings
{
    public function __construct(
        public int $defaultUserQuota = 0,
        public int $minPasswordLength = 0,
        public int $maxPasswordLength = 0,
        public string $disclaimer = '',
        public array $disabledMailServices = [],
        /** @var array<string, string> keys that the panel does not manage (iRedAdmin), kept on write */
        public array $otherKeys = [],
    ) {}

    /**
     * Takes the keys that the panel does not manage from a stored settings string,
     * so that a save from the settings form keeps them.
     */
    public function keepOtherKeysOf(string $stored): void
    {
        $this->otherKeys = self::fromSettingsString($stored)->otherKeys;
    }

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
                'disabled_mail_services' => $result->disabledMailServices = array_values(array_filter(explode(',', $value))),
                default => $result->otherKeys[$key] = $value,
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
        // The disclaimer lives in Domain::$disclaimer; a ';' in its text would split this format.
        if (!empty($this->disabledMailServices)) {
            $parts[] = "disabled_mail_services:" . implode(',', $this->disabledMailServices);
        }
        foreach ($this->otherKeys as $key => $value) {
            if ($key !== '' && $value !== '') {
                $parts[] = "{$key}:{$value}";
            }
        }

        return empty($parts) ? '' : implode(';', $parts) . ';';
    }

    /**
     * @throws InvalidInputException when a number is not a whole number of 0 or more,
     *         or the min password length exceeds the max password length
     */
    public static function fromFormData(array $post): self
    {
        $settings = new self(
            defaultUserQuota: self::number($post, 'defaultUserQuota', 'domain.default_user_quota'),
            minPasswordLength: self::number($post, 'minPasswordLength', 'domain.min_password_length'),
            maxPasswordLength: self::number($post, 'maxPasswordLength', 'domain.max_password_length'),
            disclaimer: FormValue::text($post, 'disclaimer'),
            disabledMailServices: $post['disabledMailServices'] ?? [],
        );

        // No password could satisfy both limits. A min of 0 falls back to the global min, like UserPassword
        // does, so the message shows the effective min.
        $minLength = $settings->minPasswordLength > 0
            ? $settings->minPasswordLength
            : Settings::getInstance()->passwordMinLength;
        if ($settings->maxPasswordLength > 0 && $minLength > $settings->maxPasswordLength) {
            throw new InvalidInputException(
                "The min password length ({$minLength}) must not exceed maxPasswordLength",
                'domain.msg_password_length_range',
                ['min' => $minLength, 'max' => $settings->maxPasswordLength],
            );
        }

        return $settings;
    }

    /**
     * A value of 0 means "use the global setting" or "unlimited", so an invalid value must not become 0.
     */
    private static function number(array $post, string $key, string $labelKey): int
    {
        return WholeNumber::parse($post[$key] ?? 0) ?? throw new InvalidInputException(
            "{$key} must be a whole number of 0 or more",
            'common.msg_invalid_whole_number',
            fieldKey: $labelKey,
        );
    }
}
