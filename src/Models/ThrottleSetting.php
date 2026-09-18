<?php

declare(strict_types=1);

namespace App\Models;

/**
 * One iRedAPD throttle row. A limit of 0 means unlimited, and -1 inherits the
 * limit of the lower-priority domain or global row (iRedAPD plugins/throttle.py).
 */
class ThrottleSetting
{
    public const KINDS = ['outbound', 'inbound', 'external'];

    public const INHERIT = -1;

    public function __construct(
        public readonly string $kind,
        public readonly int $period,
        public readonly int $maxMsgs,
        public readonly int $maxQuota,
        public readonly int $msgSize,
    ) {}

    /**
     * Reads a form or JSON body. A missing or empty limit inherits.
     *
     * @throws \InvalidArgumentException with the name of the invalid field as message
     */
    public static function fromInput(array $input): self
    {
        $kind = $input['kind'] ?? 'outbound';
        if (!in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException('kind');
        }
        // iRedAPD skips a row without a period.
        $period = self::integer($input, 'period', 3600);
        if ($period < 1) {
            throw new \InvalidArgumentException('period');
        }

        return new self(
            $kind,
            $period,
            self::limit($input, 'maxMsgs'),
            self::limit($input, 'maxQuota'),
            self::limit($input, 'msgSize'),
        );
    }

    private static function limit(array $input, string $field): int
    {
        $value = self::integer($input, $field, self::INHERIT);
        if ($value < self::INHERIT) {
            throw new \InvalidArgumentException($field);
        }
        return $value;
    }

    private static function integer(array $input, string $field, int $default): int
    {
        $raw = $input[$field] ?? null;
        if (is_int($raw)) {
            return $raw;
        }
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return $default;
        }
        if (is_string($raw) && preg_match('/^-?\d+$/D', trim($raw)) === 1) {
            return (int) trim($raw);
        }
        throw new \InvalidArgumentException($field);
    }
}
