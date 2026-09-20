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
use App\Services\MailboxQuotaFloor;
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
    /** The tabs of the user page, in the order the template shows them. */
    private const EDIT_MODES = ['general', 'password', 'services', 'forwarding', 'aliases', 'bcc', 'relay'];

    /** Every tab variable the template reads, so a tab only fills its own. */
    private const TAB_DEFAULTS = [
        'forwardings' => [],
        'keepCopy' => true,
        'userAliases' => [],
        'userSenderBcc' => null,
        'userRecipientBcc' => null,
        'userRelayhost' => null,
        'userTransport' => null,
    ];

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

        if (!in_array($editMode, self::EDIT_MODES, true)) {
            http_response_code(404);
            $tpl->render('page404.php');
            return;
        }

        $error = $_SESSION['flash_error'] ?? null;
        $validationErrors = [];
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $result = self::saveUserPage($domain, $userUid, $editMode);
                $error = $result['error'] ?? $error;
                $success = $result['success'] ?? $success;
                $validationErrors = $result['validationErrors'] ?? [];
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $user = RepositoryFactory::getUserRepository()->getUser($domain, $userUid);
        if ($user === null) {
            http_response_code(404);
            $tpl->render('page404.php');
            return;
        }

        $tpl->render('userView.php', self::tabData($domain, $userUid, $editMode) + [
            'domain' => $domain,
            'user' => $user,
            'error' => $error,
            'validationErrors' => $validationErrors,
            'success' => $success,
            'editMode' => $editMode,
            'requireOldPassword' => Settings::getInstance()->requireOldPasswordOnChange,
            'openPages' => $openPages,
            'managedBy' => ReplicatedAccountGuard::owner("{$userUid}@{$domain}"),
            'lockedFields' => ReplicatedAccountGuard::lockedUserFields("{$userUid}@{$domain}"),
        ]);
    }

    /**
     * Saves the posted form of one user tab.
     *
     * @return array{error?: ?string, success?: ?string, validationErrors?: array<string, string>}
     */
    private static function saveUserPage(string $domain, string $userUid, string $editMode): array
    {
        return match ($editMode) {
            'general' => self::saveGeneral($domain, $userUid),
            'password' => self::savePassword($domain, $userUid),
            'services' => self::saveServices($domain, $userUid),
            'forwarding' => self::saveForwarding($domain, $userUid),
            'aliases' => self::saveUserAliases($domain, $userUid),
            'bcc' => self::saveUserBcc($domain, $userUid),
            'relay' => self::saveUserRelay($domain, $userUid),
            default => throw new \LogicException("Unknown user edit mode: {$editMode}"),
        };
    }

    /**
     * @return array{error?: ?string, success?: ?string}
     */
    private static function saveGeneral(string $domain, string $userUid): array
    {
        $userRepo = RepositoryFactory::getUserRepository();
        $existingUser = $userRepo->getUser($domain, $userUid);
        $user = User::fromFormData($_POST);
        $user->uid = $userUid;
        if ($existingUser !== null) {
            $user->copyServicesFrom($existingUser);
            ReplicatedAccountGuard::keepUserFields("{$userUid}@{$domain}", $user, $existingUser);
        }

        // Prevent privilege escalation: only global admins can change domainGlobalAdmin
        if (!Middleware::isGlobalAdmin()) {
            $user->domainGlobalAdmin = $existingUser && $existingUser->domainGlobalAdmin;
        }

        $error = self::quotaChangeError(
            $domain,
            $existingUser->mailQuota ?? $user->mailQuota,
            $user->mailQuota,
            "{$userUid}@{$domain}",
        );
        if ($error !== null) {
            return ['error' => $error];
        }

        $userRepo->updateUser($domain, $user);
        ActivityLogger::logUpdate($domain, $userUid, "User profile updated");

        return ['success' => Translator::translate('user.msg_info_updated')];
    }

    /**
     * @return array{success?: ?string, validationErrors?: array<string, string>}
     */
    private static function savePassword(string $domain, string $userUid): array
    {
        $userRepo = RepositoryFactory::getUserRepository();

        // Old password verification if enabled
        if (Settings::getInstance()->requireOldPasswordOnChange) {
            $oldPassword = $_POST['old_password'] ?? '';
            if (!$userRepo->verifyUserPassword($domain, $userUid, $oldPassword)) {
                return ['validationErrors' => ['old_password' => Translator::translate('user.msg_current_password_incorrect')]];
            }
        }

        $password = $_POST['password'] ?? '';
        $passwordRepeat = $_POST['password_repeat'] ?? '';
        $validationErrors = UserPassword::validateLocalized($password, $passwordRepeat, self::domainSettings($domain));
        if ($validationErrors !== []) {
            return ['validationErrors' => $validationErrors];
        }

        $userRepo->updateUserPassword($domain, $userUid, PasswordUtils::generatePasswordHash($password));
        ActivityLogger::logUpdate($domain, $userUid, "Password changed");

        return ['success' => Translator::translate('common.msg_password_updated')];
    }

    /**
     * @return array{success?: ?string}
     */
    private static function saveServices(string $domain, string $userUid): array
    {
        $userRepo = RepositoryFactory::getUserRepository();
        $currentUser = $userRepo->getUser($domain, $userUid);
        if ($currentUser === null) {
            return [];
        }

        foreach (array_keys(User::SERVICE_LABELS) as $service) {
            $currentUser->$service = isset($_POST[$service]);
        }
        $userRepo->updateUser($domain, $currentUser);
        ActivityLogger::logUpdate($domain, $userUid, "Mail services updated");

        return ['success' => Translator::translate('user.msg_services_updated')];
    }

    /**
     * @return array{success: string}
     */
    private static function saveForwarding(string $domain, string $userUid): array
    {
        $email = "{$userUid}@{$domain}";
        $forwardingRepo = RepositoryFactory::getForwardingRepository();
        $forwardingRepo->setForwardings($email, $domain, BaseController::postedAddresses('forwardingAddresses'));
        $forwardingRepo->setKeepCopy($email, $domain, isset($_POST['keepCopy']));
        ActivityLogger::logUpdate($domain, $userUid, "Forwarding settings updated");

        return ['success' => Translator::translate('user.msg_forwarding_updated')];
    }

    /**
     * Adds or removes one per-user alias.
     *
     * @return array{success: string}
     */
    private static function saveUserAliases(string $domain, string $userUid): array
    {
        CsrfProtection::validateToken();
        $email = "{$userUid}@{$domain}";
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
                RepositoryFactory::getAliasRepository()->removeUserAlias($email, $aliasToRemove);
                ActivityLogger::logUpdate($domain, $userUid, "Removed alias: {$aliasToRemove}");
            }
        }

        return ['success' => Translator::translate('user.msg_aliases_updated')];
    }

    /**
     * @return array{success: string}
     */
    private static function saveUserBcc(string $domain, string $userUid): array
    {
        CsrfProtection::validateToken();
        $email = "{$userUid}@{$domain}";
        $bccRepo = RepositoryFactory::getBccRepository();
        $bccRepo->setUserSenderBcc($email, BaseController::postedAddress('senderBcc'));
        $bccRepo->setUserRecipientBcc($email, BaseController::postedAddress('recipientBcc'));
        ActivityLogger::logUpdate($domain, $userUid, "BCC settings updated");

        return ['success' => Translator::translate('common.msg_bcc_updated')];
    }

    /**
     * @return array{success: string}
     */
    private static function saveUserRelay(string $domain, string $userUid): array
    {
        CsrfProtection::validateToken();
        $email = "{$userUid}@{$domain}";
        RepositoryFactory::getRelayRepository()->setRelayhost($email, BaseController::postedRelayhost());
        // A wrong transport loses mail, so only a global admin sets it.
        if (array_key_exists('transport', $_POST)) {
            Middleware::globalAdminRequired();
            RepositoryFactory::getUserRepository()
                ->setTransport($domain, $userUid, MailTransport::valid(FormValue::text($_POST, 'transport')));
        }
        ActivityLogger::logUpdate($domain, $userUid, "Relay settings updated");

        return ['success' => Translator::translate('common.msg_relay_updated')];
    }

    /**
     * The stored values that one user tab shows, over the defaults of every tab.
     *
     * @return array<string, mixed>
     */
    private static function tabData(string $domain, string $userUid, string $editMode): array
    {
        $email = "{$userUid}@{$domain}";

        return match ($editMode) {
            'forwarding' => [
                'forwardings' => RepositoryFactory::getForwardingRepository()->getForwardings($email),
                'keepCopy' => RepositoryFactory::getForwardingRepository()->getKeepCopy($email),
            ],
            'aliases' => ['userAliases' => RepositoryFactory::getAliasRepository()->getUserAliases($email)],
            'bcc' => [
                'userSenderBcc' => RepositoryFactory::getBccRepository()->getUserSenderBcc($email),
                'userRecipientBcc' => RepositoryFactory::getBccRepository()->getUserRecipientBcc($email),
            ],
            'relay' => [
                'userRelayhost' => RepositoryFactory::getRelayRepository()->getRelayhost($email),
                'userTransport' => RepositoryFactory::getUserRepository()->getTransport($domain, $userUid),
            ],
            default => [],
        } + self::TAB_DEFAULTS;
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
            self::bulkChange($domain, array_values(array_filter($selectedUsers, is_string(...))), $action);
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
    private static function quotaChangeError(string $domain, int $oldMb, int $newMb, string $email = ''): ?string
    {
        $limitError = RepositoryFactory::getDomainRepository()->getDomain($domain)?->quotaChangeError($oldMb, $newMb);
        if ($limitError === null && $newMb < $oldMb && $email !== '') {
            $limitError = MailboxQuotaFloor::error($email, $newMb, Middleware::isGlobalAdmin());
        }
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
            RepositoryFactory::getDomainRepository()->getDomain($domain)->settings ?? ''
        );
    }
}
