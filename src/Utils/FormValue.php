<?php

declare(strict_types=1);

namespace App\Utils;

use App\Exceptions\InvalidInputException;

/**
 * Reads text fields from a form post or a decoded JSON body.
 */
final class FormValue
{
    /**
     * Returns the trimmed field. A JSON number is accepted as its text.
     *
     * @throws InvalidInputException when the field is an array, an object or a boolean
     */
    public static function text(array $post, string $key, string $default = ''): string
    {
        $value = $post[$key] ?? $default;
        if (is_string($value) || is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        throw new InvalidInputException("{$key} must be a string", 'common.msg_invalid_input');
    }
}
