<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Parses a whole number of 0 or more from a form string or a JSON value.
 */
final class WholeNumber
{
    /**
     * @return ?int the number, 0 for an empty value, or null when the value is not a whole number of 0 or more
     */
    public static function parse(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (is_string($value) && ctype_digit(trim($value))) {
            return (int) trim($value);
        }

        return null;
    }
}
