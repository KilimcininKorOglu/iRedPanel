<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * The "key:value;key:value;" format of the iRedMail settings columns (admin.settings,
 * mailbox.settings, domain.settings).
 */
final class SettingsString
{
    /**
     * @return array<string, string> the pairs with a key and a value; the value keeps every ':' after the first
     */
    public static function parse(string $settings): array
    {
        $pairs = [];
        foreach (explode(';', $settings) as $pair) {
            [$key, $value] = array_map('trim', explode(':', $pair, 2)) + [1 => ''];
            if ($key !== '' && $value !== '') {
                $pairs[$key] = $value;
            }
        }

        return $pairs;
    }

    /**
     * Writes $values over the stored pairs. A managed key that $values does not hold is
     * removed; every other stored key stays.
     *
     * @param array<string, scalar> $stored
     * @param array<string, string> $values
     * @param string[] $managedKeys
     */
    public static function merge(array $stored, array $values, array $managedKeys): string
    {
        $pairs = array_diff_key($stored, array_flip($managedKeys)) + $values;
        $parts = [];
        foreach ($pairs as $key => $value) {
            $value = is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;
            if ($value !== '') {
                $parts[] = "{$key}:{$value}";
            }
        }

        return $parts === [] ? '' : implode(';', $parts) . ';';
    }
}
