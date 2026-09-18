<?php

declare(strict_types=1);

namespace App\Api;

use App\Models\ThrottleSetting;
use App\Repositories\RepositoryFactory;

class ThrottleApiController
{
    public static function get(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        $settings = RepositoryFactory::getIredapdRepository()->getThrottleSettings($account);
        ApiResponse::success(['account' => $account, 'settings' => $settings]);
    }

    public static function update(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        try {
            $setting = ThrottleSetting::fromInput($data);
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error("Invalid {$e->getMessage()}");
            return;
        }

        RepositoryFactory::getIredapdRepository()->setThrottleSettings(
            $account,
            $setting->kind,
            $setting->period,
            $setting->maxMsgs,
            $setting->maxQuota,
            $setting->msgSize,
        );

        ApiResponse::success(['message' => 'Throttle settings updated']);
    }
}
