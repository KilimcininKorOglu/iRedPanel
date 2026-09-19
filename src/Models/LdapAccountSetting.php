<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Maps the LDAP domain attribute accountSetting ("key:value" values, the keys that
 * iRedAdmin uses) to the Domain limits and the DomainSettings of Domain::$settings.
 * Values of other keys are kept on write.
 */
final class LdapAccountSetting
{
    /** Domain property => accountSetting key. 0 means unlimited and is stored as no value. */
    private const LIMIT_KEYS = [
        'maxQuota' => 'maxUserQuota',
        'mailboxes' => 'numberOfUsers',
        'aliases' => 'numberOfAliases',
    ];

    /** DomainSettings property => accountSetting key. 0 means "use the global setting". */
    private const SETTING_KEYS = [
        'defaultUserQuota' => 'defaultQuota',
        'minPasswordLength' => 'minPasswordLength',
        'maxPasswordLength' => 'maxPasswordLength',
    ];

    /**
     * Sets the Domain fields from the stored accountSetting values.
     *
     * @param string[] $values
     */
    public static function applyTo(Domain $domain, array $values): void
    {
        foreach (self::LIMIT_KEYS as $property => $key) {
            $domain->$property = (int) (self::valueOf($values, $key) ?? 0);
        }

        $settings = new DomainSettings();
        foreach (self::SETTING_KEYS as $property => $key) {
            $settings->$property = (int) (self::valueOf($values, $key) ?? 0);
        }
        $domain->settings = $settings->toSettingsString();
    }

    /**
     * Returns the accountSetting values for the Domain fields, with the $current values
     * of every key that the panel does not manage.
     *
     * @param string[] $current
     * @return string[]
     */
    public static function valuesFor(Domain $domain, array $current): array
    {
        $managed = [...array_values(self::LIMIT_KEYS), ...array_values(self::SETTING_KEYS)];
        $values = array_values(array_filter(
            $current,
            static fn (string $value): bool => !in_array(self::keyOf($value), $managed, true)
        ));

        $settings = DomainSettings::fromSettingsString($domain->settings);
        $numbers = [];
        foreach (self::LIMIT_KEYS as $property => $key) {
            $numbers[$key] = $domain->$property;
        }
        foreach (self::SETTING_KEYS as $property => $key) {
            $numbers[$key] = $settings->$property;
        }
        foreach ($numbers as $key => $number) {
            if ($number !== 0) {
                $values[] = "{$key}:{$number}";
            }
        }

        return $values;
    }

    private static function keyOf(string $value): string
    {
        return explode(':', $value, 2)[0];
    }

    /**
     * @param string[] $values
     */
    private static function valueOf(array $values, string $key): ?string
    {
        foreach ($values as $value) {
            if (self::keyOf($value) === $key) {
                return explode(':', $value, 2)[1] ?? '';
            }
        }

        return null;
    }
}
