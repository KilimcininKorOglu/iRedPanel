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
                    $sender = trim($_POST['sender'] ?? '');
                    $wb = $_POST['wb'] ?? 'W';

                    if ($sender === '') {
                        throw new \RuntimeException('Email address is required');
                    }

                    if ($direction === 'outbound') {
                        $repo->addOutboundEntry($account, $sender, $wb);
                    } else {
                        $repo->addInboundEntry($account, $sender, $wb);
                    }
                    ActivityLogger::logUpdate('', $account, "Added {$wb} entry for {$sender} ({$direction})");
                    $success = Translator::translate('wblist.msg_entry_added');
                } elseif ($action === 'remove') {
                    $sender = $_POST['sender'] ?? '';

                    if ($direction === 'outbound') {
                        $repo->removeOutboundEntry($account, $sender);
                    } else {
                        $repo->removeInboundEntry($account, $sender);
                    }
                    ActivityLogger::logDelete('', $account, "Removed entry for {$sender} ({$direction})");
                    $success = Translator::translate('wblist.msg_entry_removed');
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
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

    private static function requireEnabled(): void
    {
        if (!Settings::getInstance()->amavisdEnabled) {
            http_response_code(403);
            echo 'Amavisd integration is not enabled';
            exit;
        }
    }
}
