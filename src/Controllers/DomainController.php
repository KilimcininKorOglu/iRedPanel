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
use App\Services\DomainOwnershipService;
use App\Services\MailingListService;
use App\TemplateEngine;

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
        $statusFilter = $_GET['status'] ?? null;
        $activeOnly = match ($statusFilter) {
            'active' => true,
            'disabled' => false,
            default => null,
        };

        $isGlobalAdmin = !empty($_SESSION['isGlobalAdmin']);
        $paginatedResult = $isGlobalAdmin
            ? RepositoryFactory::getDomainRepository()->getDomainsPaginated($page, $perPage, $activeOnly)
            : self::managedDomainsPaginated($page, $perPage, $activeOnly);

        $tpl->render('domainList.php', [
            'paginatedResult' => $paginatedResult,
            'domains' => $paginatedResult->items,
            'statusFilter' => $statusFilter,
            'isGlobalAdmin' => $isGlobalAdmin,
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
        Middleware::globalAdminRequired();

        $error = null;
        $validationErrors = [];
        $domain = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $domain = Domain::fromFormData($_POST);

                if (empty($domain->domainName)) {
                    $validationErrors['domainName'] = Translator::translate('domain.msg_name_required');
                } elseif (!Domain::isValidName($domain->domainName)) {
                    $validationErrors['domainName'] = Translator::translate('common.msg_invalid_domain_format');
                }

                if (empty($validationErrors)) {
                    $repo = RepositoryFactory::getDomainRepository();

                    // Check for duplicate
                    if ($repo->getDomain($domain->domainName) !== null) {
                        $validationErrors['domainName'] = Translator::translate('domain.msg_exists', ['domain' => $domain->domainName]);
                    } elseif (RepositoryFactory::getDomainAliasRepository()->getAlias($domain->domainName) !== null) {
                        $validationErrors['domainName'] = Translator::translate('domain.msg_is_alias_domain', ['domain' => $domain->domainName]);
                    } elseif (Settings::getInstance()->requireDomainOwnershipVerification) {
                        $ownershipRepo = RepositoryFactory::getDomainOwnershipRepository();
                        if (!$ownershipRepo->isVerified($domain->domainName)) {
                            $code = DomainOwnershipService::pendingCode($ownershipRepo, $domain->domainName, $_SESSION['email'] ?? '');
                            $validationErrors['domainName'] = Translator::translate('domain.msg_ownership_required', ['domain' => $domain->domainName, 'code' => $code]);
                        }
                    }

                    // Enforce admin domain creation limit with transaction to prevent TOCTOU
                    if (empty($validationErrors)) {
                        $pdo = self::getBackendPdo();
                        if ($pdo !== null) {
                            $pdo->beginTransaction();
                        }
                        try {
                            $adminEmail = $_SESSION['email'] ?? '';
                            $adminRepo = RepositoryFactory::getAdminRepository();
                            $admin = $adminRepo->getAdmin($adminEmail);
                            if ($admin !== null && $admin->createMaxDomains >= 0) {
                                $counts = $adminRepo->getAdminResourceCounts($adminEmail);
                                if ($counts['domains'] >= $admin->createMaxDomains) {
                                    $validationErrors['domainName'] = Translator::translate('domain.msg_creation_limit', ['limit' => $admin->createMaxDomains]);
                                }
                            }

                            if (empty($validationErrors)) {
                                $repo->createDomain($domain);
                                if ($pdo !== null) {
                                    $pdo->commit();
                                }
                                ActivityLogger::logCreate($domain->domainName, '', "Domain created: {$domain->domainName}");
                                BaseController::flashCreated($domain->domainName);
                                header("Location: /domains");
                                exit;
                            }

                            if ($pdo !== null && $pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                        } catch (\Throwable $e) {
                            if ($pdo !== null && $pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            throw $e;
                        }
                    }
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
        if ($stored === null || !in_array($editMode, ['general', ...ProfileToggles::DOMAIN_PROFILES], true)) {
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
        };
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

        $domainRepo = RepositoryFactory::getDomainRepository();
        $done = BaseController::runBulk($selectedDomains, function (string $domainName) use ($domainRepo, $action, $adminEmail): void {
            $domainRepo->getDomain($domainName) ?? throw BaseController::itemNotFound();
            if ($action === 'delete') {
                MailingListService::deleteDomainLists($domainName);
                $domainRepo->deleteDomain($domainName, $adminEmail);
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

        try {
            $adminEmail = $_SESSION['email'] ?? '';
            $domainRepo = RepositoryFactory::getDomainRepository();
            $domainRepo->getDomain($domainName) ?? throw BaseController::itemNotFound();
            MailingListService::deleteDomainLists($domainName);
            $domainRepo->deleteDomain($domainName, $adminEmail);
            AccountSettingsService::deleteDomain($domainName);
            ActivityLogger::logDelete($domainName, '', "Domain deleted: {$domainName}");
            BaseController::flashDeleted($domainName);
        } catch (\Exception $e) {
            BaseController::flashItemError($domainName, $e);
        }
        header("Location: /domains");
        exit;
    }

    private static function getBackendPdo(): ?\PDO
    {
        return match (Settings::getInstance()->backend) {
            'mysql' => \App\Repositories\Mysql\MysqlConnection::getInstance()->getPdo(),
            'pgsql' => \App\Repositories\Pgsql\PgsqlConnection::getInstance()->getPdo(),
            default => null,
        };
    }
}
