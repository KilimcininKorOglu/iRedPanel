<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Maps the LDAP domain attribute accountSetting ("key:value" values, the keys that
 * iRedAdmin uses) to Domain fields. Values of other keys are kept on write.
 */
final class LdapAccountSetting
{
    /** Domain property => accountSetting key. 0 means unlimited and is stored as no value. */
    private const LIMIT_KEYS = [
        'maxQuota' => 'maxUserQuota',
        'mailboxes' => 'numberOfUsers',
        'aliases' => 'numberOfAliases',
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
        $managed = array_values(self::LIMIT_KEYS);
        $values = array_values(array_filter(
            $current,
            static fn (string $value): bool => !in_array(self::keyOf($value), $managed, true)
        ));
        foreach (self::LIMIT_KEYS as $property => $key) {
            if ($domain->$property !== 0) {
                $values[] = "{$key}:{$domain->$property}";
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
