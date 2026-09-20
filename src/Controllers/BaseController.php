<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\BackendConnectionException;
use App\Exceptions\InvalidInputException;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Alias;
use App\Models\KeepMailboxDays;
use App\Repositories\RepositoryFactory;
use App\TemplateEngine;
use App\Utils\AddressList;
use App\Utils\Relayhost;

class BaseController
{
    /** Actions that the list pages offer in their bulk action menu. */
    public const BULK_ACTIONS = ['enable', 'disable', 'delete'];

    /**
     * Stores an error message that the next rendered page shows.
     */
    public static function flashError(string $message): void
    {
        $_SESSION['flash_error'] = $message;
    }

    /**
     * Stores a success message that the next rendered page shows.
     */
    public static function flashSuccess(string $message): void
    {
        $_SESSION['flash_success'] = $message;
    }

    /**
     * The delete_date of the mailboxes of a posted delete form (`keepMailboxDays`), or
     * null to keep them. Redirects back with an error when the admin may not choose the value.
     */
    public static function postedDeleteDate(string $backUrl): ?string
    {
        try {
            $days = KeepMailboxDays::parse($_POST['keepMailboxDays'] ?? null, Middleware::isGlobalAdmin());
        } catch (InvalidInputException $e) {
            self::flashError(self::errorMessage($e));
            header('Location: ' . $backUrl);
            exit;
        }

        return KeepMailboxDays::deleteDate($days);
    }

    /**
     * Reads one posted email address. An empty field gives null.
     *
     * @throws \RuntimeException when the value is not an email address
     */
    public static function postedAddress(string $field): ?string
    {
        $address = strtolower(trim((string) ($_POST[$field] ?? '')));
        if ($address === '') {
            return null;
        }
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw self::invalidAddress($address);
        }
        return $address;
    }

    /**
     * Reads the posted local part and domain of a new alias or mailing list.
     *
     * @return array{0: string, 1: string} the address and the domain, lowercased
     * @throws \RuntimeException naming the first failed check
     */
    public static function postedNewAliasAddress(): array
    {
        $localPart = trim((string) ($_POST['localPart'] ?? ''));
        $domain = strtolower(trim((string) ($_POST['domain'] ?? '')));
        if ($localPart === '' || $domain === '') {
            throw new \RuntimeException(Translator::translate('common.msg_address_required'));
        }
        Middleware::domainAdminRequired($domain);

        $address = strtolower($localPart . '@' . $domain);
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw self::invalidAddress($address);
        }
        // The domain select can be edited in the browser, so the domain must exist in the backend.
        $domainObj = RepositoryFactory::getDomainRepository()->getDomain($domain);
        if ($domainObj === null) {
            throw new \RuntimeException(Translator::translate('common.msg_domain_not_found', ['domain' => $domain]));
        }

        $aliasRepo = RepositoryFactory::getAliasRepository();
        if ($aliasRepo->isAddressInUse($address)) {
            throw new \RuntimeException(Translator::translate('common.msg_address_in_use', ['address' => $address]));
        }
        // Aliases and mailing lists both count against the domain alias limit.
        $aliasCount = $domainObj->aliases > 0 ? $aliasRepo->countAliasesForDomain($domain) : 0;
        if ($domainObj->aliases > 0 && $aliasCount >= $domainObj->aliases) {
            throw new \RuntimeException(Translator::translate('common.msg_alias_limit', [
                'current' => $aliasCount,
                'max' => $domainObj->aliases,
            ]));
        }

        return [$address, $domain];
    }

    /**
     * The domains that the admin manages, as getDomains() rows: every domain for a
     * global admin.
     *
     * @return list<array<string, mixed>>
     */
    public static function managedDomainRows(): array
    {
        $domains = RepositoryFactory::getDomainRepository()->getDomains();
        if (Middleware::isGlobalAdmin()) {
            return $domains;
        }

        return array_values(array_filter($domains, static fn (array $d): bool => Middleware::isDomainAdmin((string) $d['domainName'])));
    }

    /**
     * The domain filter of an alias or mailing list page. A global admin may see every
     * domain (null); a domain admin always sees one of its own domains.
     */
    public static function listDomainFilter(): ?string
    {
        Middleware::anyDomainAdminRequired();
        $domain = is_string($_GET['domain'] ?? null) && $_GET['domain'] !== '' ? $_GET['domain'] : null;
        if (Middleware::isGlobalAdmin()) {
            return $domain;
        }

        return Middleware::isDomainAdmin((string) $domain) ? $domain : (string) array_values($_SESSION['managedDomains'])[0];
    }

    /**
     * The status filter of a list page (`?status=active` or `?status=disabled`).
     *
     * @return array{0: string, 1: ?bool} the filter value ('' for all) and the activeOnly argument of the repository
     */
    public static function statusFilter(): array
    {
        return match ($_GET['status'] ?? '') {
            'active' => ['active', true],
            'disabled' => ['disabled', false],
            default => ['', null],
        };
    }

    /**
     * The list page URL, with the domain filter of the posted list form.
     */
    public static function listUrl(string $path): string
    {
        $domain = is_string($_POST['filterDomain'] ?? null) ? $_POST['filterDomain'] : '';

        return $domain === '' ? $path : $path . '?domain=' . rawurlencode($domain);
    }

    /**
     * Throws when the admin does not manage the domain of the address; the address then
     * counts as not found, so a domain admin learns nothing about other domains.
     */
    public static function assertManagedAddress(string $address): void
    {
        $domain = str_contains($address, '@') ? explode('@', $address, 2)[1] : '';
        if (!Middleware::isGlobalAdmin() && !Middleware::isDomainAdmin($domain)) {
            throw self::itemNotFound();
        }
    }

    /**
     * Reads a posted list of email addresses, one per line or comma.
     *
     * @return list<string> the addresses, lowercased and without duplicates
     * @throws \RuntimeException naming the first value that is not an email address
     */
    public static function postedAddresses(string $field): array
    {
        try {
            return AddressList::parse((string) ($_POST[$field] ?? ''));
        } catch (\InvalidArgumentException $e) {
            throw self::invalidAddress($e->getMessage());
        }
    }

    /**
     * Reads the posted alias or mailing list access policy.
     *
     * @throws \RuntimeException when iRedAPD does not know the policy
     */
    public static function postedAccessPolicy(): string
    {
        $policy = trim((string) ($_POST['accessPolicy'] ?? 'public'));
        try {
            return Alias::validAccessPolicy($policy);
        } catch (\InvalidArgumentException) {
            throw new \RuntimeException(Translator::translate('alias.msg_invalid_access_policy', ['policy' => $policy]));
        }
    }

    private static function invalidAddress(string $address): \RuntimeException
    {
        return new \RuntimeException(Translator::translate('common.msg_invalid_email', ['address' => $address]));
    }

    /**
     * Reads the posted relay host. An empty field removes the relay.
     *
     * @throws \RuntimeException when Postfix cannot use the value as a next hop
     */
    public static function postedRelayhost(): ?string
    {
        $relayhost = trim((string) ($_POST['relayhost'] ?? ''));
        if ($relayhost === '') {
            return null;
        }
        if (!Relayhost::isValid($relayhost)) {
            throw new \RuntimeException(Translator::translate('common.msg_invalid_relayhost', ['relayhost' => $relayhost]));
        }
        return $relayhost;
    }

    /**
     * Stores the success message for a created item.
     */
    public static function flashCreated(string $item): void
    {
        self::flashSuccess(Translator::translate('common.msg_item_created', ['item' => $item]));
    }

    /**
     * Stores the success message for a deleted item.
     */
    public static function flashDeleted(string $item): void
    {
        self::flashSuccess(Translator::translate('common.msg_item_deleted', ['item' => $item]));
    }

    /**
     * Applies $apply to every selected item and stores the outcome as flash
     * messages. $apply throws to report a failure for one item; the other
     * items still run.
     *
     * @return list<string> the items that succeeded
     */
    /**
     * Checks a bulk form post, and reports a missing selection or action
     * instead of redirecting without a message.
     */
    public static function isValidBulkRequest(mixed $items, mixed $action, array $actions = self::BULK_ACTIONS): bool
    {
        if (is_array($items) && $items !== [] && in_array($action, $actions, true)) {
            return true;
        }
        self::flashError(Translator::translate('common.msg_bulk_select'));
        return false;
    }

    public static function runBulk(array $items, callable $apply): array
    {
        $done = [];
        $failed = [];
        foreach (array_filter($items, is_string(...)) as $item) {
            try {
                $apply($item);
                $done[] = $item;
            } catch (\Exception $e) {
                $failed[] = self::itemFailure($item, $e);
            }
        }

        if ($failed !== []) {
            self::flashError(Translator::translate('common.msg_bulk_failed', ['items' => implode(', ', $failed)]));
        }
        if ($done !== []) {
            self::flashSuccess(Translator::translate('common.msg_bulk_done', ['count' => count($done)]));
        }
        return $done;
    }

    /**
     * Stores the failure of an action on one item as the flash error.
     */
    public static function flashItemError(string $item, \Throwable $e): void
    {
        self::flashError(self::itemError($item, $e));
    }

    /**
     * Returns the message for a failed action on one item.
     */
    public static function itemError(string $item, \Throwable $e): string
    {
        return Translator::translate('common.msg_bulk_failed', ['items' => self::itemFailure($item, $e)]);
    }

    private static function itemFailure(string $item, \Throwable $e): string
    {
        return "{$item} (" . self::errorMessage($e) . ')';
    }

    /**
     * Returns the exception for an item that an action cannot find.
     */
    public static function itemNotFound(): \RuntimeException
    {
        return new \RuntimeException(Translator::translate('common.msg_item_not_found'));
    }
    /**
     * Returns the flash text for a failed operation.
     *
     * Database errors carry SQL and schema details, so they go to the server
     * log and the admin sees a generic message. Other exceptions are the
     * repositories' own validation messages and are shown as they are.
     */
    public static function errorMessage(\Throwable $e): string
    {
        if ($e instanceof BackendConnectionException) {
            error_log('Backend connection error: ' . $e->getMessage());
            return Translator::translate('misc.backend_down_body');
        }
        if ($e instanceof InvalidInputException) {
            $field = $e->fieldKey !== '' ? ['field' => Translator::translate($e->fieldKey)] : [];
            return Translator::translate($e->translationKey, $e->params + $field);
        }
        if (!$e instanceof \PDOException) {
            return $e->getMessage();
        }
        error_log('Database error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return Translator::translate('common.msg_operation_failed');
    }

    /**
     * Renders the 404 error page.
     */
    public static function page404(TemplateEngine $tpl): void
    {
        http_response_code(404);
        $tpl->render('page404.php');
    }

    /**
     * Renders the 403 page for a form with a missing or invalid CSRF token.
     */
    public static function pageCsrf(TemplateEngine $tpl): void
    {
        http_response_code(403);
        $tpl->render('pageCsrf.php');
    }

    /**
     * Renders the 503 page when the mail server backend is not reachable.
     */
    public static function pageBackendDown(TemplateEngine $tpl, BackendConnectionException $e): void
    {
        error_log('Backend connection error: ' . $e->getMessage());
        http_response_code(503);
        $tpl->render('pageBackendDown.php');
    }
}
