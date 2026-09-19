<?php

declare(strict_types=1);

namespace App\Api;

use App\Exceptions\InvalidInputException;
use App\Models\SpamPolicy;
use App\Repositories\RepositoryFactory;
use App\Utils\AmavisdAddress;

class SpamPolicyApiController
{
    public static function get(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        $policy = RepositoryFactory::getSpamPolicyRepository()->getPolicy($account);
        if ($policy === null) {
            ApiResponse::error('No policy found for account', 404);
            return;
        }
        ApiResponse::success(['account' => $account, 'alwaysInsertXSpamHeaders' => $policy->alwaysInsertXSpamHeaders()] + $policy->toArray());
    }

    public static function update(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        if (!AmavisdAddress::isValidAccount($account)) {
            ApiResponse::error('Invalid account');
            return;
        }

        $repo = RepositoryFactory::getSpamPolicyRepository();
        $stored = $repo->getPolicy($account);
        try {
            // The body changes single fields; the stored policy carries the rest.
            $policy = SpamPolicy::fromFormData($data + ($stored?->toArray() ?? []));
        } catch (InvalidInputException $e) {
            ApiResponse::error($e->getMessage());
            return;
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error(self::fieldError($e->getMessage()));
            return;
        }
        $repo->createOrUpdatePolicy($account, $policy);
        ApiResponse::success(['message' => 'Spam policy updated']);
    }

    /**
     * The message for a field that SpamPolicy::fromFormData() rejected.
     */
    private static function fieldError(string $field): string
    {
        if (array_key_exists($field, SpamPolicy::QUARANTINE_COLUMNS)) {
            return "{$field} must be default, yes or no";
        }

        return "{$field} must be a number";
    }

    /**
     * Removes the policy of the account, which then follows the policy of its
     * domain or the global one again.
     */
    public static function delete(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getSpamPolicyRepository();
        if ($repo->getPolicy($account) === null) {
            ApiResponse::error('No policy found for account', 404);
            return;
        }

        $repo->deletePolicy($account);
        ApiResponse::deleted();
    }
}
