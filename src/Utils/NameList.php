<?php

declare(strict_types=1);

namespace App\Utils;

use App\Exceptions\InvalidInputException;

/**
 * Validates a form or JSON list whose items must come from a fixed set of names.
 */
final class NameList
{
    /**
     * @param string[] $allowed
     * @param string $what the item kind in the English message, e.g. "mail service"
     * @param string $translationKey message key with the placeholder :$param
     * @return list<string> the names without duplicates
     * @throws InvalidInputException when $names is not a list of allowed names
     */
    public static function valid(mixed $names, array $allowed, string $what, string $translationKey, string $param): array
    {
        if (!is_array($names)) {
            throw new InvalidInputException("A list of {$what} names is required", $translationKey, [$param => gettype($names)]);
        }
        foreach ($names as $name) {
            if (!is_string($name) || !in_array($name, $allowed, true)) {
                $shown = is_string($name) ? $name : gettype($name);
                throw new InvalidInputException("Unknown {$what}: {$shown}", $translationKey, [$param => $shown]);
            }
        }

        return array_values(array_unique($names));
    }
}
