<?php

declare(strict_types=1);

namespace App\Repositories;

interface ForwardingRepositoryInterface
{
    /**
     * Returns forwarding addresses for a user (excluding self-forwarding).
     *
     * @return string[]
     */
    public function getForwardings(string $email): array;

    /**
     * Sets forwarding addresses for a user (replaces all existing).
     *
     * @param string[] $forwardingAddresses
     */
    public function setForwardings(string $email, string $domain, array $forwardingAddresses): void;

    /**
     * Whether the user has a self-forwarding entry (keeps a local copy).
     */
    public function getKeepCopy(string $email): bool;

    /**
     * Sets whether the user keeps a local copy when forwarding. Call it after
     * setForwardings(): a user without forwarding addresses always keeps the
     * SQL self row, because Postfix needs it for local delivery.
     */
    public function setKeepCopy(string $email, string $domain, bool $keepCopy): void;
}
