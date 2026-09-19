<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * The posting restriction of a group account. Each account type has its own
 * set of policies, so the caller passes the set it accepts.
 */
class AccessPolicy
{
    /**
     * @param list<string> $policies the policies of the account type
     * @throws \InvalidArgumentException when iRedAPD does not know the policy
     */
    public static function validate(mixed $policy, array $policies): string
    {
        if (!in_array($policy, $policies, true)) {
            throw new \InvalidArgumentException('accessPolicy must be one of: ' . implode(', ', $policies));
        }

        return $policy;
    }
}
