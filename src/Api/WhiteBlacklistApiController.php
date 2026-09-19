<?php

declare(strict_types=1);

namespace App\Api;

use App\Repositories\RepositoryFactory;
use App\Services\WhiteBlacklistService;

class WhiteBlacklistApiController
{
    /**
     * Returns the entries of the account. `?wb=W` or `?wb=B` returns only that kind.
     */
    public static function get(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        try {
            $wb = WhiteBlacklistService::filterKind($_GET['wb'] ?? null);
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        $repo = RepositoryFactory::getWhiteBlacklistRepository();
        ApiResponse::success([
            'inbound' => WhiteBlacklistService::filtered($repo->getInboundList($account), $wb),
            'outbound' => WhiteBlacklistService::filtered($repo->getOutboundList($account), $wb),
        ]);
    }

    /**
     * Adds one address in `sender` or several in `senders`.
     */
    public static function update(string $account): void
    {
        self::change(
            static fn (array $data): int => WhiteBlacklistService::add(
                $account,
                self::senders($data),
                WhiteBlacklistService::kind($data['wb'] ?? 'W'),
                self::direction($data),
            ),
            'Entries added',
        );
    }

    /**
     * Removes the addresses in `sender` or `senders`, or every entry of the
     * direction with `{"all": true}`, which `wb` limits to one kind.
     */
    public static function delete(string $account): void
    {
        self::change(
            static function (array $data) use ($account): int {
                $direction = self::direction($data);

                return ApiInput::bool($data, 'all', false)
                    ? WhiteBlacklistService::removeAll($account, $direction, WhiteBlacklistService::filterKind($data['wb'] ?? null))
                    : WhiteBlacklistService::remove($account, self::senders($data), $direction);
            },
            'Entries removed',
            'Entry not found',
        );
    }

    /**
     * Runs one write and answers with the number of entries that it changed.
     *
     * @param \Closure(array): int $run
     * @param ?string $emptyError the 404 message when the write changed nothing
     */
    private static function change(\Closure $run, string $message, ?string $emptyError = null): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();

        try {
            $count = $run(ApiMiddleware::getJsonBody());
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        if ($count === 0 && $emptyError !== null) {
            ApiResponse::error($emptyError, 404);
            return;
        }

        ApiResponse::success(['message' => $message, 'count' => $count]);
    }

    /**
     * @return list<string> the addresses of `senders`, or the single `sender`
     * @throws \InvalidArgumentException when neither field holds addresses
     */
    private static function senders(array $data): array
    {
        if (array_key_exists('senders', $data)) {
            return ApiInput::strings($data['senders'], 'senders');
        }
        $sender = $data['sender'] ?? '';
        if (!is_string($sender) || trim($sender) === '') {
            throw new \InvalidArgumentException('sender or senders is required');
        }

        return [$sender];
    }

    /**
     * @throws \InvalidArgumentException for a direction that Amavisd does not have
     */
    private static function direction(array $data): string
    {
        $direction = $data['direction'] ?? 'inbound';
        if (!in_array($direction, WhiteBlacklistService::DIRECTIONS, true)) {
            throw new \InvalidArgumentException('direction must be inbound or outbound');
        }

        return $direction;
    }
}
