<?php

declare(strict_types=1);

namespace App\Models;

class Alias
{
    /** Posting restrictions that iRedAPD enforces for aliases and mailing lists. */
    public const ACCESS_POLICIES = ['public', 'domain', 'membersOnly', 'moderatorsOnly'];

    /**
     * @throws \InvalidArgumentException when iRedAPD does not know the policy
     */
    public static function validAccessPolicy(mixed $policy): string
    {
        if (!in_array($policy, self::ACCESS_POLICIES, true)) {
            throw new \InvalidArgumentException('accessPolicy must be one of: ' . implode(', ', self::ACCESS_POLICIES));
        }
        return $policy;
    }

    public function __construct(
        public readonly string $address,
        public readonly string $domain,
        public readonly string $name = '',
        public readonly string $accessPolicy = 'public',
        public readonly bool $islist = true,
        public readonly bool $active = true,
        public readonly ?string $created = null,
        public readonly ?string $modified = null,
    ) {}
}
