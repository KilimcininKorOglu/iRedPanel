<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The link between a directory object (by its GUID) and a local account.
 */
class ReplicatedAccount
{
    public const KIND_USER = 'user';
    public const KIND_GROUP = 'group';

    /** The local account follows the directory object. */
    public const STATE_ACTIVE = 'active';
    /** The directory object disappeared, so the local account was disabled. */
    public const STATE_MISSING = 'missing';
    /** The address belongs to a local account that the directory does not own. */
    public const STATE_CONFLICT = 'conflict';
    /** The directory object has no usable address, so no local account exists. */
    public const STATE_SKIPPED = 'skipped';

    /**
     * @param string $fingerprint hash of the replicated values, or the reason of a conflict or skip
     */
    public function __construct(
        public readonly string $guid,
        public readonly string $kind,
        public readonly string $address,
        public readonly string $sourceDn,
        public readonly string $fingerprint,
        public readonly string $state,
        public readonly int $lastSeenAt,
        public readonly int $resourceId = 0,
    ) {}

    /**
     * True when the panel owns a local account for this object.
     */
    public function ownsAccount(): bool
    {
        return $this->state === self::STATE_ACTIVE || $this->state === self::STATE_MISSING;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            guid: (string) $row['object_guid'],
            kind: (string) $row['kind'],
            address: (string) $row['address'],
            sourceDn: (string) $row['source_dn'],
            fingerprint: (string) $row['fingerprint'],
            state: (string) $row['state'],
            lastSeenAt: (int) $row['last_seen_at'],
            resourceId: (int) $row['resource_id'],
        );
    }
}
