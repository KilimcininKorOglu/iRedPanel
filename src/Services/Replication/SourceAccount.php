<?php

declare(strict_types=1);

namespace App\Services\Replication;

/**
 * A directory user or group, mapped to the values that the panel replicates.
 */
final class SourceAccount
{
    public const SKIP_NO_ADDRESS = 'no_address';
    public const SKIP_INVALID_ADDRESS = 'invalid_address';
    public const SKIP_OTHER_DOMAIN = 'other_domain';
    /** Another object of the same run already uses the address. */
    public const SKIP_DUPLICATE_ADDRESS = 'duplicate_address';

    /**
     * @param string $skipReason one of the SKIP_* values, or '' when the account can be replicated
     * @param ?bool $active the directory account status, or null when the status is not replicated
     * @param array<string, string> $profile User property => value, only the mapped properties
     * @param string[] $memberDns the member DNs of a group
     */
    public function __construct(
        public readonly string $guid,
        public readonly string $kind,
        public readonly string $dn,
        public readonly string $address,
        public readonly string $skipReason = '',
        public readonly ?bool $active = null,
        public readonly array $profile = [],
        public readonly string $name = '',
        public readonly array $memberDns = [],
    ) {}

    public function isUsable(): bool
    {
        return $this->skipReason === '';
    }
}
