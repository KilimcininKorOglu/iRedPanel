<?php

declare(strict_types=1);

namespace App\Api;

use App\Utils\AddressList;
use App\Utils\Relayhost;

/**
 * Reads optional fields of a JSON request body. Every method throws
 * \InvalidArgumentException with the message for the API client.
 */
final class ApiInput
{
    /**
     * Reads an optional email address. Null or an empty string clears the value.
     */
    public static function address(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException("{$field} must be an email address or null");
        }
        $address = strtolower(trim($value));
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Invalid {$field}: {$address}");
        }
        return $address;
    }

    /**
     * Reads an array of email addresses.
     *
     * @return list<string> lowercased and without duplicates
     */
    public static function addresses(mixed $value, string $field): array
    {
        $items = self::strings($value, $field);
        try {
            return AddressList::parse(implode("\n", $items));
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException("Invalid {$field} address: {$e->getMessage()}");
        }
    }

    /**
     * Reads an array of strings.
     *
     * @return list<string>
     */
    public static function strings(mixed $value, string $field): array
    {
        if (!is_array($value) || !array_is_list($value) || array_filter($value, 'is_string') !== $value) {
            throw new \InvalidArgumentException("{$field} must be an array of strings");
        }
        return $value;
    }

    /**
     * Reads an optional Postfix relay host. Null or an empty string removes the relay.
     */
    public static function relayhost(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !Relayhost::isValid(trim($value))) {
            throw new \InvalidArgumentException("Invalid {$field}");
        }
        return trim($value);
    }

    /**
     * Reads a boolean field that must be a JSON true or false.
     */
    public static function bool(array $data, string $field, bool $default): bool
    {
        $value = $data[$field] ?? $default;
        if (!is_bool($value)) {
            throw new \InvalidArgumentException("{$field} must be true or false");
        }
        return $value;
    }

    /**
     * Applies a list change of the body to $current. The body either replaces the
     * list with $keys[0] or adds and removes items with $keys[1] and $keys[2].
     *
     * @param array{0: string, 1: string, 2: string} $keys the replace, add and remove fields
     * @param list<string> $current
     * @param callable(mixed, string): list<string> $parse reads one field value
     * @return ?list<string> the new list, or null when the body has none of the fields
     */
    public static function listChange(array $data, array $keys, array $current, callable $parse): ?array
    {
        [$replace, $add, $remove] = $keys;
        $hasReplace = array_key_exists($replace, $data);
        $hasDelta = array_key_exists($add, $data) || array_key_exists($remove, $data);
        if ($hasReplace && $hasDelta) {
            throw new \InvalidArgumentException("{$replace} conflicts with {$add} and {$remove}");
        }
        if ($hasReplace) {
            return $parse($data[$replace], $replace);
        }
        if (!$hasDelta) {
            return null;
        }

        $added = array_key_exists($add, $data) ? $parse($data[$add], $add) : [];
        $removed = array_key_exists($remove, $data) ? $parse($data[$remove], $remove) : [];
        return array_values(array_diff(array_unique(array_merge($current, $added)), $removed));
    }
}
