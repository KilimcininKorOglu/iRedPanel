<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Puts user input into a LIKE pattern as literal text.
 *
 * The panel escapes with '!' in MySQL and PostgreSQL alike, because the
 * meaning of a backslash depends on the MySQL sql_mode and on the bytea
 * input format of the amavisd PostgreSQL schema.
 */
final class SqlLike
{
    /** Append to every LIKE whose pattern comes from escape(). */
    public const ESCAPE = "ESCAPE '!'";

    public static function escape(string $value): string
    {
        return strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }
}
