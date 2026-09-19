<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\MailingList;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\Services\AdminLimits;
use App\Services\MailingListService;
use App\TemplateEngine;

class MailingListController
{
    public static function list(TemplateEngine $tpl): void
    {
        $domainFilter = BaseController::listDomainFilter();

        $settings = Settings::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;

        $repo = RepositoryFactory::getMailingListRepository();
        $paginatedResult = $repo->getMailingListsPaginated($page, $perPage, $domainFilter);
        $domains = BaseController::managedDomainRows();

        $tpl->render('mailingListList.php', [
            'mailingLists' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
            'domains' => $domains,
            'filterDomain' => $domainFilter ?? '',
        ]);
    }

    public static function createForm(TemplateEngine $tpl): void
    {
        Middleware::anyDomainAdminRequired();

        $domains = BaseController::managedDomainRows();
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();

            try {
                $address = self::createFromPost();
                BaseController::flashCreated($address);
                header("Location: /mailing-lists/{$address}");
                exit;
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $tpl->render('mailingListCreate.php', [
            'domains' => $domains,
            'error' => $error,
        ]);
    }

    /**
     * Validates the create form and creates the list.
     *
     * @return string the new list address
     */
    private static function createFromPost(): string
    {
        [$address, $domain] = BaseController::postedNewAliasAddress();
        AdminLimits::assertCanCreate('lists');

        MailingListService::create(
            $address,
            $domain,
            trim($_POST['name'] ?? ''),
            BaseController::postedAccessPolicy(),
            self::postedMaxMsgSize(),
        );
        ActivityLogger::logCreate($domain, '', "Created mailing list: {$address}");

        return $address;
    }

    public static function view(TemplateEngine $tpl, string $address): void
    {
        $domain = str_contains($address, '@') ? explode('@', $address, 2)[1] : '';
        Middleware::domainAdminRequired($domain);

        $repo = RepositoryFactory::getMailingListRepository();
        $ml = $repo->getMailingList($address);

        if ($ml === null) {
            BaseController::page404($tpl);
            return;
        }

        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();

            try {
                $success = self::applyViewAction($_POST['action'] ?? 'updateSettings', $address, $ml->domain);
                $ml = $repo->getMailingList($address) ?? $ml;
            } catch (\InvalidArgumentException $e) {
                $error = Translator::translate('common.msg_invalid_email', ['address' => $e->getMessage()]);
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        [$subscribers, $subscribersError] = self::loadFromMlmmj(MailingListService::subscribers(...), $address);
        [$moderators, $moderatorsError] = self::loadFromMlmmj(MailingListService::moderators(...), $address);

        $tpl->render('mailingListView.php', [
            'ml' => $ml,
            'owners' => $repo->getOwners($address),
            'subscribers' => $subscribers,
            'subscribersError' => $subscribersError,
            'moderators' => $moderators,
            'moderatorsError' => $moderatorsError,
            'supportsNewsletter' => $repo->supportsNewsletter(),
            'newsletterBaseUrl' => Settings::getInstance()->publicUrl . '/newsletters',
            // Keep the typed addresses when adding them failed.
            'subscribersDraft' => $error !== null ? (string) ($_POST['subscribers'] ?? '') : '',
            'success' => $success,
            'error' => $error,
        ]);
    }

    /**
     * @throws \RuntimeException when the posted size is not a whole number of 0 or more
     */
    private static function postedMaxMsgSize(): int
    {
        $value = $_POST['maxMsgSize'] ?? '';
        try {
            return MailingList::validMaxMsgSize(is_string($value) ? $value : null);
        } catch (\InvalidArgumentException) {
            throw new \RuntimeException(Translator::translate('mlist.msg_invalid_max_msg_size'));
        }
    }

    /**
     * Runs one POST action of the list view.
     *
     * @return string the success message
     */
    private static function applyViewAction(string $action, string $address, string $domain): string
    {
        switch ($action) {
            case 'updateSettings':
                MailingListService::update(
                    $address,
                    trim($_POST['name'] ?? ''),
                    BaseController::postedAccessPolicy(),
                    self::postedMaxMsgSize(),
                    isset($_POST['active']),
                    RepositoryFactory::getMailingListRepository()->supportsNewsletter() ? isset($_POST['isNewsletter']) : null,
                );
                ActivityLogger::logUpdate($domain, '', "Updated mailing list: {$address}");
                return Translator::translate('mlist.msg_updated');
            case 'updateOwners':
                MailingListService::setOwners($address, BaseController::postedAddresses('owners'));
                ActivityLogger::logUpdate($domain, '', "Updated owners for: {$address}");
                return Translator::translate('mlist.msg_owners_updated');
            case 'updateModerators':
                MailingListService::setModerators($address, BaseController::postedAddresses('moderators'));
                ActivityLogger::logUpdate($domain, '', "Updated moderators for: {$address}");
                return Translator::translate('mlist.msg_moderators_updated');
            case 'addSubscribers':
                $subscribers = self::postedAddresses('subscribers');
                MailingListService::addSubscribers($address, $subscribers);
                ActivityLogger::logUpdate($domain, '', "Added " . count($subscribers) . " subscriber(s) to: {$address}");
                return Translator::translate('mlist.msg_subscribers_added', ['count' => count($subscribers)]);
            case 'removeSubscriber':
                $subscriber = self::postedAddresses('subscriber');
                MailingListService::removeSubscribers($address, $subscriber);
                ActivityLogger::logUpdate($domain, '', "Removed subscriber " . implode(',', $subscriber) . " from: {$address}");
                return Translator::translate('mlist.msg_subscriber_removed', ['address' => implode(', ', $subscriber)]);
            default:
                throw new \UnexpectedValueException("Unknown action: {$action}");
        }
    }

    /**
     * @return string[] at least one valid address from the POST field
     */
    private static function postedAddresses(string $field): array
    {
        $addresses = BaseController::postedAddresses($field);
        if ($addresses === []) {
            throw new \RuntimeException(Translator::translate('newsletter.msg_invalid_email'));
        }

        return $addresses;
    }

    /**
     * Returns the addresses that mlmmj holds, or the error that prevented
     * loading them, so the settings stay usable while mlmmjadmin is down.
     *
     * @param callable(string): string[] $load
     * @return array{0: string[], 1: ?string}
     */
    private static function loadFromMlmmj(callable $load, string $address): array
    {
        try {
            return [$load($address), null];
        } catch (\Exception $e) {
            return [[], BaseController::errorMessage($e)];
        }
    }

    public static function delete(TemplateEngine $tpl, string $address): void
    {
        Middleware::domainAdminRequired(str_contains($address, '@') ? explode('@', $address, 2)[1] : '');
        CsrfProtection::validateToken();

        $repo = RepositoryFactory::getMailingListRepository();
        try {
            $ml = $repo->getMailingList($address) ?? throw BaseController::itemNotFound();
            MailingListService::delete($address);
            ActivityLogger::logDelete($ml->domain, '', "Deleted mailing list: {$address}");
            BaseController::flashDeleted($address);
        } catch (\Exception $e) {
            BaseController::flashItemError($address, $e);
        }

        header('Location: ' . BaseController::listUrl('/mailing-lists'));
        exit;
    }

    public static function bulkAction(TemplateEngine $tpl): void
    {
        Middleware::anyDomainAdminRequired();
        CsrfProtection::validateToken();

        $action = $_POST['action'] ?? '';
        $selected = $_POST['selected'] ?? [];

        if (!BaseController::isValidBulkRequest($selected, $action)) {
            header('Location: ' . BaseController::listUrl('/mailing-lists'));
            exit;
        }

        $repo = RepositoryFactory::getMailingListRepository();
        $done = BaseController::runBulk($selected, function (string $address) use ($repo, $action): void {
            BaseController::assertManagedAddress($address);
            $repo->getMailingList($address) ?? throw BaseController::itemNotFound();
            if ($action === 'delete') {
                MailingListService::delete($address);
            } else {
                $repo->enableDisableMailingList($address, $action === 'enable');
            }
        });

        if ($done !== []) {
            ActivityLogger::log($action, '', '', "Bulk {$action} on " . count($done) . " mailing lists");
        }
        header('Location: ' . BaseController::listUrl('/mailing-lists'));
        exit;
    }
}
