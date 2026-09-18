<?php

declare(strict_types=1);

namespace App\Api;

use App\Repositories\RepositoryFactory;
use App\Utils\IredapdList;

class GreylistApiController
{
    public static function get(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = RepositoryFactory::getIredapdRepository();
        $settings = $repo->getGreylistSettings($account);
        $whitelisted = $repo->getWhitelistedSenders($account);

        ApiResponse::success([
            'account' => $account,
            'settings' => $settings,
            'whitelistedSenders' => $whitelisted,
        ]);
    }

    public static function update(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        $repo = RepositoryFactory::getIredapdRepository();

        $senders = null;
        if (isset($data['whitelistedSenders'])) {
            $raw = $data['whitelistedSenders'];
            if (!is_array($raw) || array_filter($raw, 'is_string') !== $raw) {
                ApiResponse::error('whitelistedSenders must be an array of strings');
                return;
            }
            try {
                $senders = IredapdList::greylistSenders($raw);
            } catch (\InvalidArgumentException $e) {
                ApiResponse::error("Invalid whitelisted sender: {$e->getMessage()}");
                return;
            }
        }

        if (isset($data['enabled'])) {
            $repo->setGreylistEnabled($account, (bool) $data['enabled']);
        }

        if ($senders !== null) {
            $repo->setWhitelistedSenders($account, $senders);
        }

        ApiResponse::success(['message' => 'Greylist settings updated']);
    }
}
