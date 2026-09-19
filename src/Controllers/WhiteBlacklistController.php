<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\Services\WhiteBlacklistService;
use App\TemplateEngine;

class WhiteBlacklistController
{
    public static function list(TemplateEngine $tpl): void
    {
        self::accountList($tpl, '@.');
    }

    public static function accountList(TemplateEngine $tpl, string $account): void
    {
        Middleware::globalAdminRequired();
        self::requireEnabled();

        $repo = RepositoryFactory::getWhiteBlacklistRepository();
        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();

            try {
                $success = self::applyAction($account, $_POST['action'] ?? '', self::postedDirection());
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $filter = self::filterKind();
        $tpl->render('whiteBlacklistList.php', [
            'account' => $account,
            'inboundList' => WhiteBlacklistService::filtered($repo->getInboundList($account), $filter),
            'outboundList' => WhiteBlacklistService::filtered($repo->getOutboundList($account), $filter),
            'wbFilter' => $filter ?? '',
            'success' => $success,
            'error' => $error,
        ]);
    }

    /**
     * Runs one POST action of the page.
     *
     * @return ?string the success message, or null when the action is unknown
     */
    private static function applyAction(string $account, string $action, string $direction): ?string
    {
        return match ($action) {
            'add' => self::addEntries($account, $direction),
            'remove' => self::removeEntries($account, $direction),
            'removeAll' => self::removeAllEntries($account, $direction),
            default => null,
        };
    }

    private static function addEntries(string $account, string $direction): string
    {
        $wb = WhiteBlacklistService::kind($_POST['wb'] ?? 'W');
        $senders = BaseController::postedAddresses('sender');
        $count = WhiteBlacklistService::add($account, $senders, $wb, $direction);
        ActivityLogger::logUpdate('', $account, "Added {$count} {$wb} entries ({$direction})");

        return Translator::translate('wblist.msg_entries_added', ['count' => $count]);
    }

    private static function removeEntries(string $account, string $direction): string
    {
        $count = WhiteBlacklistService::remove($account, [(string) ($_POST['sender'] ?? '')], $direction);
        if ($count === 0) {
            throw new \RuntimeException(BaseController::itemError((string) ($_POST['sender'] ?? ''), BaseController::itemNotFound()));
        }
        ActivityLogger::logDelete('', $account, "Removed entry for {$_POST['sender']} ({$direction})");

        return Translator::translate('wblist.msg_entry_removed');
    }

    private static function removeAllEntries(string $account, string $direction): string
    {
        $count = WhiteBlacklistService::removeAll($account, $direction, self::filterKind());
        ActivityLogger::logDelete('', $account, "Removed {$count} entries ({$direction})");

        return Translator::translate('wblist.msg_entries_removed', ['count' => $count]);
    }

    private static function postedDirection(): string
    {
        return ($_POST['direction'] ?? '') === 'outbound' ? 'outbound' : 'inbound';
    }

    /**
     * The white/blacklist kind that the page shows, from `?wb=W` or `?wb=B`.
     */
    private static function filterKind(): ?string
    {
        $value = $_GET['wb'] ?? null;

        return in_array($value, ['W', 'B'], true) ? $value : null;
    }

    private static function requireEnabled(): void
    {
        if (!Settings::getInstance()->amavisdEnabled) {
            http_response_code(403);
            echo 'Amavisd integration is not enabled';
            exit;
        }
    }
}
