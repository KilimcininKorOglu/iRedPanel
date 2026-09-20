<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * A date field of a form or a JSON body, as YYYY-MM-DD.
 */
final class CalendarDate
{
    /** Whether the value is a date in YYYY-MM-DD format that exists in the calendar. */
    public static function isValid(mixed $value): bool
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }
        [$year, $month, $day] = array_map(intval(...), explode('-', $value));

        return checkdate($month, $day, $year);
    }
}
