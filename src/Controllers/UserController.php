<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\Exceptions\InvalidInputException;
use App\I18n\Translator;
use App\Middleware;
use App\Models\DomainSettings;
use App\Models\ProfileToggles;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserPassword;
use App\Repositories\RepositoryFactory;
use App\Services\AccountRenameService;
use App\Services\AccountSettingsService;
use App\Services\ActivityLogger;
use App\Services\AdminLimits;
use App\Services\Replication\ReplicatedAccountGuard;
use App\TemplateEngine;
use App\Utils\PasswordUtils;

class UserController
{
    /**
     * Displays the user list page.
     */
    public static function userList(TemplateEngine $tpl, string $domain): void
    {
        Middleware::domainAdminRequired($domain);

        $settings = Settings::getInstance();
        $userRepo = RepositoryFactory::getUserRepository();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;
        $startsWith = $_GET['letter'] ?? null;
        $sortBy = $_GET['sort'] ?? 'uid';
        $sortDir = $_GET['dir'] ?? 'asc';
        $statusFilter = $_GET['status'] ?? null;
        $activeOnly = match ($statusFilter) {
            'active' => true,
            'disabled' => false,
            default => null,
        };

        $paginatedResult = $userRepo->getUsersPaginated($domain, $page, $perPage, $startsWith, $activeOnly, $sortBy, $sortDir);
        $usedQuotas = RepositoryFactory::getQuotaRepository()->getDomainUsedQuotas($domain);

        $tpl->render('userList.php', [
            'domain' => $domain,
            'users' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
            'supportsCreate' => $userRepo->supportsCreateUser(),
            'usedQuotas' => $usedQuotas,
            'currentLetter' => $startsWith,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * Displays the user detail/edit page.
     */
    public static function userView(TemplateEngine $tpl, string $domain, string $userUid, string $editMode): void
    {
        Middleware::domainAdminRequired($domain);

        // The global admin can close user pages for the domain admins of the domain.
        $openPages = ProfileToggles::openUserPages(self::domainSettings($domain), Middleware::isGlobalAdmin());
        if (in_array($editMode, ProfileToggles::USER_PROFILES, true) && !in_array($editMode, $openPages, true)) {
            if ($editMode === 'general' && $openPages !== []) {
                header('Location: /' . rawurlencode($domain) . '/users/' . rawurlencode($userUid) . '/' . $openPages[0]);
                exit;
            }
            http_response_code(403);
            echo 'Access denied: this page is disabled for domain admins';
            return;
        }

        self::userPage($tpl, $domain, $userUid, $editMode, $openPages);
    }

    /**
     * @param list<string> $openPages user pages that the admin may open
     */
    private static function userPage(TemplateEngine $tpl, string $domain, string $userUid, string $editMode, array $openPages): void
    {
        Middleware::domainAdminRequired($domain);

        if (!in_array($editMode, ['general', 'password', 'services', 'forwarding', 'aliases', 'bcc', 'relay'], true)) {
            http_response_code(404);
            $tpl->render('page404.php');
            return;
        }

        $userRepo = RepositoryFactory::getUserRepository();
        $error = $_SESSION['flash_error'] ?? null;
        $validationErrors = [];
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if ($editMode === 'general') {
                    $existingUser = $userRepo->getUser($domain, $userUid);
                    $user = User::fromFormData($_POST);
                    $user->uid = $userUid;
                    if ($existingUser !== null) {
                        $user->copyServicesFrom($existingUser);
                        ReplicatedAccountGuard::keepUserFields("{$userUid}@{$domain}", $user, $existingUser);
                    }

                    // Prevent privilege escalation: only global admins can change domainGlobalAdmin
                    if (!Middleware::isGlobalAdmin()) {
                        $user->domainGlobalAdmin = $existingUser ? $existingUser->domainGlobalAdmin : false;
                    }

                    $error = self::quotaChangeError($domain, $existingUser?->mailQuota ?? $user->mailQuota, $user->mailQuota) ?? $error;

                    if ($error === null) {
                        $userRepo->updateUser($domain, $user);
                        ActivityLogger::logUpdate($domain, $userUid, "User profile updated");
                        $success = Translator::translate('user.msg_info_updated');
                    }
                } elseif ($editMode === 'password') {
                    // Old password verification if enabled
                    $settings = Settings::getInstance();
                    if ($settings->requireOldPasswordOnChange) {
                        $oldPassword = $_POST['old_password'] ?? '';
                        if (!$userRepo->verifyUserPassword($domain, $userUid, $oldPassword)) {
                            $validationErrors['old_password'] = Translator::translate('user.msg_current_password_incorrect');
                        }
                    }

                    if (empty($validationErrors)) {
                        $password = $_POST['password'] ?? '';
                        $passwordRepeat = $_POST['password_repeat'] ?? '';
                        $validationErrors = UserPassword::validateLocalized($password, $passwordRepeat, self::domainSettings($domain));

                        if (empty($validationErrors)) {
                            $passwordHash = PasswordUtils::generatePasswordHash($password);
                            $userRepo->updateUserPassword($domain, $userUid, $passwordHash);
                            ActivityLogger::logUpdate($domain, $userUid, "Password changed");
                            $success = Translator::translate('common.msg_password_updated');
                        }
                    }
                } elseif ($editMode === 'services') {
                    $currentUser = $userRepo->getUser($domain, $userUid);
                    if ($currentUser !== null) {
                        $currentUser->enableSmtp = isset($_POST['enableSmtp']);
                        $currentUser->enableSmtpSecured = isset($_POST['enableSmtpSecured']);
                        $currentUser->enablePop3 = isset($_POST['enablePop3']);
                        $currentUser->enablePop3Secured = isset($_POST['enablePop3Secured']);
                        $currentUser->enableImap = isset($_POST['enableImap']);
                        $currentUser->enableImapSecured = isset($_POST['enableImapSecured']);
                        $currentUser->enableManagesieve = isset($_POST['enableManagesieve']);
                        $currentUser->enableManagesieveSecured = isset($_POST['enableManagesieveSecured']);
                        $currentUser->enableSogo = isset($_POST['enableSogo']);
                        $userRepo->updateUser($domain, $currentUser);
                        ActivityLogger::logUpdate($domain, $userUid, "Mail services updated");
                        $success = Translator::translate('user.msg_services_updated');
                    }
                } elseif ($editMode === 'forwarding') {
                    $email = "{$userUid}@{$domain}";
                    $forwardingRepo = RepositoryFactory::getForwardingRepository();
                    $addresses = BaseController::postedAddresses('forwardingAddresses');
                    $keepCopy = isset($_POST['keepCopy']);

                    $forwardingRepo->setForwardings($email, $domain, $addresses);
                    $forwardingRepo->setKeepCopy($email, $domain, $keepCopy);
                    ActivityLogger::logUpdate($domain, $userUid, "Forwarding settings updated");
                    $success = Translator::translate('user.msg_forwarding_updated');
                } elseif ($editMode === 'aliases') {
                    CsrfProtection::validateToken();
                    $email = "{$userUid}@{$domain}";
                    $aliasRepo = RepositoryFactory::getAliasRepository();
                    $action = $_POST['action'] ?? '';

                    if ($action === 'add') {
                        $newAlias = strtolower(trim($_POST['newAlias'] ?? ''));
                        if ($newAlias !== '') {
                            self::assertUserAliasAvailable($domain, $newAlias);
                            $aliasRepo->addUserAlias($email, $newAlias);
                            ActivityLogger::logUpdate($domain, $userUid, "Added alias: {$newAlias}");
                        }
                    } elseif ($action === 'remove') {
                        $aliasToRemove = $_POST['aliasAddress'] ?? '';
                        if ($aliasToRemove !== '') {
                            $aliasRepo->removeUserAlias($email, $aliasToRemove);
                            ActivityLogger::logUpdate($domain, $userUid, "Removed alias: {$aliasToRemove}");
                        }
                    }
                    $success = Translator::translate('user.msg_aliases_updated');
                } elseif ($editMode === 'bcc') {
                    CsrfProtection::validateToken();
                    $email = "{$userUid}@{$domain}";
                    $bccRepo = RepositoryFactory::getBccRepository();
                    $senderBcc = BaseController::postedAddress('senderBcc');
                    $recipientBcc = BaseController::postedAddress('recipientBcc');
                    $bccRepo->setUserSenderBcc($email, $senderBcc);
                    $bccRepo->setUserRecipientBcc($email, $recipientBcc);
                    ActivityLogger::logUpdate($domain, $userUid, "BCC settings updated");
                    $success = Translator::translate('common.msg_bcc_updated');
                } elseif ($editMode === 'relay') {
                    CsrfProtection::validateToken();
                    $email = "{$userUid}@{$domain}";
                    $relayRepo = RepositoryFactory::getRelayRepository();
                    $relayRepo->setRelayhost($email, BaseController::postedRelayhost());
                    ActivityLogger::logUpdate($domain, $userUid, "Relay settings updated");
                    $success = Translator::translate('common.msg_relay_updated');
                }
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $user = $userRepo->getUser($domain, $userUid);
        if ($user === null) {
            http_response_code(404);
            $tpl->render('page404.php');
            return;
        }

        // Fetch forwarding data if on forwarding tab
        $forwardings = [];
        $keepCopy = true;
        if ($editMode === 'forwarding') {
            $email = "{$userUid}@{$domain}";
            $forwardingRepo = RepositoryFactory::getForwardingRepository();
            $forwardings = $forwardingRepo->getForwardings($email);
            $keepCopy = $forwardingRepo->getKeepCopy($email);
        }

        // Fetch user aliases if on aliases tab
        $userAliases = [];
        if ($editMode === 'aliases') {
            $email = "{$userUid}@{$domain}";
            $userAliases = RepositoryFactory::getAliasRepository()->getUserAliases($email);
        }

        // Fetch BCC data if on bcc tab
        $userSenderBcc = null;
        $userRecipientBcc = null;
        if ($editMode === 'bcc') {
            $email = "{$userUid}@{$domain}";
            $bccRepo = RepositoryFactory::getBccRepository();
            $userSenderBcc = $bccRepo->getUserSenderBcc($email);
            $userRecipientBcc = $bccRepo->getUserRecipientBcc($email);
        }

        // Fetch relay data if on relay tab
        $userRelayhost = null;
        if ($editMode === 'relay') {
            $email = "{$userUid}@{$domain}";
            $userRelayhost = RepositoryFactory::getRelayRepository()->getRelayhost($email);
        }

        $tpl->render('userView.php', [
            'domain' => $domain,
            'user' => $user,
            'error' => $error,
            'validationErrors' => $validationErrors,
            'success' => $success,
            'editMode' => $editMode,
            'forwardings' => $forwardings,
            'keepCopy' => $keepCopy,
            'userAliases' => $userAliases,
            'userSenderBcc' => $userSenderBcc,
            'userRecipientBcc' => $userRecipientBcc,
            'userRelayhost' => $userRelayhost,
            'requireOldPassword' => Settings::getInstance()->requireOldPasswordOnChange,
            'openPages' => $openPages,
            'managedBy' => ReplicatedAccountGuard::owner("{$userUid}@{$domain}"),
            'lockedFields' => ReplicatedAccountGuard::lockedUserFields("{$userUid}@{$domain}"),
        ]);
    }

    /**
     * Renames a mailbox to another free address in the same domain (POST only).
     * The result is shown on the General tab through a session flash message.
     */
    public static function renameUser(TemplateEngine $tpl, string $domain, string $userUid): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $newUid = strtolower(trim($_POST['newUid'] ?? ''));
        if ($newUid === '' || $newUid === $userUid) {
            header("Location: /{$domain}/users/{$userUid}/general");
            exit;
        }

        try {
            $newEmail = AccountRenameService::renameUser($domain, $userUid, $newUid);
            $_SESSION['flash_success'] = Translator::translate('user.msg_renamed', ['address' => $newEmail]);
            header("Location: /{$domain}/users/{$newUid}/general");
            exit;
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = BaseController::errorMessage($e);
            header("Location: /{$domain}/users/{$userUid}/general");
            exit;
        }
    }

    public static function bulkAction(TemplateEngine $tpl, string $domain): void
    {
        Middleware::domainAdminRequired($domain);
        CsrfProtection::validateToken();

        $selectedUsers = $_POST['selectedUsers'] ?? [];
        $action = $_POST['action'] ?? '';
        $adminEmail = $_SESSION['email'] ?? '';

        if (!BaseController::isValidBulkRequest($selectedUsers, $action)) {
            header("Location: /{$domain}/users");
            exit;
        }

        $deleteDate = $action === 'delete' ? BaseController::postedDeleteDate("/{$domain}/users") : null;
        $userRepo = RepositoryFactory::getUserRepository();
        $done = BaseController::runBulk($selectedUsers, function (string $uid) use ($userRepo, $domain, $action, $adminEmail, $deleteDate): void {
            $user = $userRepo->getUser($domain, $uid) ?? throw BaseController::itemNotFound();
            if ($action === 'delete') {
                $userRepo->deleteUser($domain, $uid, $adminEmail, $deleteDate);
                AccountSettingsService::deleteAccounts(["{$uid}@{$domain}"]);
                return;
            }
            if (in_array('accountStatus', ReplicatedAccountGuard::lockedUserFields("{$uid}@{$domain}"), true)) {
                ReplicatedAccountGuard::assertNotReplicated("{$uid}@{$domain}");
            }
            $user->accountStatus = ($action === 'enable');
            $userRepo->updateUser($domain, $user);
        });

        if ($done !== []) {
            ActivityLogger::log($action, $domain, '', "Bulk {$action} on " . count($done) . " users");
        }
        header("Location: /{$domain}/users");
        exit;
    }

    /**
     * Handles user deletion (POST only).
     */
    public static function userDelete(TemplateEngine $tpl, string $domain, string $userUid): void
    {
        Middleware::domainAdminRequired($domain);
        CsrfProtection::validateToken();
        $deleteDate = BaseController::postedDeleteDate("/{$domain}/users");

        try {
            $adminEmail = $_SESSION['email'] ?? '';
            $userRepo = RepositoryFactory::getUserRepository();
            $userRepo->getUser($domain, $userUid) ?? throw BaseController::itemNotFound();
            $userRepo->deleteUser($domain, $userUid, $adminEmail, $deleteDate);
            AccountSettingsService::deleteAccounts(["{$userUid}@{$domain}"]);
            ActivityLogger::logDelete($domain, $userUid, "User deleted");
            BaseController::flashDeleted("{$userUid}@{$domain}");
        } catch (\Exception $e) {
            BaseController::flashItemError("{$userUid}@{$domain}", $e);
        }
        header("Location: /{$domain}/users");
        exit;
    }

    /**
     * Returns why a mailbox quota must not change, from the domain limits or the limits of
     * the logged-in domain admin, or null.
     */
    private static function quotaChangeError(string $domain, int $oldMb, int $newMb): ?string
    {
        $limitError = RepositoryFactory::getDomainRepository()->getDomain($domain)?->quotaChangeError($oldMb, $newMb);
        if ($limitError === null && $oldMb !== $newMb) {
            try {
                AdminLimits::assertQuota($oldMb, $newMb);
            } catch (InvalidInputException $e) {
                $limitError = $e;
            }
        }

        return $limitError === null ? null : Translator::translate($limitError->translationKey, $limitError->params);
    }

    /**
     * Displays the user creation page.
     */
    public static function userCreateView(TemplateEngine $tpl, string $domain): void
    {
        Middleware::domainAdminRequired($domain);

        $userRepo = RepositoryFactory::getUserRepository();
        $validationErrors = [];
        $error = null;
        $user = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userUid = strtolower(trim((string) ($_POST['uid'] ?? '')));
            $uidError = self::newUserUidError($domain, $userUid);

            if ($uidError !== null) {
                $validationErrors['uid'] = $uidError;
            } else {
                try {
                    // The create form has no status field; a new mailbox starts active.
                    $user = User::fromFormData(['uid' => $userUid] + $_POST + ['accountStatus' => true]);
                    $domainSettings = self::domainSettings($domain);
                    $user->setNewMailboxServices($domainSettings->disabledMailServices);
                    $password = $_POST['password'] ?? '';
                    $passwordRepeat = $_POST['password_repeat'] ?? '';
                    $validationErrors = UserPassword::validateLocalized($password, $passwordRepeat, $domainSettings);

                    if (empty($validationErrors)) {
                        // The creation limits of a domain admin
                        AdminLimits::assertCanCreate('users');
                        AdminLimits::assertQuota(0, $user->mailQuota);

                        // Enforce domain mailbox count and quota limits
                        $limitError = RepositoryFactory::getDomainRepository()->getDomain($domain)
                            ?->newMailboxError($user->mailQuota);
                        if ($limitError !== null) {
                            $tpl->render('userCreate.php', [
                                'domain' => $domain,
                                'validationErrors' => $validationErrors,
                                'error' => Translator::translate($limitError->translationKey, $limitError->params),
                                'user' => $user,
                            ]);
                            return;
                        }

                        $passwordHash = PasswordUtils::generatePasswordHash($password);
                        $userRepo->createUser($domain, $user, $passwordHash);
                        ActivityLogger::logCreate($domain, $user->uid, "User created");
                        BaseController::flashCreated("{$user->uid}@{$domain}");
                        header("Location: /{$domain}/users");
                        exit;
                    }
                } catch (\Exception $e) {
                    $error = BaseController::errorMessage($e);
                }
            }
        }

        $defaultQuota = self::domainSettings($domain)->defaultUserQuota;

        $tpl->render('userCreate.php', [
            'domain' => $domain,
            'validationErrors' => $validationErrors,
            'error' => $error,
            'user' => $user,
            'defaultQuota' => $defaultQuota > 0 ? $defaultQuota : 100,
        ]);
    }

    /**
     * Checks the lowercased local part of a new mailbox.
     *
     * @return ?string the translated error, or null when the address is valid and free
     */
    private static function newUserUidError(string $domain, string $uid): ?string
    {
        $address = "{$uid}@{$domain}";
        if ($uid === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return Translator::translate('common.msg_invalid_email', ['address' => $address]);
        }
        // A global admin reaches the create page for any domain name.
        if (RepositoryFactory::getDomainRepository()->getDomain($domain) === null) {
            return Translator::translate('common.msg_domain_not_found', ['domain' => $domain]);
        }
        if (RepositoryFactory::getUserRepository()->getUser($domain, $uid) !== null) {
            return Translator::translate('user.msg_exists', ['uid' => $uid]);
        }
        if (RepositoryFactory::getAliasRepository()->isAddressInUse($address)) {
            return Translator::translate('common.msg_address_in_use', ['address' => $address]);
        }

        return null;
    }

    /**
     * A per-user alias delivers every message for the address to the user, so it
     * must stay inside the user's domain and must not take over an existing address.
     */
    private static function assertUserAliasAvailable(string $domain, string $address): void
    {
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException(Translator::translate('user.msg_alias_invalid', ['address' => $address]));
        }
        if (explode('@', $address, 2)[1] !== $domain) {
            throw new \RuntimeException(Translator::translate('user.msg_alias_domain_mismatch', ['domain' => $domain]));
        }
        if (RepositoryFactory::getAliasRepository()->isAddressInUse($address)) {
            throw new \RuntimeException(Translator::translate('common.msg_address_in_use', ['address' => $address]));
        }
    }

    private static function domainSettings(string $domain): DomainSettings
    {
        return DomainSettings::fromSettingsString(
            RepositoryFactory::getDomainRepository()->getDomain($domain)?->settings ?? ''
        );
    }
}
