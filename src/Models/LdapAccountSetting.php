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
        'lists' => 'numberOfLists',
    ];

    /** DomainSettings property => accountSetting key. 0 means "use the global setting". */
    private const SETTING_KEYS = [
        'defaultUserQuota' => 'defaultQuota',
        'minPasswordLength' => 'minPasswordLength',
        'maxPasswordLength' => 'maxPasswordLength',
    ];

    /** DomainSettings list property => accountSetting key with one value per item ("key:item"). */
    private const LIST_KEYS = [
        'disabledMailServices' => 'disabledMailService',
        'disabledDomainProfiles' => 'disabledDomainProfile',
        'disabledUserProfiles' => 'disabledUserProfile',
        'disabledUserPreferences' => 'disabledUserPreference',
    ];

    /**
     * Sets the Domain fields from the stored accountSetting values.
     *
     * @param string[] $values
     * @param string[] $enabledServices values of the domain attribute enabledService
     */
    public static function applyTo(Domain $domain, array $values, array $enabledServices = []): void
    {
        foreach (self::LIMIT_KEYS as $property => $key) {
            $domain->$property = (int) (self::valueOf($values, $key) ?? 0);
        }

        $settings = new DomainSettings();
        foreach (self::SETTING_KEYS as $property => $key) {
            $settings->$property = (int) (self::valueOf($values, $key) ?? 0);
        }
        foreach (self::LIST_KEYS as $property => $key) {
            $settings->$property = self::itemsOf($values, $key);
        }
        $settings->enabledServices = array_values($enabledServices);
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
        $managed = [...array_values(self::LIMIT_KEYS), ...array_values(self::SETTING_KEYS), ...array_values(self::LIST_KEYS)];
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
        foreach (self::LIST_KEYS as $property => $key) {
            foreach ($settings->$property as $item) {
                $values[] = "{$key}:{$item}";
            }
        }

        return $values;
    }

    /**
     * Returns the items of a key that has one value per item, lowercased as iRedAdmin reads them.
     *
     * @param string[] $values
     * @return list<string>
     */
    private static function itemsOf(array $values, string $key): array
    {
        $items = [];
        foreach ($values as $value) {
            $parts = explode(':', $value, 2);
            if ($parts[0] === $key && ($parts[1] ?? '') !== '') {
                $items[] = strtolower($parts[1]);
            }
        }

        return array_values(array_unique($items));
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
