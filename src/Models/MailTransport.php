<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;

/**
 * A Postfix transport of one account, such as `dovecot`, `lmtp:unix:private/dovecot-lmtp`
 * or `smtp:[mx.example.com]:2525`. An empty value means the transport of the domain.
 */
final class MailTransport
{
    /** Postfix writes the transport into a map file, so a space or a newline must not enter it. */
    private const PATTERN = '#^[A-Za-z0-9_./:\[\]@+-]{1,255}$#';

    /**
     * @return ?string the transport, or null for the domain default
     * @throws InvalidInputException when the value is not a transport
     */
    public static function valid(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || preg_match(self::PATTERN, trim($value)) !== 1) {
            throw new InvalidInputException('Invalid transport', 'user.msg_invalid_transport', [], 'user.transport');
        }

        return trim($value);
    }
}
