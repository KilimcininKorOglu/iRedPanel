<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\PaginatedResult;
use App\Models\ProfileToggles;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\AccountSettingsService;
use App\Services\ActivityLogger;
use App\Services\AdminLimits;
use App\Services\DomainAdminService;
use App\Services\DomainOwnershipService;
use App\Services\MailingListService;
use App\TemplateEngine;
use App\Utils\FormValue;

class DomainController
{
    /**
     * Displays the paginated domain list page.
     */
    public static function domainList(TemplateEngine $tpl): void
    {
        Middleware::loginRequired();

        $settings = Settings::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;
        [$statusFilter, $activeOnly] = BaseController::statusFilter();

        $isGlobalAdmin = !empty($_SESSION['isGlobalAdmin']);
        $paginatedResult = $isGlobalAdmin
            ? RepositoryFactory::getDomainRepository()->getDomainsPaginated($page, $perPage, $activeOnly)
            : self::managedDomainsPaginated($page, $perPage, $activeOnly);

        $tpl->render('domainList.php', [
            'paginatedResult' => $paginatedResult,
            'domains' => $paginatedResult->items,
            'statusFilter' => $statusFilter,
            'isGlobalAdmin' => $isGlobalAdmin,
            'canCreateDomain' => AdminLimits::canCreateDomain(),
        ]);
    }

    /**
     * Pages the domains that the logged-in domain admin manages.
     */
    private static function managedDomainsPaginated(int $page, int $perPage, ?bool $activeOnly): PaginatedResult
    {
        $repo = RepositoryFactory::getDomainRepository();
        $domains = [];
        foreach ($_SESSION['managedDomains'] ?? [] as $domainName) {
            $domain = $repo->getDomain($domainName);
            if ($domain !== null && ($activeOnly === null || $domain->active === $activeOnly)) {
                $domains[] = $domain;
            }
        }
        usort($domains, fn(Domain $a, Domain $b) => strcmp($a->domainName, $b->domainName));

        return new PaginatedResult(array_slice($domains, ($page - 1) * $perPage, $perPage), count($domains), $page, $perPage);
    }

    /**
     * Displays the domain creation form and handles creation.
     */
    public static function domainCreate(TemplateEngine $tpl): void
    {
        Middleware::loginRequired();
        if (!AdminLimits::canCreateDomain()) {
            http_response_code(403);
            echo 'Access denied: domain creation not allowed';
            exit;
        }

        $error = null;
        $validationErrors = [];
        $domain = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // The domain limits are global admin settings, as on the General tab.
                $domain = Domain::fromFormData(Middleware::isGlobalAdmin() ? $_POST : array_intersect_key($_POST, array_flip(['domainName', 'description', 'active'])));
                $validationErrors = self::newDomainErrors($domain->domainName);
                if ($validationErrors === []) {
                    AdminLimits::assertCanCreate('domains');
                    RepositoryFactory::getDomainRepository()->createDomain($domain);
                    self::assignToCreator($domain->domainName);
                    ActivityLogger::logCreate($domain->domainName, '', "Domain created: {$domain->domainName}");
                    BaseController::flashCreated($domain->domainName);
                    header("Location: /domains");
                    exit;
                }
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $tpl->render('domainCreate.php', [
            'domain' => $domain,
            'error' => $error,
            'validationErrors' => $validationErrors,
        ]);
    }

    /**
     * Returns the errors of a new domain name: format, an existing domain or alias
     * domain, and the ownership verification when the panel requires it.
     *
     * @return array<string, string> field => message; empty when the name is usable
     */
    private static function newDomainErrors(string $name): array
    {
        if ($name === '') {
            return ['domainName' => Translator::translate('domain.msg_name_required')];
        }
        if (!Domain::isValidName($name)) {
            return ['domainName' => Translator::translate('common.msg_invalid_domain_format')];
        }
        if (RepositoryFactory::getDomainRepository()->getDomain($name) !== null) {
            return ['domainName' => Translator::translate('domain.msg_exists', ['domain' => $name])];
        }
        if (RepositoryFactory::getDomainAliasRepository()->getAlias($name) !== null) {
            return ['domainName' => Translator::translate('domain.msg_is_alias_domain', ['domain' => $name])];
        }
        $ownershipRepo = RepositoryFactory::getDomainOwnershipRepository();
        if (Settings::getInstance()->requireDomainOwnershipVerification && !$ownershipRepo->isVerified($name)) {
            $code = DomainOwnershipService::pendingCode($ownershipRepo, $name, $_SESSION['email'] ?? '');

            return ['domainName' => Translator::translate('domain.msg_ownership_required', ['domain' => $name, 'code' => $code])];
        }

        return [];
    }

    /**
     * A domain admin that creates a domain becomes its admin, as in iRedAdmin-Pro.
     */
    private static function assignToCreator(string $domain): void
    {
        if (Middleware::isGlobalAdmin()) {
            return;
        }
        RepositoryFactory::getAdminRepository()->assignDomainToAdmin((string) $_SESSION['email'], $domain);
        $_SESSION['managedDomains'][] = $domain;
    }

    /**
     * Displays and handles domain editing.
     */
    public static function domainView(TemplateEngine $tpl, string $domainName, string $editMode = 'general'): void
    {
        // A domain admin edits the pages of a managed domain that the global admin left open;
        // the general page holds the limits and stays global admin only.
        Middleware::domainAdminRequired($domainName);
        $isGlobalAdmin = Middleware::isGlobalAdmin();

        $repo = RepositoryFactory::getDomainRepository();
        $stored = $repo->getDomain($domainName);
        if ($stored === null || !in_array($editMode, ProfileToggles::openDomainPages(new DomainSettings(), true), true)) {
            http_response_code(404);
            $tpl->render('page404.php');
            return;
        }
        $openPages = ProfileToggles::openDomainPages(DomainSettings::fromSettingsString($stored->settings), $isGlobalAdmin);
        if (!in_array($editMode, $openPages, true)) {
            if ($editMode === 'general' && $openPages !== []) {
                header('Location: /domains/' . rawurlencode($domainName) . '/' . $openPages[0]);
                exit;
            }
            http_response_code(403);
            echo 'Access denied: this page is disabled for domain admins';
            return;
        }

        $error = null;
        $success = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $success = self::saveDomainPage($domainName, $editMode, $isGlobalAdmin);
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $domain = $repo->getDomain($domainName);
        if ($domain === null) {
            http_response_code(404);
            $tpl->render('page404.php');
            return;
        }

        $domainSettings = DomainSettings::fromSettingsString($domain->settings);

        $catchallTarget = null;
        if ($editMode === 'catchall') {
            $catchallTarget = RepositoryFactory::getAliasRepository()->getCatchall($domainName);
        }

        $senderBcc = null;
        $recipientBcc = null;
        if ($editMode === 'bcc') {
            $bccRepo = RepositoryFactory::getBccRepository();
            $senderBcc = $bccRepo->getDomainSenderBcc($domainName);
            $recipientBcc = $bccRepo->getDomainRecipientBcc($domainName);
        }

        $domainRelayhost = null;
        if ($editMode === 'relay') {
            $domainRelayhost = RepositoryFactory::getRelayRepository()->getRelayhost('@' . $domainName);
        }

        $tpl->render('domainView.php', [
            'domain' => $domain,
            'domainSettings' => $domainSettings,
            'editMode' => $editMode,
            'domainAdmins' => $editMode === ProfileToggles::ADMINS_PAGE ? RepositoryFactory::getAdminRepository()->getDomainAdmins($domainName) : [],
            'catchallTarget' => $catchallTarget,
            'senderBcc' => $senderBcc,
            'recipientBcc' => $recipientBcc,
            'domainRelayhost' => $domainRelayhost,
            'supportsDomainQuota' => $repo->supportsDomainQuota(),
            'openPages' => ProfileToggles::openDomainPages($domainSettings, $isGlobalAdmin),
            'isGlobalAdmin' => $isGlobalAdmin,
            'error' => $error,
            'success' => $success,
        ]);
    }

    /**
     * Saves the posted form of one domain page.
     *
     * @return string the translated success message
     */
    private static function saveDomainPage(string $domainName, string $editMode, bool $isGlobalAdmin): string
    {
        return match ($editMode) {
            'general' => self::saveGeneral($domainName),
            'settings' => self::saveSettings($domainName, $isGlobalAdmin),
            'catchall' => self::saveCatchall($domainName),
            'bcc' => self::saveBcc($domainName),
            'relay' => self::saveRelay($domainName),
            ProfileToggles::ADMINS_PAGE => self::saveAdmins($domainName, $isGlobalAdmin),
        };
    }

    /**
     * Adds the posted address to the admins of the domain, or removes one admin.
     */
    private static function saveAdmins(string $domainName, bool $isGlobalAdmin): string
    {
        $scope = $isGlobalAdmin ? null : ($_SESSION['managedDomains'] ?? []);
        if (($_POST['action'] ?? '') === 'remove') {
            $address = FormValue::text($_POST, 'admin');
            DomainAdminService::change($domainName, [], [$address], false, $scope, $_SESSION['email'] ?? '');

            return Translator::translate('domain.msg_admin_removed', ['address' => $address]);
        }

        $address = FormValue::text($_POST, 'newAdmin');
        DomainAdminService::change($domainName, [$address], [], false, $scope);

        return Translator::translate('domain.msg_admin_added', ['address' => strtolower($address)]);
    }

    private static function saveGeneral(string $domainName): string
    {
        $repo = RepositoryFactory::getDomainRepository();
        $formDomain = Domain::fromFormData($_POST);
        // updateDomain() writes every column, so start from the stored domain and change
        // only the profile fields; the settings tab values and the disclaimer stay.
        $domain = $repo->getDomain($domainName) ?? throw new \RuntimeException(
            Translator::translate('common.msg_domain_not_found', ['domain' => $domainName])
        );
        $domain->applyProfile($formDomain);
        $repo->updateDomain($domain);
        ActivityLogger::logUpdate($domainName, '', "Domain updated: {$domainName}");

        return Translator::translate('domain.msg_updated');
    }

    private static function saveSettings(string $domainName, bool $isGlobalAdmin): string
    {
        $repo = RepositoryFactory::getDomainRepository();
        $domainSettings = DomainSettings::fromFormData($_POST);
        $currentDomain = $repo->getDomain($domainName) ?? throw new \RuntimeException(
            Translator::translate('common.msg_domain_not_found', ['domain' => $domainName])
        );
        if (!$isGlobalAdmin) {
            $storedSettings = DomainSettings::fromSettingsString($currentDomain->settings);
            // A min length that a global admin stored stays; a domain admin cannot lower it below the global min.
            if ($domainSettings->minPasswordLength !== $storedSettings->minPasswordLength) {
                $domainSettings->assertDomainAdminPasswordPolicy();
            }
            $domainSettings->keepGlobalAdminTogglesOf($storedSettings);
        }
        $domainSettings->keepOtherKeysOf($currentDomain->settings);
        $currentDomain->settings = $domainSettings->toSettingsString();
        $currentDomain->disclaimer = $domainSettings->disclaimer;
        $repo->updateDomain($currentDomain);
        ActivityLogger::logUpdate($domainName, '', "Domain settings updated: {$domainName}");

        return Translator::translate('domain.msg_settings_updated');
    }

    private static function saveCatchall(string $domainName): string
    {
        CsrfProtection::validateToken();
        RepositoryFactory::getAliasRepository()->setCatchall($domainName, BaseController::postedAddress('catchallTarget'));
        ActivityLogger::logUpdate($domainName, '', "Catch-all updated: {$domainName}");

        return Translator::translate('domain.msg_catchall_updated');
    }

    private static function saveBcc(string $domainName): string
    {
        CsrfProtection::validateToken();
        $bccRepo = RepositoryFactory::getBccRepository();
        $senderBcc = BaseController::postedAddress('senderBcc');
        $recipientBcc = BaseController::postedAddress('recipientBcc');
        $bccRepo->setDomainSenderBcc($domainName, $senderBcc);
        $bccRepo->setDomainRecipientBcc($domainName, $recipientBcc);
        ActivityLogger::logUpdate($domainName, '', "BCC settings updated: {$domainName}");

        return Translator::translate('common.msg_bcc_updated');
    }

    private static function saveRelay(string $domainName): string
    {
        CsrfProtection::validateToken();
        RepositoryFactory::getRelayRepository()->setRelayhost('@' . $domainName, BaseController::postedRelayhost());
        ActivityLogger::logUpdate($domainName, '', "Relay settings updated: {$domainName}");

        return Translator::translate('common.msg_relay_updated');
    }

    /**
     * Handles bulk domain operations (POST only).
     */
    public static function bulkAction(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $selectedDomains = $_POST['selectedDomains'] ?? [];
        $action = $_POST['action'] ?? '';
        $adminEmail = $_SESSION['email'] ?? '';

        if (!BaseController::isValidBulkRequest($selectedDomains, $action)) {
            header("Location: /domains");
            exit;
        }

        $deleteDate = $action === 'delete' ? BaseController::postedDeleteDate('/domains') : null;
        $domainRepo = RepositoryFactory::getDomainRepository();
        $done = BaseController::runBulk($selectedDomains, function (string $domainName) use ($domainRepo, $action, $adminEmail, $deleteDate): void {
            $domainRepo->getDomain($domainName) ?? throw BaseController::itemNotFound();
            if ($action === 'delete') {
                MailingListService::deleteDomainLists($domainName);
                $domainRepo->deleteDomain($domainName, $adminEmail, $deleteDate);
                AccountSettingsService::deleteDomain($domainName);
            } else {
                $domainRepo->enableDisableDomain($domainName, $action === 'enable');
            }
        });

        if ($done !== []) {
            ActivityLogger::log($action, '', '', "Bulk {$action} on " . count($done) . " domains");
        }
        header("Location: /domains");
        exit;
    }

    /**
     * Handles domain deletion (POST only).
     */
    public static function domainDelete(TemplateEngine $tpl, string $domainName): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();
        $deleteDate = BaseController::postedDeleteDate('/domains');

        try {
            $adminEmail = $_SESSION['email'] ?? '';
            $domainRepo = RepositoryFactory::getDomainRepository();
            $domainRepo->getDomain($domainName) ?? throw BaseController::itemNotFound();
            MailingListService::deleteDomainLists($domainName);
            $domainRepo->deleteDomain($domainName, $adminEmail, $deleteDate);
            AccountSettingsService::deleteDomain($domainName);
            ActivityLogger::logDelete($domainName, '', "Domain deleted: {$domainName}");
            BaseController::flashDeleted($domainName);
        } catch (\Exception $e) {
            BaseController::flashItemError($domainName, $e);
        }
        header("Location: /domains");
        exit;
    }
}
