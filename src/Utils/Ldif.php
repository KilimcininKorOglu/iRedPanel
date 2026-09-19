<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Writes LDIF text (RFC 2849): one line per attribute value, base64 for a
 * value that LDIF cannot hold as text, and a fold for a long line.
 */
final class Ldif
{
    /** Maximum length of a line; the rest continues with a leading space. */
    private const LINE_LENGTH = 76;

    /**
     * One entry, with the blank line that separates it from the next.
     *
     * @param array<string, list<string>> $attributes
     */
    public static function entry(string $dn, array $attributes): string
    {
        $ldif = self::line('dn', $dn);
        foreach ($attributes as $name => $values) {
            foreach ($values as $value) {
                $ldif .= self::line($name, $value);
            }
        }

        return $ldif . "\n";
    }

    public static function line(string $name, string $value): string
    {
        $line = self::isSafe($value) ? "{$name}: {$value}" : "{$name}:: " . base64_encode($value);

        return self::fold($line) . "\n";
    }

    /**
     * Whether the value may stand as plain text (RFC 2849 SAFE-STRING).
     */
    public static function isSafe(string $value): bool
    {
        if ($value === '') {
            return true;
        }
        if (preg_match('/[^\x01-\x09\x0b-\x0c\x0e-\x7f]/', $value) === 1) {
            return false;
        }

        return !in_array($value[0], [' ', ':', '<'], true) && !str_ends_with($value, ' ');
    }

    private static function fold(string $line): string
    {
        if (strlen($line) <= self::LINE_LENGTH) {
            return $line;
        }

        $folded = substr($line, 0, self::LINE_LENGTH);
        foreach (str_split(substr($line, self::LINE_LENGTH), self::LINE_LENGTH - 1) as $part) {
            $folded .= "\n " . $part;
        }

        return $folded;
    }
}
