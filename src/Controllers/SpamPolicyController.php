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
                    $policy = SpamPolicy::fromFormData($_POST);
                    $repo->createOrUpdatePolicy($account, $policy);
                    ActivityLogger::logUpdate('', $account, "Spam policy updated for {$account}");
                    $success = Translator::translate('spampolicy.msg_updated');
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
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

    private static function requireEnabled(): void
    {
        if (!Settings::getInstance()->amavisdEnabled) {
            http_response_code(403);
            echo 'Amavisd integration is not enabled';
            exit;
        }
    }
}
