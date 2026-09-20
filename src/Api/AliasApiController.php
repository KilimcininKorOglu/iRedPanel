<?php

declare(strict_types=1);

namespace App\Api;

use App\Exceptions\InvalidInputException;
use App\Models\Alias;
use App\Repositories\RepositoryFactory;
use App\Services\AccountRenameService;
use App\Services\AccountSettingsService;
use App\Services\Replication\ReplicatedAccountGuard;
use App\Utils\AddressList;

class AliasApiController
{
    public static function list(): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = RepositoryFactory::getAliasRepository();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['perPage'] ?? 50);
        $domain = $_GET['domain'] ?? null;

        try {
            $activeOnly = ApiInput::disabledOnly();
            $emailOnly = ApiInput::queryFlag('emailOnly');
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        $result = $repo->getAliasesPaginated($page, $perPage, $domain, $activeOnly);
        ApiResponse::paginated($result, fn(Alias $a) => $emailOnly ? $a->address : [
            'address' => $a->address,
            'domain' => $a->domain,
            'name' => $a->name,
            'accessPolicy' => $a->accessPolicy,
            'active' => $a->active,
        ]);
    }

    public static function get(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = RepositoryFactory::getAliasRepository();
        $alias = $repo->getAlias($address);
        if ($alias === null) {
            ApiResponse::error('Alias not found', 404);
            return;
        }

        $members = $repo->getAliasMembers($address);
        $moderators = $repo->getModerators($address);

        ApiResponse::success([
            'address' => $alias->address,
            'domain' => $alias->domain,
            'name' => $alias->name,
            'accessPolicy' => $alias->accessPolicy,
            'active' => $alias->active,
            'members' => $members,
            'moderators' => $moderators,
        ]);
    }

    public static function create(): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        $address = strtolower(trim((string) ($data['address'] ?? '')));
        $domain = strtolower(trim((string) ($data['domain'] ?? '')));

        $failure = self::newAddressError($address, $domain);
        if ($failure !== null) {
            ApiResponse::error($failure[0], $failure[1]);
            return;
        }

        try {
            $members = self::members($data['members'] ?? []);
            $accessPolicy = Alias::validAccessPolicy($data['accessPolicy'] ?? 'public');
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        RepositoryFactory::getAliasRepository()->createAlias($address, $domain, $data['name'] ?? '', $members, $accessPolicy);
        ApiResponse::created(['address' => $address]);
    }

    /**
     * The format checks of a new alias address, before any backend read.
     *
     * @return array{0: string, 1: int}|null the error message and HTTP status, or null when the format is valid
     */
    private static function addressFormatError(string $address, string $domain): ?array
    {
        if ($address === '' || $domain === '') {
            return ['address and domain are required', 400];
        }
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return ['Invalid address', 400];
        }
        if (!str_ends_with($address, '@' . $domain)) {
            return ['address must be in domain', 400];
        }

        return null;
    }

    /**
     * Checks the address of a new alias or mailing list.
     * Both count against the domain alias limit.
     *
     * @param string $address lowercased address
     * @param string $domain lowercased domain
     * @return array{0: string, 1: int}|null the error message and HTTP status, or null when the address is valid
     */
    public static function newAddressError(string $address, string $domain): ?array
    {
        $formatError = self::addressFormatError($address, $domain);
        if ($formatError !== null) {
            return $formatError;
        }

        $domainObj = RepositoryFactory::getDomainRepository()->getDomain($domain);
        if ($domainObj === null) {
            return ['Domain not found', 404];
        }

        $repo = RepositoryFactory::getAliasRepository();
        if ($repo->isAddressInUse($address)) {
            return ['Address already in use', 409];
        }
        $aliasCount = $domainObj->aliases > 0 ? $repo->countAliasesForDomain($domain) : 0;
        if ($domainObj->aliases > 0 && $aliasCount >= $domainObj->aliases) {
            return ["Domain alias limit reached ({$aliasCount}/{$domainObj->aliases})", 403];
        }

        return null;
    }

    public static function update(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getAliasRepository();
        $alias = $repo->getAlias($address);
        if ($alias === null) {
            ApiResponse::error('Alias not found', 404);
            return;
        }

        $data = ApiMiddleware::getJsonBody();
        $storedMembers = $repo->getAliasMembers($address);
        try {
            $members = ApiInput::listChange(
                $data,
                ['members', 'addMembers', 'removeMembers'],
                $storedMembers,
                self::members(...),
            ) ?? $storedMembers;
            $accessPolicy = Alias::validAccessPolicy($data['accessPolicy'] ?? $alias->accessPolicy);
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        $name = (string) ($data['name'] ?? $alias->name);
        $locked = ReplicatedAccountGuard::changedGroupFields($address, $name, $members, $alias->name, $storedMembers);
        if ($locked !== []) {
            ApiResponse::error('Managed by the directory, read-only fields: ' . implode(', ', $locked), 409);
            return;
        }

        $repo->updateAlias(
            $address,
            $name,
            $members,
            $accessPolicy,
            $data['active'] ?? $alias->active,
        );
        ApiResponse::success(['message' => 'Alias updated']);
    }

    /**
     * Moves the alias to the address in the body field newAddress, in the same domain.
     */
    public static function rename(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        if (RepositoryFactory::getAliasRepository()->getAlias($address) === null) {
            ApiResponse::error('Alias not found', 404);
            return;
        }

        $newAddress = ApiMiddleware::getJsonBody()['newAddress'] ?? null;
        if (!is_string($newAddress)) {
            ApiResponse::error('newAddress is required');
            return;
        }
        try {
            $renamed = AccountRenameService::renameAlias($address, $newAddress);
        } catch (InvalidInputException $e) {
            ApiResponse::invalidInput($e);
            return;
        }
        ApiResponse::success(['message' => 'Alias renamed', 'address' => $renamed]);
    }

    /**
     * @return list<string> the members, lowercased and without duplicates
     * @throws \InvalidArgumentException with the message for the API client
     */
    private static function members(mixed $input, string $field = 'members'): array
    {
        if (!is_array($input) || array_filter($input, is_string(...)) !== $input) {
            throw new \InvalidArgumentException("{$field} must be an array of email addresses");
        }
        try {
            return AddressList::parse(implode("\n", $input));
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('Invalid member: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public static function delete(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getAliasRepository();
        if ($repo->getAlias($address) === null) {
            ApiResponse::error('Alias not found', 404);
            return;
        }

        $repo->deleteAlias($address);
        AccountSettingsService::deleteAccounts([$address]);
        ApiResponse::deleted();
    }
}
