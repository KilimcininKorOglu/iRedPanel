<?php

declare(strict_types=1);

namespace App\Services\Replication;

use App\Models\ReplicatedAccount;

/**
 * Compares the directory objects with the stored links and decides the actions of a run.
 * It reads nothing itself, so every decision is testable without a server.
 */
final class ReplicationPlanner
{
    /** The mass-disable guard applies from this many owned accounts. */
    public const GUARD_MIN_ACCOUNTS = 10;

    private const SKIP_PREFIX = 'skip:';
    private const CONFLICT_PREFIX = 'conflict:';

    /** @var array<string, ReplicationAction> */
    private array $actions = [];

    /**
     * @param \Closure(string): bool $addressInUse true when a local account uses the address
     * @param bool $allowMassDisable apply the disable actions even when the guard would hold them back
     */
    public function __construct(
        private readonly \Closure $addressInUse,
        private readonly bool $allowMassDisable = false,
    ) {}

    /**
     * @param SourceAccount[] $users
     * @param SourceAccount[]|null $groups null when the resource does not replicate groups
     * @param array<string, ReplicatedAccount> $links keyed by GUID
     */
    public function plan(array $users, ?array $groups, array $links): ReplicationPlan
    {
        $this->actions = [];
        $sources = $this->claimAddresses([...$users, ...($groups ?? [])]);
        $memberAddresses = $this->memberAddresses($sources);

        foreach ($sources as $source) {
            $this->planSource($source, $links[$source->guid] ?? null, $memberAddresses);
        }
        $scope = $groups === null ? [ReplicatedAccount::KIND_USER] : [ReplicatedAccount::KIND_USER, ReplicatedAccount::KIND_GROUP];
        $this->planAbsent($sources, $links, $scope);
        $heldBack = $this->guardMassDisable($sources, $links, $scope);

        return new ReplicationPlan(array_values($this->actions), $heldBack);
    }

    /**
     * Two objects with one address cannot both own it: the first keeps it, the next is skipped.
     *
     * @param SourceAccount[] $sources
     * @return array<string, SourceAccount> keyed by GUID
     */
    private function claimAddresses(array $sources): array
    {
        $claimed = [];
        $result = [];
        foreach ($sources as $source) {
            if ($source->guid === '') {
                continue;
            }
            if ($source->isUsable() && isset($claimed[$source->address])) {
                $source = new SourceAccount($source->guid, $source->kind, $source->dn, $source->address, SourceAccount::SKIP_DUPLICATE_ADDRESS);
            }
            if ($source->isUsable()) {
                $claimed[$source->address] = true;
            }
            $result[$source->guid] = $source;
        }

        return $result;
    }

    /**
     * @param array<string, SourceAccount> $sources
     * @return array<string, string> lowercased DN => address
     */
    private function memberAddresses(array $sources): array
    {
        $map = [];
        foreach ($sources as $source) {
            if ($source->isUsable()) {
                $map[strtolower($source->dn)] = $source->address;
            }
        }

        return $map;
    }

    /**
     * @param array<string, string> $memberAddresses
     */
    private function planSource(SourceAccount $source, ?ReplicatedAccount $link, array $memberAddresses): void
    {
        if (!$source->isUsable()) {
            $this->planUnusable($source, $link);
            return;
        }
        [$members, $detail] = $this->resolveMembers($source, $memberAddresses);
        $fingerprint = self::fingerprint($source, $members);

        if ($link === null || !$link->ownsAccount()) {
            $this->planNew($source, $link, $fingerprint, $members, $detail);
            return;
        }
        $this->planOwned($source, $link, $fingerprint, $members, $detail);
    }

    /**
     * An owned account whose object lost its address is disabled once. An object without
     * an address is ignored; one with an unusable address is logged once.
     */
    private function planUnusable(SourceAccount $source, ?ReplicatedAccount $link): void
    {
        if ($link !== null && $link->ownsAccount()) {
            if ($link->state === ReplicatedAccount::STATE_ACTIVE) {
                $this->add(ReplicationAction::DISABLE, $source, $link, $link->fingerprint, detail: $source->skipReason);
            }
            return;
        }
        if ($source->skipReason === SourceAccount::SKIP_NO_ADDRESS) {
            // Built-in groups, computers and service accounts have no address: they are not mail accounts.
            if ($link !== null) {
                $this->actions[$source->guid] = new ReplicationAction(ReplicationAction::FORGET, $source->kind, $source->guid, $source, $link, '');
            }
            return;
        }
        $marker = self::SKIP_PREFIX . $source->skipReason . ':' . $source->address;
        if ($link?->fingerprint !== $marker) {
            $this->add(ReplicationAction::SKIP, $source, $link, $marker, detail: $source->skipReason);
        }
    }

    /**
     * @param string[] $members
     */
    private function planNew(SourceAccount $source, ?ReplicatedAccount $link, string $fingerprint, array $members, string $detail): void
    {
        if (($this->addressInUse)($source->address)) {
            $this->conflict($source, $link);
            return;
        }
        $this->add(ReplicationAction::CREATE, $source, $link, $fingerprint, $members, $detail);
    }

    /**
     * @param string[] $members
     */
    private function planOwned(SourceAccount $source, ReplicatedAccount $link, string $fingerprint, array $members, string $detail): void
    {
        if ($source->address !== $link->address) {
            if (($this->addressInUse)($source->address)) {
                $this->conflict($source, $link);
                return;
            }
            $this->add(ReplicationAction::RENAME, $source, $link, $fingerprint, $members, $detail);
            return;
        }
        if ($link->state === ReplicatedAccount::STATE_MISSING) {
            $this->add(ReplicationAction::ENABLE, $source, $link, $fingerprint, $members, $detail);
            return;
        }
        if ($link->fingerprint !== $fingerprint) {
            $this->add(ReplicationAction::UPDATE, $source, $link, $fingerprint, $members, $detail);
        }
    }

    private function conflict(SourceAccount $source, ?ReplicatedAccount $link): void
    {
        $marker = self::CONFLICT_PREFIX . $source->address;
        if ($link?->fingerprint !== $marker) {
            $this->add(ReplicationAction::CONFLICT, $source, $link, $marker);
        }
    }

    /**
     * An owned account whose object is gone is disabled; the link of a gone conflict or skip is removed.
     *
     * @param array<string, SourceAccount> $sources
     * @param array<string, ReplicatedAccount> $links
     * @param string[] $scope the kinds that this run read
     */
    private function planAbsent(array $sources, array $links, array $scope): void
    {
        foreach ($links as $guid => $link) {
            if (isset($sources[$guid]) || !in_array($link->kind, $scope, true)) {
                continue;
            }
            if (!$link->ownsAccount()) {
                $this->actions[$guid] = new ReplicationAction(ReplicationAction::FORGET, $link->kind, $guid, null, $link, '');
            } elseif ($link->state === ReplicatedAccount::STATE_ACTIVE) {
                $this->actions[$guid] = new ReplicationAction(ReplicationAction::DISABLE, $link->kind, $guid, null, $link, $link->fingerprint, detail: 'removed');
            }
        }
    }

    /**
     * An empty or broken search looks like the removal of every account. The disable
     * actions are held back when the directory returned nothing, or when more than half
     * of at least GUARD_MIN_ACCOUNTS owned accounts would be disabled.
     *
     * @param array<string, SourceAccount> $sources
     * @param array<string, ReplicatedAccount> $links
     * @param string[] $scope
     * @return int the number of held back actions
     */
    private function guardMassDisable(array $sources, array $links, array $scope): int
    {
        $disables = array_filter($this->actions, static fn(ReplicationAction $a): bool => $a->type === ReplicationAction::DISABLE);
        if ($disables === [] || $this->allowMassDisable) {
            return 0;
        }
        $owned = count(array_filter(
            $links,
            static fn(ReplicatedAccount $l): bool => $l->state === ReplicatedAccount::STATE_ACTIVE && in_array($l->kind, $scope, true),
        ));
        $massive = $owned >= self::GUARD_MIN_ACCOUNTS && count($disables) * 2 > $owned;
        if ($sources !== [] && !$massive) {
            return 0;
        }
        $this->actions = array_diff_key($this->actions, $disables);

        return count($disables);
    }

    /**
     * @param array<string, string> $memberAddresses
     * @return array{string[], string} the sorted member addresses and a note about unresolved members
     */
    private function resolveMembers(SourceAccount $source, array $memberAddresses): array
    {
        if ($source->kind !== ReplicatedAccount::KIND_GROUP) {
            return [[], ''];
        }
        $members = [];
        $unresolved = 0;
        foreach ($source->memberDns as $dn) {
            $address = $memberAddresses[strtolower($dn)] ?? null;
            if ($address === null) {
                $unresolved++;
            } elseif ($address !== $source->address) {
                $members[] = $address;
            }
        }
        $members = array_values(array_unique($members));
        sort($members);

        return [$members, $unresolved > 0 ? "unresolved_members:{$unresolved}" : ''];
    }

    /**
     * @param string[] $members
     */
    private static function fingerprint(SourceAccount $source, array $members): string
    {
        return sha1(json_encode(
            [$source->address, $source->active, $source->profile, $source->name, $members],
            JSON_THROW_ON_ERROR,
        ));
    }

    /**
     * @param string[] $members
     */
    private function add(
        string $type,
        SourceAccount $source,
        ?ReplicatedAccount $link,
        string $fingerprint,
        array $members = [],
        string $detail = '',
    ): void {
        $this->actions[$source->guid] = new ReplicationAction($type, $source->kind, $source->guid, $source, $link, $fingerprint, $members, $detail);
    }
}
