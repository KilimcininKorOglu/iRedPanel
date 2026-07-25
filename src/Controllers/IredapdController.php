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

class IredapdController
{
    public static function throttleView(TemplateEngine $tpl, string $account): void
    {
        Middleware::globalAdminRequired();
        self::requireEnabled();

        $repo = RepositoryFactory::getIredapdRepository();
        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            try {
                $kind = $_POST['kind'] ?? 'outbound';
                $period = (int) ($_POST['period'] ?? 3600);
                $maxMsgs = (int) ($_POST['maxMsgs'] ?? 0);
                $maxQuota = (int) ($_POST['maxQuota'] ?? 0);
                $msgSize = (int) ($_POST['msgSize'] ?? 0);

                $repo->setThrottleSettings($account, $kind, $period, $maxMsgs, $maxQuota, $msgSize);
                ActivityLogger::logUpdate('', $account, "Throttle settings updated for {$account}");
                $success = Translator::translate('throttle.msg_updated');
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        $throttleSettings = $repo->getThrottleSettings($account);

        $tpl->render('throttleView.php', [
            'account' => $account,
            'throttleSettings' => $throttleSettings,
            'success' => $success,
            'error' => $error,
        ]);
    }

    public static function greylistView(TemplateEngine $tpl, string $account): void
    {
        Middleware::globalAdminRequired();
        self::requireEnabled();

        $repo = RepositoryFactory::getIredapdRepository();
        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            try {
                $action = $_POST['action'] ?? '';

                if ($action === 'toggle') {
                    $enabled = isset($_POST['enabled']);
                    $repo->setGreylistEnabled($account, $enabled);
                    $status = $enabled ? 'enabled' : 'disabled';
                    ActivityLogger::logUpdate('', $account, "Greylisting {$status} for {$account}");
                    $success = Translator::translate($enabled ? 'greylist.msg_enabled' : 'greylist.msg_disabled');
                } elseif ($action === 'whitelist') {
                    $sendersRaw = $_POST['whitelistedSenders'] ?? '';
                    $senders = array_filter(array_map('trim', explode("\n", $sendersRaw)));
                    $repo->setWhitelistedSenders($account, $senders);
                    ActivityLogger::logUpdate('', $account, "Greylist whitelist updated for {$account}");
                    $success = Translator::translate('greylist.msg_whitelist_updated');
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        $greylistSettings = $repo->getGreylistSettings($account);
        $whitelistedSenders = $repo->getWhitelistedSenders($account);
        $greylistEnabled = false;
        foreach ($greylistSettings as $setting) {
            if (($setting['sender'] ?? '') === '@.' && ($setting['active'] ?? 0)) {
                $greylistEnabled = true;
                break;
            }
        }

        $tpl->render('greylistView.php', [
            'account' => $account,
            'greylistEnabled' => $greylistEnabled,
            'whitelistedSenders' => $whitelistedSenders,
            'success' => $success,
            'error' => $error,
        ]);
    }

    public static function greylistTracking(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        self::requireEnabled();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = Settings::getInstance()->paginationPerPage;

        $repo = RepositoryFactory::getIredapdRepository();
        $paginatedResult = $repo->getGreylistTrackingPaginated($page, $perPage);

        $tpl->render('greylistTracking.php', [
            'entries' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
        ]);
    }

    public static function wblistRdns(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        self::requireEnabled();

        $repo = RepositoryFactory::getIredapdRepository();
        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            try {
                $whitelists = array_filter(array_map('trim', explode("\n", $_POST['whitelists'] ?? '')));
                $blacklists = array_filter(array_map('trim', explode("\n", $_POST['blacklists'] ?? '')));
                $repo->setWblistRdns($whitelists, $blacklists);
                ActivityLogger::log('update', '', '', 'Updated rDNS white/blacklist');
                $success = Translator::translate('wblist.msg_rdns_updated');
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        $data = $repo->getWblistRdns();

        $tpl->render('wblistRdns.php', [
            'whitelists' => $data['whitelists'],
            'blacklists' => $data['blacklists'],
            'success' => $success,
            'error' => $error,
        ]);
    }

    public static function wblistSenderScore(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        self::requireEnabled();

        $repo = RepositoryFactory::getIredapdRepository();
        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            try {
                $ips = array_filter(array_map('trim', explode("\n", $_POST['ips'] ?? '')));
                $repo->setSenderScoreWhitelist($ips);
                ActivityLogger::log('update', '', '', 'Updated SenderScore whitelist');
                $success = Translator::translate('wblist.msg_senderscore_updated');
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        $ips = $repo->getSenderScoreWhitelist();

        $tpl->render('wblistSenderScore.php', [
            'ips' => $ips,
            'success' => $success,
            'error' => $error,
        ]);
    }

    private static function requireEnabled(): void
    {
        if (!Settings::getInstance()->iredapdEnabled) {
            http_response_code(403);
            echo 'iRedAPD integration is not enabled';
            exit;
        }
    }
}
