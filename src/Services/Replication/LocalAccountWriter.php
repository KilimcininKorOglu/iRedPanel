<?php

declare(strict_types=1);

namespace App\Services\Replication;

use App\Exceptions\InvalidInputException;
use App\Models\AccountResource;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\ReplicatedAccount;
use App\Models\User;
use App\Repositories\AliasRepositoryInterface;
use App\Repositories\DomainRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use App\Services\AccountSettingsService;
use App\Utils\PasswordUtils;

/**
 * Applies replication actions to the local mailboxes and aliases through the
 * same repositories and limit checks as the web form, the REST API and the CLI.
 */
final class LocalAccountWriter
{
    /** User properties that a resource may replicate. */
    private const PROFILE_PROPERTIES = ['cn', 'givenName', 'sn', 'employeeNumber', 'title', 'mobile', 'telephoneNumber'];

    public function __construct(
        private readonly AccountResource $resource,
        private readonly UserRepositoryInterface $users,
        private readonly AliasRepositoryInterface $aliases,
        private readonly DomainRepositoryInterface $domains,
    ) {}

    /**
     * Creates the account of the action, or updates it when it exists already.
     *
     * @return string '' or 'recreated' when a known object had lost its local account
     */
    public function write(ReplicationAction $action): string
    {
        $source = $action->source ?? throw new \LogicException("Action {$action->type} has no directory object");
        if ($action->kind === ReplicatedAccount::KIND_GROUP) {
            return $this->writeGroup($source, $action->members, $action->type !== ReplicationAction::CREATE);
        }

        return $this->writeUser($source, $action->type === ReplicationAction::ENABLE, $action->type !== ReplicationAction::CREATE);
    }

    /**
     * Moves the local account to the new address of the object.
     */
    public function rename(ReplicationAction $action): void
    {
        $old = $action->link->address ?? throw new \LogicException('A rename needs the stored address');
        $new = $action->source->address ?? throw new \LogicException('A rename needs the directory object');
        if ($action->kind === ReplicatedAccount::KIND_GROUP) {
            $this->renameGroup($old, $new);
            return;
        }
        if ($this->users->getUser($this->resource->domain, self::localPart($old)) === null) {
            return;
        }
        $this->users->renameUser($this->resource->domain, self::localPart($old), self::localPart($new));
        AccountSettingsService::renameAccount($old, $new);
    }

    /**
     * Disables the local account. A missing account needs no change.
     */
    public function disable(ReplicatedAccount $link): void
    {
        if ($link->kind === ReplicatedAccount::KIND_GROUP) {
            if ($this->aliases->getAlias($link->address) !== null) {
                $this->aliases->enableDisableAlias($link->address, false);
            }
            return;
        }
        $user = $this->users->getUser($this->resource->domain, self::localPart($link->address));
        if ($user !== null && $user->accountStatus) {
            $user->accountStatus = false;
            $this->users->updateUser($this->resource->domain, $user);
        }
    }

    /**
     * @param bool $enable the object came back, so an unreplicated status turns active again
     */
    private function writeUser(SourceAccount $source, bool $enable, bool $mayExist): string
    {
        $uid = self::localPart($source->address);
        $user = $mayExist ? $this->users->getUser($this->resource->domain, $uid) : null;
        if ($user === null) {
            $this->createUser($source, $uid);
            return $mayExist ? 'recreated' : '';
        }
        $this->applyProfile($user, $source);
        if ($source->active !== null || $enable) {
            $user->accountStatus = $source->active ?? true;
        }
        $this->users->updateUser($this->resource->domain, $user);

        return '';
    }

    private function createUser(SourceAccount $source, string $uid): void
    {
        $domain = $this->domain();
        $settings = DomainSettings::fromSettingsString($domain->settings);
        $quota = max(0, $settings->defaultUserQuota);
        $limitError = $domain->newMailboxError($quota);
        if ($limitError !== null) {
            throw $limitError;
        }
        $user = new User(uid: $uid, accountStatus: $source->active ?? true, mailQuota: $quota);
        $user->setNewMailboxServices($settings->disabledMailServices);
        $this->applyProfile($user, $source);
        // Nobody knows this password: the admin or the user sets one in the panel.
        $hash = PasswordUtils::generatePasswordHash(PasswordUtils::generateRandomPassword(32));
        $this->users->createUser($this->resource->domain, $user, $hash);
    }

    /**
     * @param string[] $members
     */
    private function writeGroup(SourceAccount $source, array $members, bool $mayExist): string
    {
        $alias = $mayExist ? $this->aliases->getAlias($source->address) : null;
        if ($alias === null) {
            $this->createGroup($source->address, $source->name, $members, $this->resource->groupAccessPolicy);
            return $mayExist ? 'recreated' : '';
        }
        // The access policy is set at creation; the admin may change it in the panel.
        $this->aliases->updateAlias($source->address, $source->name, $members, $alias->accessPolicy, true);

        return '';
    }

    /**
     * @param string[] $members
     */
    private function createGroup(string $address, string $name, array $members, string $accessPolicy): void
    {
        $domain = $this->domain();
        if ($domain->aliases > 0) {
            $count = $this->aliases->countAliasesForDomain($domain->domainName);
            if ($count >= $domain->aliases) {
                throw new InvalidInputException(
                    "Domain alias limit reached ({$count}/{$domain->aliases})",
                    'common.msg_alias_limit',
                    ['current' => $count, 'max' => $domain->aliases],
                );
            }
        }
        if (!$this->aliases->createAlias($address, $domain->domainName, $name, $members, $accessPolicy)) {
            throw new \RuntimeException("Creating the alias {$address} failed");
        }
    }

    /**
     * An alias has no rename, so the old alias is replaced by a new one with its policy.
     */
    private function renameGroup(string $old, string $new): void
    {
        $alias = $this->aliases->getAlias($old);
        if ($alias === null) {
            return;
        }
        $members = $this->aliases->getAliasMembers($old);
        if (!$this->aliases->deleteAlias($old)) {
            throw new \RuntimeException("Deleting the alias {$old} failed");
        }
        AccountSettingsService::deleteAccounts([$old]);
        $this->createGroup($new, $alias->name, $members, $alias->accessPolicy);
    }

    private function applyProfile(User $user, SourceAccount $source): void
    {
        foreach ($source->profile as $property => $value) {
            if (in_array($property, self::PROFILE_PROPERTIES, true)) {
                $user->$property = $value;
            }
        }
    }

    private function domain(): Domain
    {
        return $this->domains->getDomain($this->resource->domain)
            ?? throw new \RuntimeException("The target domain {$this->resource->domain} does not exist");
    }

    private static function localPart(string $address): string
    {
        return substr($address, 0, (int) strrpos($address, '@'));
    }
}
