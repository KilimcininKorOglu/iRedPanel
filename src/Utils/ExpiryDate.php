<?php

declare(strict_types=1);

namespace App\Utils;

use App\Exceptions\InvalidInputException;

/**
 * The account expiry date of a mailbox, a domain or an admin. The model value is
 * a date as YYYY-MM-DD, and '' means that the account never expires.
 *
 * The SQL column is NOT NULL and holds the far future date as its "no expiry"
 * value, the LDAP attribute holds a generalized time and is absent when the
 * account never expires.
 */
final class ExpiryDate
{
    /** The value that the SQL `expired` column holds when the account never expires. */
    public const SQL_NONE = '9999-12-31 00:00:00';

    /** The date part of SQL_NONE. */
    public const NONE = '9999-12-31';

    /**
     * @throws InvalidInputException when the value is not '' or a date in YYYY-MM-DD format
     */
    public static function valid(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (CalendarDate::isValid($value)) {
            return $value === self::NONE ? '' : (string) $value;
        }

        throw new InvalidInputException(
            'expiredDate must be a date in YYYY-MM-DD format',
            'common.msg_invalid_expired_date',
        );
    }

    /** Converts a stored SQL value to the model value; the column default means no expiry. */
    public static function fromSql(mixed $stored): string
    {
        $date = substr((string) ($stored ?? ''), 0, 10);

        return $date === '' || $date === self::NONE ? '' : $date;
    }

    /** Converts the model value to the SQL column value; the column is NOT NULL. */
    public static function toSql(string $date): string
    {
        return $date === '' ? self::SQL_NONE : $date . ' 00:00:00';
    }

    /** Converts an LDAP generalized time (YYYYMMDDHHMMSSZ) to the model value. */
    public static function fromLdap(mixed $stored): string
    {
        $value = (string) ($stored ?? '');
        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $match) !== 1) {
            return '';
        }
        $date = "{$match[1]}-{$match[2]}-{$match[3]}";

        return $date === self::NONE ? '' : $date;
    }

    /** The LDAP attribute value; null removes the attribute, so the account never expires. */
    public static function toLdap(string $date): ?string
    {
        return $date === '' ? null : str_replace('-', '', $date) . '000000Z';
    }

    /**
     * Refuses an account whose expiry date has passed. The login paths call it, because
     * the cron script runs once a day and a date passes between two runs.
     *
     * @param string $date the model value (YYYY-MM-DD or '')
     * @throws \RuntimeException when the date has passed
     */
    public static function assertNotExpired(string $account, string $date): void
    {
        if (self::isExpired($date)) {
            throw new \RuntimeException("Account {$account} expired on {$date}");
        }
    }

    /**
     * The value that `expired < :cutoff` compares against, so that the SQL answer
     * matches isExpired(): every date before today has passed.
     *
     * @param ?int $now unix timestamp, the current time when null
     */
    public static function sqlCutoff(?int $now = null): string
    {
        return date('Y-m-d', $now ?? time()) . ' 00:00:00';
    }

    /**
     * Whether the account is expired. The date is the last valid day, so the
     * account expires at the start of the next day.
     *
     * @param ?int $now unix timestamp, the current time when null
     */
    public static function isExpired(string $date, ?int $now = null): bool
    {
        if ($date === '') {
            return false;
        }

        // Local time, because the SQL comparison runs against the database server clock.
        return ($now ?? time()) >= (int) strtotime($date . ' 00:00:00') + 86400;
    }
}
