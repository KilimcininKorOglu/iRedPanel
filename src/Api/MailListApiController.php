<?php

declare(strict_types=1);

namespace App\Api;

use App\Models\MailList;
use App\Models\Settings;
use App\Repositories\MailListRepositoryInterface;
use App\Repositories\RepositoryFactory;

/**
 * Mail lists of the LDAP backend: group accounts whose members the admin
 * manages. A SQL backend has no such account type and answers 400.
 */
class MailListApiController
{
    public static function list(): void
    {
        $repo = self::repo();
        if ($repo === null) {
            return;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(500, max(1, (int) ($_GET['perPage'] ?? Settings::getInstance()->paginationPerPage)));
        try {
            $result = $repo->getMailListsPaginated(self::scope(), $page, $perPage, ApiInput::disabledOnly());
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        ApiResponse::paginated($result, static fn (MailList $list): array => $list->toArray());
    }

    public static function get(string $address): void
    {
        $repo = self::repo($address);
        if ($repo === null) {
            return;
        }

        $list = $repo->getMailList($address);
        if ($list === null) {
            ApiResponse::error('Mail list not found', 404);
            return;
        }

        ApiResponse::success($list->toArray());
    }

    public static function create(): void
    {
        $repo = self::repo();
        if ($repo === null) {
            return;
        }
        ApiMiddleware::requireWriteAccess();

        $data = ApiMiddleware::getJsonBody();
        $address = strtolower(trim((string) ($data['address'] ?? '')));
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            ApiResponse::error('address must be an email address');
            return;
        }
        ApiMiddleware::requireDomainAccess(explode('@', $address, 2)[1]);

        if ($repo->isAddressInUse($address)) {
            ApiResponse::error('Address already in use');
            return;
        }

        try {
            $repo->createMailList(MailList::fromFormData($address, $data + ['active' => true]));
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        ApiResponse::created(['address' => $address]);
    }

    /**
     * Writes the fields that the body carries; the other fields keep their value.
     */
    public static function update(string $address): void
    {
        $repo = self::repo($address);
        if ($repo === null) {
            return;
        }
        ApiMiddleware::requireWriteAccess();

        $stored = $repo->getMailList($address);
        if ($stored === null) {
            ApiResponse::error('Mail list not found', 404);
            return;
        }

        $data = ApiMiddleware::getJsonBody();
        try {
            $members = ApiInput::listChange(
                $data,
                ['members', 'addMembers', 'removeMembers'],
                $stored->members,
                static fn (mixed $value, string $field): array => ApiInput::addresses($value, $field),
            ) ?? $stored->members;
            $repo->updateMailList(MailList::fromFormData($address, [
                'members' => $members,
            ] + $data + $stored->toArray()));
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        ApiResponse::success(['message' => 'Mail list updated']);
    }

    public static function delete(string $address): void
    {
        $repo = self::repo($address);
        if ($repo === null) {
            return;
        }
        ApiMiddleware::requireWriteAccess();

        if ($repo->getMailList($address) === null) {
            ApiResponse::error('Mail list not found', 404);
            return;
        }

        $repo->deleteMailList($address);
        \App\Services\AccountSettingsService::deleteAccounts([$address]);
        ApiResponse::deleted();
    }

    /**
     * The domains of a domain key, or null for a global key.
     *
     * @return ?list<string>
     */
    private static function scope(): ?array
    {
        $key = ApiMiddleware::getCurrentKey();
        if ($key === null || $key->isGlobal()) {
            return null;
        }

        return $key->domainList();
    }

    /**
     * The repository, or null when the request already got its answer: a SQL
     * backend has no mail lists, and a domain key reaches its own domains only.
     */
    private static function repo(?string $address = null): ?MailListRepositoryInterface
    {
        $repo = RepositoryFactory::getMailListRepository();
        if (!$repo->isAvailable()) {
            ApiResponse::error('Mail lists need the LDAP backend');
            return null;
        }
        if ($address !== null) {
            ApiMiddleware::requireDomainAccess(explode('@', $address, 2)[1] ?? '');
        }

        return $repo;
    }
}
