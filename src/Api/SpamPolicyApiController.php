<?php

declare(strict_types=1);

namespace App\Api;

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
        ApiResponse::success((array) $policy);
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
        $policy = SpamPolicy::fromFormData($data);
        RepositoryFactory::getSpamPolicyRepository()->createOrUpdatePolicy($account, $policy);
        ApiResponse::success(['message' => 'Spam policy updated']);
    }
}
