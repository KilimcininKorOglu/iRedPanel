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
    /** settings key => list property; the items are stored comma-separated. */
    private const LIST_KEYS = [
        'disabled_mail_services' => 'disabledMailServices',
        'disabled_domain_profiles' => 'disabledDomainProfiles',
        'disabled_user_profiles' => 'disabledUserProfiles',
        'disabled_user_preferences' => 'disabledUserPreferences',
        'enabled_services' => 'enabledServices',
    ];

    public function __construct(
        public int $defaultUserQuota = 0,
        public int $minPasswordLength = 0,
        public int $maxPasswordLength = 0,
        public string $disclaimer = '',
        public array $disabledMailServices = [],
        /** @var array<string, string> keys that the panel does not manage, kept on write */
        public array $otherKeys = [],
        /** @var list<string> ProfileToggles::DOMAIN_PROFILES items closed for domain admins */
        public array $disabledDomainProfiles = [],
        /** @var list<string> ProfileToggles::USER_PROFILES items closed for domain admins */
        public array $disabledUserProfiles = [],
        /** @var list<string> ProfileToggles::USER_PREFERENCES items closed for self-service users */
        public array $disabledUserPreferences = [],
        /** @var list<string> domain services; ProfileToggles::SELF_SERVICE opens self-service */
        public array $enabledServices = [],
    ) {}

    public function selfService(): bool
    {
        return in_array(ProfileToggles::SELF_SERVICE, $this->enabledServices, true);
    }

    /**
     * Returns the fields that fromFormData() reads, with the values of this object.
     * A JSON body is merged over them, so that a missing field keeps its value.
     */
    public function toFormData(string $disclaimer): array
    {
        return [
            'defaultUserQuota' => $this->defaultUserQuota,
            'minPasswordLength' => $this->minPasswordLength,
            'maxPasswordLength' => $this->maxPasswordLength,
            'disclaimer' => $disclaimer,
            'disabledMailServices' => $this->disabledMailServices,
            'disabledDomainProfiles' => $this->disabledDomainProfiles,
            'disabledUserProfiles' => $this->disabledUserProfiles,
            'disabledUserPreferences' => $this->disabledUserPreferences,
            'selfService' => $this->selfService(),
        ];
    }

    /**
     * Takes the keys and the domain services that the panel does not manage from a
     * stored settings string, so that a save from the settings form keeps them.
     */
    public function keepOtherKeysOf(string $stored): void
    {
        $storedSettings = self::fromSettingsString($stored);
        $this->otherKeys = $storedSettings->otherKeys;
        $otherServices = array_diff($storedSettings->enabledServices, [ProfileToggles::SELF_SERVICE]);
        $this->enabledServices = array_values(array_unique([...$otherServices, ...$this->enabledServices]));
    }

    /**
     * Keeps the stored page toggles that only a global admin changes, because they bound
     * the domain admin who saves the form.
     */
    public function keepGlobalAdminTogglesOf(self $stored): void
    {
        $this->disabledDomainProfiles = $stored->disabledDomainProfiles;
        $this->disabledUserProfiles = $stored->disabledUserProfiles;
    }

    /**
     * Parse from iRedMail's "key:value;key:value;" format (MySQL domain.settings column).
     */
    public static function fromSettingsString(string $settings): self
    {
        $result = new self();

        foreach (array_filter(explode(';', $settings)) as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $result->setStoredValue(trim($parts[0]), trim($parts[1]));
        }

        return $result;
    }

    private function setStoredValue(string $key, string $value): void
    {
        if (isset(self::LIST_KEYS[$key])) {
            $this->{self::LIST_KEYS[$key]} = array_values(array_filter(explode(',', $value)));
            return;
        }

        match ($key) {
            'default_user_quota' => $this->defaultUserQuota = (int) $value,
            'min_passwd_length' => $this->minPasswordLength = (int) $value,
            'max_passwd_length' => $this->maxPasswordLength = (int) $value,
            'disclaimer' => $this->disclaimer = $value,
            default => $this->otherKeys[$key] = $value,
        };
    }

    /**
     * Serialize to iRedMail's "key:value;key:value;" format.
     */
    public function toSettingsString(): string
    {
        $numbers = [
            'default_user_quota' => $this->defaultUserQuota,
            'min_passwd_length' => $this->minPasswordLength,
            'max_passwd_length' => $this->maxPasswordLength,
        ];
        $parts = [];
        foreach ($numbers as $key => $number) {
            if ($number > 0) {
                $parts[] = "{$key}:{$number}";
            }
        }
        // The disclaimer lives in Domain::$disclaimer; a ';' in its text would split this format.
        foreach (self::LIST_KEYS as $key => $property) {
            if ($this->$property !== []) {
                $parts[] = "{$key}:" . implode(',', $this->$property);
            }
        }
        foreach ($this->otherKeys as $key => $value) {
            if ($key !== '' && $value !== '') {
                $parts[] = "{$key}:{$value}";
            }
        }

        return $parts === [] ? '' : implode(';', $parts) . ';';
    }

    /**
     * @throws InvalidInputException when a number is not a whole number of 0 or more,
     *         a list holds an unknown name, or the min password length exceeds the max password length
     */
    public static function fromFormData(array $post): self
    {
        $settings = new self(
            defaultUserQuota: self::number($post, 'defaultUserQuota', 'domain.default_user_quota'),
            minPasswordLength: self::number($post, 'minPasswordLength', 'domain.min_password_length'),
            maxPasswordLength: self::number($post, 'maxPasswordLength', 'domain.max_password_length'),
            disclaimer: FormValue::text($post, 'disclaimer'),
            disabledMailServices: User::validServiceNames($post['disabledMailServices'] ?? []),
            disabledDomainProfiles: ProfileToggles::valid($post['disabledDomainProfiles'] ?? [], ProfileToggles::DOMAIN_PROFILES, 'disabledDomainProfiles'),
            disabledUserProfiles: ProfileToggles::valid($post['disabledUserProfiles'] ?? [], ProfileToggles::USER_PROFILES, 'disabledUserProfiles'),
            disabledUserPreferences: ProfileToggles::valid($post['disabledUserPreferences'] ?? [], ProfileToggles::USER_PREFERENCES, 'disabledUserPreferences'),
            enabledServices: (bool) ($post['selfService'] ?? false) ? [ProfileToggles::SELF_SERVICE] : [],
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
     * A domain admin may make the password policy of the domain stricter, never weaker
     * than the global minimum length.
     *
     * @throws InvalidInputException
     */
    public function assertDomainAdminPasswordPolicy(): void
    {
        $globalMin = Settings::getInstance()->passwordMinLength;
        if ($this->minPasswordLength > 0 && $this->minPasswordLength < $globalMin) {
            throw new InvalidInputException(
                "minPasswordLength must not be lower than the global minimum ({$globalMin})",
                'domain.msg_min_password_below_global',
                ['min' => $globalMin],
            );
        }
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
