<?php

declare(strict_types=1);

namespace App\Models;

class MailingList
{
    public function __construct(
        public readonly string $address,
        public readonly string $domain,
        public readonly string $name = '',
        public readonly string $accessPolicy = 'public',
        public readonly string $transport = '',
        public readonly int $maxMsgSize = 0,
        public readonly bool $active = true,
        public readonly ?string $created = null,
        public readonly string $mlid = '',
        public readonly bool $isNewsletter = false,
    ) {}

    /**
     * Returns a random UUID v4, the server-wide list ID that mlmmjadmin stores in `mlid`.
     */
    public static function generateId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Validates a maximum message size in bytes. 0 means unlimited.
     * An empty value gives 0, like an empty form field.
     *
     * @throws \InvalidArgumentException when the value is not a whole number of 0 or more
     */
    public static function validMaxMsgSize(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit(trim($value))) {
            return (int) trim($value);
        }
        throw new \InvalidArgumentException('maxMsgSize must be a whole number of 0 or more');
    }

    /**
     * Returns the Postfix transport of a list, `mlmmj:<domain>/<listname>`.
     * The Postfix `mlmmj` pipe appends the nexthop to `/var/vmail/mlmmj/`.
     */
    public static function transportFor(string $address): string
    {
        if (!str_contains($address, '@')) {
            throw new \InvalidArgumentException("Invalid mailing list address: {$address}");
        }
        [$listName, $domain] = explode('@', strtolower($address), 2);

        return "mlmmj:{$domain}/{$listName}";
    }
}
