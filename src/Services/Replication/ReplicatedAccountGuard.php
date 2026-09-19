<?php

declare(strict_types=1);

namespace App\Services\Replication;

use App\I18n\Translator;
use App\Models\AccountResource;
use App\Models\User;
use App\Repositories\AccountResourceRepositoryInterface;
use App\Repositories\RepositoryFactory;

/**
 * Keeps the directory-owned fields of a replicated account unchanged in the web
 * form and the REST API. The directory is the source of these fields: a local
 * change would stay until the directory entry changes, and then be overwritten.
 */
final class ReplicatedAccountGuard
{
    /** @var array<string, ?AccountResource> address => owning resource, per request */
    private static array $owners = [];

    private static ?AccountResourceRepositoryInterface $repository = null;

    /**
     * Returns the resource that replicates the address, or null for a local account.
     */
    public static function owner(string $address): ?AccountResource
    {
        $address = strtolower($address);
        if (!array_key_exists($address, self::$owners)) {
            self::$owners[$address] = self::lookup($address);
        }

        return self::$owners[$address];
    }

    /**
     * The User properties that the directory owns: the mapped profile fields and the status.
     *
     * @return string[]
     */
    public static function lockedUserFields(string $address): array
    {
        $resource = self::owner($address);
        if ($resource === null) {
            return [];
        }

        return array_keys(array_filter($resource->userAttributes, static fn(string $attribute): bool => $attribute !== ''));
    }

    /**
     * Copies the directory-owned fields of the stored user into the submitted one.
     */
    public static function keepUserFields(string $address, User $submitted, User $stored): void
    {
        foreach (self::lockedUserFields($address) as $property) {
            $submitted->$property = $stored->$property;
        }
    }

    /**
     * The directory-owned properties whose submitted value differs from the stored one.
     *
     * @return string[]
     */
    public static function changedUserFields(string $address, User $submitted, User $stored): array
    {
        return array_values(array_filter(
            self::lockedUserFields($address),
            static fn(string $property): bool => (string) $submitted->$property !== (string) $stored->$property,
        ));
    }

    /**
     * True when the directory owns the members and the name of the alias.
     */
    public static function isGroupLocked(string $address): bool
    {
        return self::owner($address) !== null;
    }

    /**
     * The directory-owned alias fields (name, members) whose submitted value differs from the stored one.
     *
     * @param string[] $members
     * @param string[] $storedMembers
     * @return string[]
     */
    public static function changedGroupFields(string $address, string $name, array $members, string $storedName, array $storedMembers): array
    {
        if (!self::isGroupLocked($address)) {
            return [];
        }
        $changed = [];
        if ($name !== $storedName) {
            $changed[] = 'name';
        }
        $normalize = static function (array $list): array {
            $list = array_map('strtolower', $list);
            sort($list);
            return $list;
        };
        if ($normalize($members) !== $normalize($storedMembers)) {
            $changed[] = 'members';
        }

        return $changed;
    }

    /**
     * @throws \RuntimeException when the directory owns the address
     */
    public static function assertNotReplicated(string $address): void
    {
        if (self::owner($address) !== null) {
            throw new \RuntimeException(Translator::translate('resource.msg_managed', ['address' => $address]));
        }
    }

    /**
     * Uses another repository, for tests; null restores the default.
     */
    public static function useRepository(?AccountResourceRepositoryInterface $repository): void
    {
        self::$repository = $repository;
        self::$owners = [];
    }

    private static function lookup(string $address): ?AccountResource
    {
        $repo = self::$repository ?? RepositoryFactory::getAccountResourceRepository();
        if (!$repo->isAvailable()) {
            return null;
        }
        $repo->ensureTablesExist();
        $link = $repo->findOwner($address);

        return $link === null ? null : $repo->find($link->resourceId);
    }
}
