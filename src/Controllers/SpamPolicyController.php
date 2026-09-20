<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Settings;
use App\Models\SpamPolicy;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\TemplateEngine;
use App\Utils\AmavisdAddress;

class SpamPolicyController
{
    public static function globalPolicy(TemplateEngine $tpl): void
    {
        self::accountPolicy($tpl, '@.');
    }

    public static function updateGlobalPolicy(TemplateEngine $tpl): void
    {
        self::accountPolicy($tpl, '@.');
    }

    public static function accountPolicy(TemplateEngine $tpl, string $account): void
    {
        Middleware::globalAdminRequired();
        self::requireEnabled();

        $repo = RepositoryFactory::getSpamPolicyRepository();
        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();

            try {
                $action = $_POST['action'] ?? 'save';

                if ($action === 'delete') {
                    $repo->deletePolicy($account);
                    ActivityLogger::log('delete', '', '', "Deleted spam policy for {$account}");
                    $success = Translator::translate('spampolicy.msg_deleted');
                } else {
                    if (!AmavisdAddress::isValidAccount($account)) {
                        throw new \RuntimeException(Translator::translate('common.msg_invalid_address', ['address' => $account]));
                    }
                    $policy = SpamPolicy::fromFormData($_POST);
                    $repo->createOrUpdatePolicy($account, $policy);
                    ActivityLogger::logUpdate('', $account, "Spam policy updated for {$account}");
                    $success = Translator::translate('spampolicy.msg_updated');
                }
            } catch (\InvalidArgumentException $e) {
                $error = self::levelError($e);
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $policy = $repo->getPolicy($account);
        $policies = $repo->listPolicies();

        $tpl->render('spamPolicyView.php', [
            'account' => $account,
            'policy' => $policy,
            'policies' => $policies,
            'success' => $success,
            'error' => $error,
        ]);
    }

    private const LEVEL_LABELS = [
        'spamTagLevel' => 'spampolicy.tag_level',
        'spamTag2Level' => 'spampolicy.tag2_level',
        'spamKillLevel' => 'spampolicy.kill_level',
    ];

    /** The fields whose value the form validates, with the label of the message. */
    private const VALUE_LABELS = [
        'spamQuarantine' => 'spampolicy.quarantine_spam',
        'virusQuarantine' => 'spampolicy.quarantine_virus',
        'bannedQuarantine' => 'spampolicy.quarantine_banned',
        'badHeaderQuarantine' => 'spampolicy.quarantine_bad_header',
        'bannedRulenames' => 'spampolicy.banned_rulenames',
    ];

    /**
     * Names the field that SpamPolicy::fromFormData() rejected.
     */
    public static function levelError(\InvalidArgumentException $e): string
    {
        $field = $e->getMessage();
        if (isset(self::LEVEL_LABELS[$field])) {
            return Translator::translate('spampolicy.msg_invalid_level', ['field' => Translator::translate(self::LEVEL_LABELS[$field])]);
        }
        if (isset(self::VALUE_LABELS[$field])) {
            return Translator::translate('spampolicy.msg_invalid_value', ['field' => Translator::translate(self::VALUE_LABELS[$field])]);
        }

        return BaseController::errorMessage($e);
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
