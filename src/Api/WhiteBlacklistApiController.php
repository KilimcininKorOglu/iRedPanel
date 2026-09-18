<?php

declare(strict_types=1);

namespace App\Api;

use App\Repositories\RepositoryFactory;
use App\Utils\AmavisdAddress;

class WhiteBlacklistApiController
{
    public static function get(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = RepositoryFactory::getWhiteBlacklistRepository();
        ApiResponse::success([
            'inbound' => $repo->getInboundList($account),
            'outbound' => $repo->getOutboundList($account),
        ]);
    }

    public static function update(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        $repo = RepositoryFactory::getWhiteBlacklistRepository();

        $sender = $data['sender'] ?? '';
        $wb = $data['wb'] ?? 'W';
        $direction = $data['direction'] ?? 'inbound';

        $invalid = self::invalidField($account, $sender, $wb);
        if ($invalid !== null) {
            ApiResponse::error("Invalid {$invalid}");
            return;
        }

        if ($direction === 'outbound') {
            $repo->addOutboundEntry($account, $sender, $wb);
        } else {
            $repo->addInboundEntry($account, $sender, $wb);
        }

        ApiResponse::success(['message' => 'Entry added']);
    }

    public static function delete(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        $repo = RepositoryFactory::getWhiteBlacklistRepository();

        $sender = $data['sender'] ?? '';
        $direction = $data['direction'] ?? 'inbound';

        if ($sender === '') {
            ApiResponse::error('sender is required');
            return;
        }

        if ($direction === 'outbound') {
            $repo->removeOutboundEntry($account, $sender);
        } else {
            $repo->removeInboundEntry($account, $sender);
        }

        ApiResponse::deleted();
    }

    /**
     * Returns the name of the first field that Amavisd cannot use, or null.
     */
    private static function invalidField(string $account, mixed $sender, mixed $wb): ?string
    {
        return match (true) {
            !AmavisdAddress::isValidAccount($account) => 'account',
            !is_string($sender) || !AmavisdAddress::isValidWblistAddress($sender) => 'sender',
            !in_array($wb, ['W', 'B'], true) => 'wb (use W or B)',
            default => null,
        };
    }
}
