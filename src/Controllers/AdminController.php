<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Admin;
use App\Models\UserPassword;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\TemplateEngine;
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

        if (empty($selectedAdmins) || !is_array($selectedAdmins)) {
            header("Location: /admins");
            exit;
        }

        $adminRepo = RepositoryFactory::getAdminRepository();
        $currentEmail = $_SESSION['email'] ?? '';

        // Exclude current user from destructive bulk actions
        if ($action === 'delete' || $action === 'disable') {
            $selectedAdmins = array_filter($selectedAdmins, fn($u) => $u !== $currentEmail);

            // Prevent wiping all global admins
            $globalAdminCount = $adminRepo->countGlobalAdmins();
            $affectedGlobalAdmins = 0;
            foreach ($selectedAdmins as $adminUsername) {
                $admin = $adminRepo->getAdmin($adminUsername);
                if ($admin !== null && $admin->isGlobalAdmin) {
                    $affectedGlobalAdmins++;
                }
            }
            if ($affectedGlobalAdmins >= $globalAdminCount) {
                $_SESSION['adminError'] = 'Cannot ' . $action . ' all global admin accounts';
                header("Location: /admins");
                exit;
            }
        }

        foreach ($selectedAdmins as $adminUsername) {
            try {
                if ($action === 'enable') {
                    $adminRepo->enableDisableAdmin($adminUsername, true);
                } elseif ($action === 'disable') {
                    $adminRepo->enableDisableAdmin($adminUsername, false);
                } elseif ($action === 'delete') {
                    $adminRepo->deleteAdmin($adminUsername);
                }
            } catch (\Exception $e) {
                error_log("Bulk action '{$action}' failed for admin '{$adminUsername}': " . $e->getMessage());
            }
        }

        ActivityLogger::log($action, '', '', "Bulk {$action} on " . count($selectedAdmins) . " admins");
        header("Location: /admins");
        exit;
    }

    /**
     * Displays the admin creation form and handles creation.
     */
    public static function adminCreate(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $error = null;
        $validationErrors = [];
        $admin = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $admin = Admin::fromFormData($_POST);
                $password = $_POST['password'] ?? '';
                $passwordRepeat = $_POST['password_repeat'] ?? '';

                // Validate email
                if (empty($admin->username) || !filter_var($admin->username, FILTER_VALIDATE_EMAIL)) {
                    $validationErrors['username'] = Translator::translate('admin.msg_email_required');
                }

                // Validate password
                $validationErrors = array_merge($validationErrors, UserPassword::validate($password, $passwordRepeat));

                if (empty($validationErrors)) {
                    $repo = RepositoryFactory::getAdminRepository();

                    // Check for duplicate
                    if ($repo->getAdmin($admin->username) !== null) {
                        $validationErrors['username'] = Translator::translate('admin.msg_exists', ['username' => $admin->username]);
                    } else {
                        $passwordHash = PasswordUtils::generatePasswordHash($password);
                        $repo->createAdmin($admin, $passwordHash);
                        ActivityLogger::logCreate('', $admin->username, "Admin created: {$admin->username}");
                        header("Location: /admins");
                        exit;
                    }
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        $tpl->render('adminCreate.php', [
            'admin' => $admin,
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
                if ($editMode === 'general') {
                    $formAdmin = Admin::fromFormData($_POST);
                    $existingAdmin = $adminRepo->getAdmin($adminEmail);
                    $admin = new Admin(
                        username: $adminEmail,
                        name: $formAdmin->name,
                        active: $formAdmin->active,
                        isGlobalAdmin: $formAdmin->isGlobalAdmin,
                        isMailboxAdmin: $existingAdmin?->isMailboxAdmin ?? false,
                    );

                    // Prevent demoting or disabling the last global admin
                    if ($existingAdmin !== null && $existingAdmin->isGlobalAdmin) {
                        $wouldLoseGlobalAdmin = !$admin->isGlobalAdmin || !$admin->active;
                        if ($wouldLoseGlobalAdmin && $adminRepo->countGlobalAdmins() <= 1) {
                            $error = Translator::translate('admin.msg_cannot_remove_last_global');
                            $admin = $existingAdmin;
                        }
                    }

                    if ($error === null) {
                        $adminRepo->updateAdmin($admin);
                        ActivityLogger::logUpdate('', $adminEmail, "Admin updated: {$adminEmail}");
                        $success = Translator::translate('admin.msg_updated');
                    }
                } elseif ($editMode === 'password') {
                    $password = $_POST['password'] ?? '';
                    $passwordRepeat = $_POST['password_repeat'] ?? '';
                    $validationErrors = UserPassword::validate($password, $passwordRepeat);

                    if (empty($validationErrors)) {
                        $passwordHash = PasswordUtils::generatePasswordHash($password);
                        $adminRepo->updateAdminPassword($adminEmail, $passwordHash);
                        ActivityLogger::logUpdate('', $adminEmail, "Admin password changed: {$adminEmail}");
                        $success = Translator::translate('common.msg_password_updated');
                    }
                } elseif ($editMode === 'domains') {
                    $action = $_POST['action'] ?? '';
                    $domain = $_POST['domain'] ?? '';

                    if ($action === 'assign' && !empty($domain)) {
                        $adminRepo->assignDomainToAdmin($adminEmail, $domain);
                        ActivityLogger::logUpdate($domain, $adminEmail, "Domain assigned to admin: {$domain}");
                        $success = Translator::translate('admin.msg_domain_assigned', ['domain' => $domain]);
                    } elseif ($action === 'revoke' && !empty($domain)) {
                        $adminRepo->revokeDomainFromAdmin($adminEmail, $domain);
                        ActivityLogger::logUpdate($domain, $adminEmail, "Domain revoked from admin: {$domain}");
                        $success = Translator::translate('admin.msg_domain_revoked', ['domain' => $domain]);
                    }
                } elseif ($editMode === 'limits') {
                    CsrfProtection::validateToken();
                    $admin = $adminRepo->getAdmin($adminEmail);
                    if ($admin !== null) {
                        $admin->createMaxDomains = max(-1, (int) ($_POST['createMaxDomains'] ?? -1));
                        $admin->createMaxUsers = max(-1, (int) ($_POST['createMaxUsers'] ?? -1));
                        $admin->createMaxAliases = max(-1, (int) ($_POST['createMaxAliases'] ?? -1));
                        $admin->createMaxLists = max(-1, (int) ($_POST['createMaxLists'] ?? -1));
                        $admin->createMaxQuota = max(-1, (int) ($_POST['createMaxQuota'] ?? -1));
                        $admin->createNewDomains = isset($_POST['createNewDomains']);
                        $adminRepo->updateAdminSettings($adminEmail, $admin->toSettingsJson());
                        ActivityLogger::logUpdate('', $adminEmail, "Admin resource limits updated");
                        $success = Translator::translate('admin.msg_limits_updated');
                    }
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
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
     * Handles admin deletion (POST only).
     */
    public static function adminDelete(TemplateEngine $tpl, string $adminEmail): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        // Prevent self-deletion
        if ($adminEmail === ($_SESSION['email'] ?? '')) {
            $_SESSION['adminError'] = 'Cannot delete your own admin account';
            header("Location: /admins");
            exit;
        }

        // Prevent last global admin deletion
        $adminRepo = RepositoryFactory::getAdminRepository();
        $targetAdmin = $adminRepo->getAdmin($adminEmail);
        if ($targetAdmin !== null && $targetAdmin->isGlobalAdmin && $adminRepo->countGlobalAdmins() <= 1) {
            $_SESSION['adminError'] = 'Cannot delete the last global admin account';
            header("Location: /admins");
            exit;
        }

        try {
            $adminRepo->deleteAdmin($adminEmail);
            header("Location: /admins");
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            $tpl->render('page404.php');
        }
    }
}
