<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\Exceptions\InvalidInputException;
use App\I18n\Translator;
use App\Middleware;
use App\Models\DomainSettings;
use App\Models\MailboxStorage;
use App\Models\MailTransport;
use App\Models\ProfileToggles;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserPassword;
use App\Repositories\RepositoryFactory;
use App\Services\AccountRenameService;
use App\Services\AccountSettingsService;
use App\Services\ActivityLogger;
use App\Services\AdminLimits;
use App\Services\LdifExportService;
use App\Services\Replication\ReplicatedAccountGuard;
use App\Services\UserAliasService;
use App\Services\UserBulkUpdate;
use App\TemplateEngine;
use App\Utils\FormValue;
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
        [$statusFilter, $activeOnly] = BaseController::statusFilter();

        $paginatedResult = $userRepo->getUsersPaginated($domain, $page, $perPage, $startsWith, $activeOnly, $sortBy, $sortDir);
        $usedQuotas = RepositoryFactory::getQuotaRepository()->getDomainUsedQuotas($domain);

        $tpl->render('userList.php', [
            'domain' => $domain,
            'users' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
            'supportsCreate' => $userRepo->supportsCreateUser(),
            'supportsLdif' => LdifExportService::available(),
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
                        $newAlias = strtolower(FormValue::text($_POST, 'newAlias'));
                        if ($newAlias !== '') {
                            UserAliasService::add($email, $domain, $newAlias);
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
                    // A wrong transport loses mail, so only a global admin sets it.
                    if (array_key_exists('transport', $_POST)) {
                        Middleware::globalAdminRequired();
                        $userRepo->setTransport($domain, $userUid, MailTransport::valid(FormValue::text($_POST, 'transport')));
                    }
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
        $userTransport = null;
        if ($editMode === 'relay') {
            $email = "{$userUid}@{$domain}";
            $userRelayhost = RepositoryFactory::getRelayRepository()->getRelayhost($email);
            $userTransport = $userRepo->getTransport($domain, $userUid);
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
            'userTransport' => $userTransport,
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

    /** Bulk actions that write one value to every selected mailbox. */
    private const BULK_CHANGES = ['language', 'transport', 'password'];

    /**
     * Writes the posted value of $action to every selected mailbox and redirects to the list.
     *
     * @param list<string> $uids
     */
    private static function bulkChange(string $domain, array $uids, string $action): void
    {
        try {
            $changes = self::bulkChangeValue($domain, $action);
            $updated = UserBulkUpdate::apply($domain, $uids, $changes);
            ActivityLogger::log('update', $domain, '', "Bulk {$action} on " . count($updated) . " users");
            BaseController::flashSuccess(Translator::translate('common.msg_bulk_done', ['count' => count($updated)]));
        } catch (\Exception $e) {
            BaseController::flashError(BaseController::errorMessage($e));
        }

        header("Location: /{$domain}/users");
        exit;
    }

    /**
     * The validated value of a bulk change.
     *
     * @return array<string, mixed> one UserBulkUpdate field
     * @throws \Exception when the posted value is invalid or the admin may not set it
     */
    private static function bulkChangeValue(string $domain, string $action): array
    {
        if ($action === 'transport') {
            // A wrong transport loses mail, so only a global admin sets it.
            Middleware::globalAdminRequired();
            return ['transport' => MailTransport::valid(FormValue::text($_POST, 'bulkTransport'))];
        }
        if ($action === 'language') {
            return ['language' => (string) User::validLanguage(FormValue::text($_POST, 'bulkLanguage'))];
        }

        $password = is_string($_POST['bulkPassword'] ?? null) ? $_POST['bulkPassword'] : '';
        $errors = UserPassword::validateLocalized($password, $password, self::domainSettings($domain));
        if ($errors !== []) {
            throw new \RuntimeException(reset($errors));
        }

        return ['password' => $password];
    }

    public static function bulkAction(TemplateEngine $tpl, string $domain): void
    {
        Middleware::domainAdminRequired($domain);
        CsrfProtection::validateToken();

        $selectedUsers = $_POST['selectedUsers'] ?? [];
        $action = $_POST['action'] ?? '';
        $adminEmail = $_SESSION['email'] ?? '';

        if (!BaseController::isValidBulkRequest($selectedUsers, $action, [...BaseController::BULK_ACTIONS, ...self::BULK_CHANGES])) {
            header("Location: /{$domain}/users");
            exit;
        }

        if (in_array($action, self::BULK_CHANGES, true)) {
            self::bulkChange($domain, array_values(array_filter($selectedUsers, 'is_string')), $action);
            return;
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

        $validationErrors = [];
        $error = null;
        $user = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userUid = strtolower(trim((string) ($_POST['uid'] ?? '')));
            $validationErrors = array_filter(['uid' => self::newUserUidError($domain, $userUid)]);

            if ($validationErrors === []) {
                try {
                    // The create form has no status field; a new mailbox starts active.
                    $user = User::fromFormData(['uid' => $userUid] + $_POST + ['accountStatus' => true]);
                    $validationErrors = self::createUser($domain, $user);
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
            'posted' => $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : null,
            'defaultQuota' => $defaultQuota > 0 ? $defaultQuota : 100,
            'mailboxFormats' => MailboxStorage::FORMATS,
        ]);
    }

    /**
     * Creates the mailbox of the create form and redirects to the user list.
     *
     * @return array<string, string> the validation errors of the password fields
     * @throws \Exception when a check, a limit or the backend refuses the mailbox
     */
    private static function createUser(string $domain, User $user): array
    {
        $domainSettings = self::domainSettings($domain);
        $user->setNewMailboxServices($domainSettings->disabledMailServices);
        [$passwordHash, $validationErrors] = self::postedPasswordHash($domainSettings);
        if ($validationErrors !== []) {
            return $validationErrors;
        }
        $userRepo = RepositoryFactory::getUserRepository();
        $storage = MailboxStorage::fromInput($_POST, Middleware::isGlobalAdmin());
        $storage->assertPathFree($userRepo);

        // The creation limits of a domain admin, then the domain mailbox count and quota limits
        AdminLimits::assertCanCreate('users');
        AdminLimits::assertQuota(0, $user->mailQuota);
        $limitError = RepositoryFactory::getDomainRepository()->getDomain($domain)?->newMailboxError($user->mailQuota);
        if ($limitError !== null) {
            throw $limitError;
        }

        $userRepo->createUser($domain, $user, $passwordHash, $storage);
        ActivityLogger::logCreate($domain, $user->uid, "User created");
        BaseController::flashCreated("{$user->uid}@{$domain}");
        header("Location: /{$domain}/users");
        exit;
    }

    /**
     * The hash of the posted password, or the posted password hash, which the password
     * policy cannot check.
     *
     * @return array{0: string, 1: array<string, string>} the hash and the validation errors
     */
    private static function postedPasswordHash(DomainSettings $domainSettings): array
    {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $hash = FormValue::text($_POST, 'passwordHash');
        if ($hash !== '') {
            return [UserPassword::acceptedHash($hash, $password), []];
        }
        $repeat = is_string($_POST['password_repeat'] ?? null) ? $_POST['password_repeat'] : '';
        $validationErrors = UserPassword::validateLocalized($password, $repeat, $domainSettings);

        return [$validationErrors === [] ? PasswordUtils::generatePasswordHash($password) : '', $validationErrors];
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

    private static function domainSettings(string $domain): DomainSettings
    {
        return DomainSettings::fromSettingsString(
            RepositoryFactory::getDomainRepository()->getDomain($domain)?->settings ?? ''
        );
    }
}
