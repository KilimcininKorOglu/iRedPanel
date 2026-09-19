<?php

declare(strict_types=1);

namespace App\Services\Replication;

use App\Models\ReplicatedAccount;

/**
 * One change that a replication run applies to a local account.
 */
final class ReplicationAction
{
    public const CREATE = 'create';
    public const UPDATE = 'update';
    public const RENAME = 'rename';
    public const DISABLE = 'disable';
    public const ENABLE = 'enable';
    public const CONFLICT = 'conflict';
    public const SKIP = 'skip';
    /** The object of a conflict or skip link is gone; only the link is removed, nothing is logged. */
    public const FORGET = 'forget';
    /** Set by the runner when an action fails. */
    public const ERROR = 'error';

    /**
     * @param string $fingerprint the value that the link stores after the action
     * @param string[] $members the member addresses of a group
     */
    public function __construct(
        public readonly string $type,
        public readonly string $kind,
        public readonly string $guid,
        public readonly ?SourceAccount $source,
        public readonly ?ReplicatedAccount $link,
        public readonly string $fingerprint,
        public readonly array $members = [],
        public readonly string $detail = '',
    ) {}

    /**
     * The address that the log shows: the directory address, or the local one when the object is gone.
     */
    public function address(): string
    {
        return $this->source?->address ?: ($this->link?->address ?? '');
    }
}
