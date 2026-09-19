<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\TemplateEngine;
use App\Utils\AmavisdAddress;

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
                $action = $_POST['action'] ?? '';
                $direction = $_POST['direction'] ?? 'inbound';

                if ($action === 'add') {
                    $sender = trim((string) ($_POST['sender'] ?? ''));
                    $wb = ($_POST['wb'] ?? 'W') === 'B' ? 'B' : 'W';
                    self::assertValidEntry($account, $sender);

                    if ($direction === 'outbound') {
                        $repo->addOutboundEntry($account, $sender, $wb);
                    } else {
                        $repo->addInboundEntry($account, $sender, $wb);
                    }
                    ActivityLogger::logUpdate('', $account, "Added {$wb} entry for {$sender} ({$direction})");
                    $success = Translator::translate('wblist.msg_entry_added');
                } elseif ($action === 'remove') {
                    $sender = (string) ($_POST['sender'] ?? '');

                    $removed = $direction === 'outbound'
                        ? $repo->removeOutboundEntry($account, $sender)
                        : $repo->removeInboundEntry($account, $sender);
                    if (!$removed) {
                        throw new \RuntimeException(BaseController::itemError($sender, BaseController::itemNotFound()));
                    }
                    ActivityLogger::logDelete('', $account, "Removed entry for {$sender} ({$direction})");
                    $success = Translator::translate('wblist.msg_entry_removed');
                }
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $inboundList = $repo->getInboundList($account);
        $outboundList = $repo->getOutboundList($account);

        $tpl->render('whiteBlacklistList.php', [
            'account' => $account,
            'inboundList' => $inboundList,
            'outboundList' => $outboundList,
            'success' => $success,
            'error' => $error,
        ]);
    }

    /**
     * Rejects addresses that Amavisd and iRedAPD cannot match, as iRedAdmin does.
     */
    private static function assertValidEntry(string $account, string $sender): void
    {
        if (!AmavisdAddress::isValidAccount($account)) {
            throw new \RuntimeException(Translator::translate('common.msg_invalid_address', ['address' => $account]));
        }
        if (!AmavisdAddress::isValidWblistAddress($sender)) {
            throw new \RuntimeException(Translator::translate('common.msg_invalid_address', ['address' => $sender]));
        }
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
