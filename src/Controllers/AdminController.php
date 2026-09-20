<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Admin;
use App\Models\UserPassword;
use App\Repositories\AdminRepositoryInterface;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\TemplateEngine;
use App\Utils\FormValue;
use App\Utils\PasswordUtils;

class AdminController
{
    /**
     * Displays the admin list page.
     */
    public static function adminList(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $admins = RepositoryFactory::getAdminRepository()->getAdmins();

        $tpl->render('adminList.php', [
            'admins' => $admins,
        ]);
    }

    /**
     * Handles bulk admin operations (POST only).
     */
    public static function bulkAction(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $selectedAdmins = $_POST['selectedAdmins'] ?? [];
        $action = $_POST['action'] ?? '';

        if (!BaseController::isValidBulkRequest($selectedAdmins, $action)) {
            header("Location: /admins");
            exit;
        }

        $adminRepo = RepositoryFactory::getAdminRepository();
        if ($action !== 'enable') {
            $guardError = self::removalGuardError($adminRepo, array_filter($selectedAdmins, is_string(...)));
            if ($guardError !== null) {
                BaseController::flashError($guardError);
                header("Location: /admins");
                exit;
            }
        }

        $done = BaseController::runBulk($selectedAdmins, function (string $adminUsername) use ($adminRepo, $action): void {
            $adminRepo->getAdmin($adminUsername) ?? throw BaseController::itemNotFound();
            if ($action === 'delete') {
                $adminRepo->deleteAdmin($adminUsername);
            } else {
                $adminRepo->enableDisableAdmin($adminUsername, $action === 'enable');
            }
        });

        if ($done !== []) {
            ActivityLogger::log($action, '', '', "Bulk {$action} on " . count($done) . " admins");
        }
        header("Location: /admins");
        exit;
    }

    /**
     * Returns why the admins must not be deleted or disabled, or null when the
     * action is allowed: an admin must not remove itself, and at least one
     * global admin must stay.
     *
     * @param string[] $usernames
     */
    private static function removalGuardError(AdminRepositoryInterface $adminRepo, array $usernames): ?string
    {
        if (in_array($_SESSION['email'] ?? '', $usernames, true)) {
            return Translator::translate('admin.msg_cannot_remove_self');
        }

        $affectedGlobalAdmins = 0;
        foreach ($usernames as $username) {
            if ($adminRepo->getAdmin($username)?->isGlobalAdmin === true) {
                $affectedGlobalAdmins++;
            }
        }
        if ($affectedGlobalAdmins > 0 && $affectedGlobalAdmins >= $adminRepo->countGlobalAdmins()) {
            return Translator::translate('admin.msg_cannot_remove_last_global');
        }
        return null;
    }

    /**
     * Whether the domain is a mail domain or an alias domain of this server. A
     * standalone admin must not use such a domain: its address would
     * collide with a mailbox that has its own password.
     */
    public static function isHostedDomain(string $domain): bool
    {
        return RepositoryFactory::getDomainRepository()->getDomain($domain) !== null
            || RepositoryFactory::getDomainAliasRepository()->getAlias($domain) !== null;
    }

    /**
     * Displays the admin creation form and handles creation.
     */
    public static function adminCreate(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $error = null;
        $validationErrors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $admin = Admin::fromFormData($_POST);
                $admin->applyLimits($_POST);
                $password = $_POST['password'] ?? '';
                $passwordRepeat = $_POST['password_repeat'] ?? '';

                // Validate email
                if (empty($admin->username) || !filter_var($admin->username, FILTER_VALIDATE_EMAIL)) {
                    $validationErrors['username'] = Translator::translate('admin.msg_email_required');
                }

                // Validate password
                $validationErrors = array_merge($validationErrors, UserPassword::validateLocalized($password, $passwordRepeat));

                if ($validationErrors === []) {
                    $repo = RepositoryFactory::getAdminRepository();

                    // Check for duplicate
                    if ($repo->getAdmin($admin->username) !== null) {
                        $validationErrors['username'] = Translator::translate('admin.msg_exists', ['username' => $admin->username]);
                    } elseif (self::isHostedDomain(substr(strrchr($admin->username, '@'), 1))) {
                        $validationErrors['username'] = Translator::translate('admin.msg_hosted_domain', ['domain' => substr(strrchr($admin->username, '@'), 1)]);
                    } else {
                        $passwordHash = PasswordUtils::generatePasswordHash($password);
                        $repo->createAdmin($admin, $passwordHash);
                        ActivityLogger::logCreate('', $admin->username, "Admin created: {$admin->username}");
                        BaseController::flashCreated($admin->username);
                        header("Location: /admins");
                        exit;
                    }
                }
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $tpl->render('adminCreate.php', [
            'posted' => $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : null,
            'error' => $error,
            'validationErrors' => $validationErrors,
        ]);
    }

    /**
     * Displays and handles admin profile editing.
     */
    public static function adminView(TemplateEngine $tpl, string $adminEmail, string $editMode): void
    {
        Middleware::globalAdminRequired();

        if (!in_array($editMode, ['general', 'password', 'domains', 'limits'], true)) {
            http_response_code(404);
            $tpl->render('page404.php');
            return;
        }

        $adminRepo = RepositoryFactory::getAdminRepository();
        $error = null;
        $validationErrors = [];
        $success = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                [$error, $success, $validationErrors] = match ($editMode) {
                    'general' => self::saveGeneral($adminRepo, $adminEmail),
                    'password' => self::savePassword($adminRepo, $adminEmail),
                    'domains' => self::saveDomains($adminRepo, $adminEmail),
                    'limits' => self::saveLimits($adminRepo, $adminEmail),
                };
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $admin = $adminRepo->getAdmin($adminEmail);
        if ($admin === null) {
            http_response_code(404);
            $tpl->render('page404.php');
            return;
        }

        $managedDomains = $adminRepo->getManagedDomains($adminEmail);
        $allDomains = RepositoryFactory::getDomainRepository()->getDomains();
        $allDomainNames = array_map(fn($d) => $d['domainName'], $allDomains);

        $tpl->render('adminView.php', [
            'admin' => $admin,
            'editMode' => $editMode,
            'managedDomains' => $managedDomains,
            'allDomainNames' => $allDomainNames,
            'error' => $error,
            'validationErrors' => $validationErrors,
            'success' => $success,
        ]);
    }

    /**
     * Saves the General tab: name, language, status and global admin flag. The last
     * global admin must stay an active global admin.
     *
     * @return array{0: ?string, 1: ?string, 2: array<string, string>} error, success, validation errors
     */
    private static function saveGeneral(AdminRepositoryInterface $adminRepo, string $adminEmail): array
    {
        $existingAdmin = $adminRepo->getAdmin($adminEmail) ?? throw BaseController::itemNotFound();
        $formAdmin = Admin::fromFormData($_POST);
        $admin = clone $existingAdmin;
        $admin->name = $formAdmin->name;
        $admin->active = $formAdmin->active;
        $admin->isGlobalAdmin = $formAdmin->isGlobalAdmin;
        $admin->language = $formAdmin->language;
        $admin->expiredDate = $formAdmin->expiredDate;

        $losesGlobalAdmin = $existingAdmin->isGlobalAdmin && (!$admin->isGlobalAdmin || !$admin->active);
        if ($losesGlobalAdmin && $adminRepo->countGlobalAdmins() <= 1) {
            return [Translator::translate('admin.msg_cannot_remove_last_global'), null, []];
        }

        $adminRepo->updateAdmin($admin);
        ActivityLogger::logUpdate('', $adminEmail, "Admin updated: {$adminEmail}");

        return [null, Translator::translate('admin.msg_updated'), []];
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: array<string, string>} error, success, validation errors
     */
    private static function savePassword(AdminRepositoryInterface $adminRepo, string $adminEmail): array
    {
        $password = $_POST['password'] ?? '';
        $validationErrors = UserPassword::validateLocalized($password, $_POST['password_repeat'] ?? '');
        if ($validationErrors !== []) {
            return [null, null, $validationErrors];
        }

        $adminRepo->updateAdminPassword($adminEmail, PasswordUtils::generatePasswordHash($password));
        ActivityLogger::logUpdate('', $adminEmail, "Admin password changed: {$adminEmail}");

        return [null, Translator::translate('common.msg_password_updated'), []];
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: array<string, string>} error, success, validation errors
     */
    private static function saveDomains(AdminRepositoryInterface $adminRepo, string $adminEmail): array
    {
        $action = $_POST['action'] ?? '';
        $domain = FormValue::text($_POST, 'domain');
        if ($domain === '' || !in_array($action, ['assign', 'revoke'], true)) {
            return [null, null, []];
        }

        if ($action === 'assign') {
            $adminRepo->assignDomainToAdmin($adminEmail, $domain);
            ActivityLogger::logUpdate($domain, $adminEmail, "Domain assigned to admin: {$domain}");

            return [null, Translator::translate('admin.msg_domain_assigned', ['domain' => $domain]), []];
        }
        $adminRepo->revokeDomainFromAdmin($adminEmail, $domain);
        ActivityLogger::logUpdate($domain, $adminEmail, "Domain revoked from admin: {$domain}");

        return [null, Translator::translate('admin.msg_domain_revoked', ['domain' => $domain]), []];
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: array<string, string>} error, success, validation errors
     */
    private static function saveLimits(AdminRepositoryInterface $adminRepo, string $adminEmail): array
    {
        $admin = $adminRepo->getAdmin($adminEmail) ?? throw BaseController::itemNotFound();
        $admin->applyLimits($_POST);
        $adminRepo->updateAdminSettings($admin);
        ActivityLogger::logUpdate('', $adminEmail, "Admin resource limits updated");

        return [null, Translator::translate('admin.msg_limits_updated'), []];
    }

    /**
     * Handles admin deletion (POST only).
     */
    public static function adminDelete(TemplateEngine $tpl, string $adminEmail): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $adminRepo = RepositoryFactory::getAdminRepository();
        $guardError = self::removalGuardError($adminRepo, [$adminEmail]);
        if ($guardError !== null) {
            BaseController::flashError($guardError);
            header("Location: /admins");
            exit;
        }

        try {
            $adminRepo->getAdmin($adminEmail) ?? throw BaseController::itemNotFound();
            $adminRepo->deleteAdmin($adminEmail);
            ActivityLogger::logDelete('', '', "Admin deleted: {$adminEmail}");
            BaseController::flashDeleted($adminEmail);
        } catch (\Exception $e) {
            BaseController::flashItemError($adminEmail, $e);
        }
        header("Location: /admins");
        exit;
    }
}
