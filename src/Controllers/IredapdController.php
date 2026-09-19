<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Settings;
use App\Models\ThrottleSetting;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\TemplateEngine;
use App\Utils\IredapdAccount;
use App\Utils\IredapdList;

class IredapdController
{
    /** Form label of each ThrottleSetting field, for validation messages. */
    private const THROTTLE_FIELD_LABELS = [
        'kind' => 'throttle.kind',
        'period' => 'throttle.period_seconds',
        'maxMsgs' => 'throttle.max_messages',
        'maxQuota' => 'throttle.max_quota_bytes',
        'msgSize' => 'throttle.max_message_size',
    ];

    public static function throttleView(TemplateEngine $tpl, string $account): void
    {
        Middleware::globalAdminRequired();
        self::requireEnabled();
        if (!IredapdAccount::isThrottleAccount($account)) {
            BaseController::page404($tpl);
            return;
        }

        $repo = RepositoryFactory::getIredapdRepository();
        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            try {
                $setting = ThrottleSetting::fromInput($_POST);
                $repo->setThrottleSettings(
                    $account,
                    $setting->kind,
                    $setting->period,
                    $setting->maxMsgs,
                    $setting->maxQuota,
                    $setting->msgSize,
                );
                ActivityLogger::logUpdate('', $account, "Throttle settings updated for {$account}");
                $success = Translator::translate('throttle.msg_updated');
            } catch (\InvalidArgumentException $e) {
                $error = self::throttleFieldError($e);
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
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
        if (!IredapdAccount::isValid($account)) {
            BaseController::page404($tpl);
            return;
        }

        $repo = RepositoryFactory::getIredapdRepository();
        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            try {
                $action = $_POST['action'] ?? '';

                if ($action === 'state') {
                    $success = self::applyGreylistState($account, (string) ($_POST['state'] ?? ''));
                } elseif ($action === 'whitelist') {
                    $senders = IredapdList::greylistSenders(explode("\n", $_POST['whitelistedSenders'] ?? ''));
                    $repo->setWhitelistedSenders($account, $senders);
                    ActivityLogger::logUpdate('', $account, "Greylist whitelist updated for {$account}");
                    $success = Translator::translate('greylist.msg_whitelist_updated');
                }
            } catch (\InvalidArgumentException $e) {
                $error = Translator::translate('common.msg_invalid_address', ['address' => $e->getMessage()]);
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $whitelistedSenders = $repo->getWhitelistedSenders($account);

        $tpl->render('greylistView.php', [
            'account' => $account,
            'greylistState' => self::greylistState($account, $repo->getGreylistSettings($account)),
            'canInherit' => $account !== '@.',
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
                $whitelists = IredapdList::rdnsNames(explode("\n", $_POST['whitelists'] ?? ''));
                $blacklists = IredapdList::rdnsNames(explode("\n", $_POST['blacklists'] ?? ''));
                $inBoth = array_intersect($whitelists, $blacklists);
                if ($inBoth !== []) {
                    throw new \DomainException(reset($inBoth));
                }
                $repo->setWblistRdns($whitelists, $blacklists);
                ActivityLogger::log('update', '', '', 'Updated rDNS white/blacklist');
                $success = Translator::translate('wblist.msg_rdns_updated');
            } catch (\InvalidArgumentException $e) {
                $error = Translator::translate('wblist.msg_invalid_rdns', ['name' => $e->getMessage()]);
            } catch (\DomainException $e) {
                $error = Translator::translate('wblist.msg_rdns_in_both', ['name' => $e->getMessage()]);
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
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
                $repo->setSenderScoreWhitelist(IredapdList::ipAddresses(explode("\n", $_POST['ips'] ?? '')));
                ActivityLogger::log('update', '', '', 'Updated SenderScore whitelist');
                $success = Translator::translate('wblist.msg_senderscore_updated');
            } catch (\InvalidArgumentException $e) {
                $error = Translator::translate('wblist.msg_invalid_ip', ['ip' => $e->getMessage()]);
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $ips = $repo->getSenderScoreWhitelist();

        $tpl->render('wblistSenderScore.php', [
            'ips' => $ips,
            'success' => $success,
            'error' => $error,
        ]);
    }

    /**
     * The account's own greylisting state: 'enabled', 'disabled', or
     * 'inherit' when iRedAPD applies the domain or global setting.
     *
     * @param array<int, array<string, mixed>> $settings rows of getGreylistSettings()
     */
    private static function greylistState(string $account, array $settings): string
    {
        foreach ($settings as $setting) {
            if ($setting['sender'] === '@.') {
                return $setting['active'] ? 'enabled' : 'disabled';
            }
        }
        // Without any matching row iRedAPD does not greylist.
        return $account === '@.' ? 'disabled' : 'inherit';
    }

    /**
     * Stores the greylisting state of an account and returns the success
     * message. 'inherit' removes the account's own row; the global '@.'
     * account has nothing to inherit from.
     */
    private static function applyGreylistState(string $account, string $state): string
    {
        $repo = RepositoryFactory::getIredapdRepository();
        if ($state === 'inherit' && $account !== '@.') {
            $repo->removeGreylistSetting($account);
            ActivityLogger::logUpdate('', $account, "Greylisting setting removed for {$account}");
            return Translator::translate('greylist.msg_inherited');
        }
        if ($state !== 'enabled' && $state !== 'disabled') {
            throw new \UnexpectedValueException("Invalid greylisting state: {$state}");
        }
        $repo->setGreylistEnabled($account, $state === 'enabled');
        ActivityLogger::logUpdate('', $account, "Greylisting {$state} for {$account}");
        return Translator::translate($state === 'enabled' ? 'greylist.msg_enabled' : 'greylist.msg_disabled');
    }

    /**
     * Names the form field that ThrottleSetting rejected. Any other invalid
     * argument is a failure the admin cannot fix in the form.
     */
    private static function throttleFieldError(\InvalidArgumentException $e): string
    {
        $labelKey = self::THROTTLE_FIELD_LABELS[$e->getMessage()] ?? null;
        if ($labelKey === null) {
            return BaseController::errorMessage($e);
        }
        return Translator::translate('throttle.msg_invalid_value', ['field' => Translator::translate($labelKey)]);
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
