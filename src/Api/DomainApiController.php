<?php

declare(strict_types=1);

namespace App\Api;

use App\Exceptions\InvalidInputException;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\ProfileToggles;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\AccountSettingsService;
use App\Services\DomainAdminService;
use App\Services\DomainOwnershipService;
use App\Services\MailingListService;

class DomainApiController
{
    public static function list(): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = RepositoryFactory::getDomainRepository();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['perPage'] ?? 50);

        try {
            $activeOnly = ApiInput::disabledOnly();
            $nameOnly = ApiInput::queryFlag('nameOnly');
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        $result = $repo->getDomainsPaginated($page, $perPage, $activeOnly);
        ApiResponse::paginated($result, fn(Domain $d) => $nameOnly ? $d->domainName : [
            'domainName' => $d->domainName,
            'description' => $d->description,
            'active' => $d->active,
            'mailboxes' => $d->mailboxes,
            'aliases' => $d->aliases,
            'quota' => $d->quota,
            'maxQuota' => $d->maxQuota,
            'created' => $d->created,
        ]);
    }

    public static function get(string $domain): void
    {
        ApiMiddleware::requireDomainAccess($domain);
        $d = RepositoryFactory::getDomainRepository()->getDomain($domain);
        if ($d === null) {
            ApiResponse::error('Domain not found', 404);
            return;
        }
        $settings = DomainSettings::fromSettingsString($d->settings)->toFormData($d->disclaimer);
        $admins = ['admins' => RepositoryFactory::getAdminRepository()->getDomainAdmins($domain)];
        ApiResponse::success((array) $d + $settings + self::routing($domain) + $admins);
    }

    /**
     * Changes the admins of the domain with addAdmins, removeAdmins and removeAllAdmins,
     * as the Admins tab of the domain page does. A domain key adds and removes only the
     * mailboxes of its domains; removeAllAdmins needs a global key.
     */
    public static function admins(string $domain): void
    {
        ApiMiddleware::requireDomainAccess($domain);
        ApiMiddleware::requireWriteAccess();
        if (RepositoryFactory::getDomainRepository()->getDomain($domain) === null) {
            ApiResponse::error('Domain not found', 404);
            return;
        }

        $data = ApiMiddleware::getJsonBody();
        $key = ApiMiddleware::getCurrentKey();
        try {
            $add = ApiInput::addresses($data['addAdmins'] ?? [], 'addAdmins');
            $remove = ApiInput::addresses($data['removeAdmins'] ?? [], 'removeAdmins');
            $removeAll = ApiInput::bool($data, 'removeAllAdmins', false);
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }
        if ($removeAll && !$key->isGlobal()) {
            ApiResponse::error('removeAllAdmins requires a global API key', 403);
            return;
        }

        try {
            DomainAdminService::change($domain, $add, $remove, $removeAll, $key->isGlobal() ? null : $key->domainList());
        } catch (InvalidInputException $e) {
            ApiResponse::invalidInput($e);
            return;
        }
        ApiResponse::success(['message' => 'Domain admins updated', 'admins' => RepositoryFactory::getAdminRepository()->getDomainAdmins($domain)]);
    }

    /**
     * Applies the settings fields of the body to $domain. Fields missing from the
     * body and the settings keys of other tools keep their stored values.
     *
     * @throws \InvalidArgumentException for an invalid value
     */
    private static function applySettings(Domain $domain, array $data, bool $isGlobalKey = true): void
    {
        $stored = DomainSettings::fromSettingsString($domain->settings);
        $settings = DomainSettings::fromFormData(
            array_intersect_key($data, $stored->toFormData('')) + $stored->toFormData($domain->disclaimer)
        );
        if (!$isGlobalKey && $settings->minPasswordLength !== $stored->minPasswordLength) {
            $settings->assertDomainAdminPasswordPolicy();
        }
        $settings->keepOtherKeysOf($domain->settings);
        $domain->settings = $settings->toSettingsString();
        $domain->disclaimer = $settings->disclaimer;
    }

    /** Body fields that hold one email address; null or '' removes the address. */
    private const ADDRESS_FIELDS = ['catchall', 'senderBcc', 'recipientBcc'];

    /**
     * Returns the catch-all, BCC and relay settings of a domain.
     */
    private static function routing(string $domain): array
    {
        $bcc = RepositoryFactory::getBccRepository();
        return [
            'catchall' => RepositoryFactory::getAliasRepository()->getCatchall($domain),
            'senderBcc' => $bcc->getDomainSenderBcc($domain),
            'recipientBcc' => $bcc->getDomainRecipientBcc($domain),
            'relayhost' => RepositoryFactory::getRelayRepository()->getRelayhost('@' . $domain),
        ];
    }

    /**
     * Reads the catch-all, BCC and relay fields that the body sets.
     *
     * @return array<string, ?string>
     * @throws \InvalidArgumentException for an invalid address or relay host
     */
    private static function routingFromBody(array $data): array
    {
        $routing = [];
        foreach (self::ADDRESS_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $routing[$field] = ApiInput::address($data, $field);
            }
        }
        if (array_key_exists('relayhost', $data)) {
            $routing['relayhost'] = ApiInput::relayhost($data, 'relayhost');
        }
        return $routing;
    }

    /**
     * @param array<string, ?string> $routing from routingFromBody()
     */
    private static function writeRouting(string $domain, array $routing): void
    {
        $bcc = RepositoryFactory::getBccRepository();
        foreach ($routing as $field => $value) {
            match ($field) {
                'catchall' => RepositoryFactory::getAliasRepository()->setCatchall($domain, $value),
                'senderBcc' => $bcc->setDomainSenderBcc($domain, $value),
                'recipientBcc' => $bcc->setDomainRecipientBcc($domain, $value),
                'relayhost' => RepositoryFactory::getRelayRepository()->setRelayhost('@' . $domain, $value),
                default => throw new \LogicException("Unknown routing field: {$field}"),
            };
        }
    }

    public static function create(): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        try {
            // A new domain starts active unless the request sets active, as in the web form.
            $domain = Domain::fromFormData($data + ['active' => true]);
            self::applySettings($domain, $data);
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        if (empty($domain->domainName)) {
            ApiResponse::error('domainName is required');
            return;
        }
        if (!Domain::isValidName($domain->domainName)) {
            ApiResponse::error('domainName must be a valid domain name');
            return;
        }

        $repo = RepositoryFactory::getDomainRepository();
        if ($repo->getDomain($domain->domainName) !== null) {
            ApiResponse::error('Domain already exists', 409);
            return;
        }
        if (RepositoryFactory::getDomainAliasRepository()->getAlias($domain->domainName) !== null) {
            ApiResponse::error('Domain already exists as an alias domain', 409);
            return;
        }

        if (Settings::getInstance()->requireDomainOwnershipVerification) {
            $ownershipRepo = RepositoryFactory::getDomainOwnershipRepository();
            if (!$ownershipRepo->isVerified($domain->domainName)) {
                $code = DomainOwnershipService::pendingCode($ownershipRepo, $domain->domainName, 'api');
                ApiResponse::error(
                    "Domain ownership must be verified before creation. Add a DNS TXT record with the value {$code} to {$domain->domainName}, then verify it on the Domain Ownership page.",
                    403,
                );
                return;
            }
        }

        $repo->createDomain($domain);
        ApiResponse::created(['domainName' => $domain->domainName]);
    }

    /**
     * Body fields that a domain key may set => the domain page that holds them in the web panel.
     * The limits, the status and the page toggles bound the domain admin, so only a global key sets them.
     */
    private const DOMAIN_KEY_FIELDS = [
        'defaultUserQuota' => 'settings',
        'minPasswordLength' => 'settings',
        'maxPasswordLength' => 'settings',
        'disclaimer' => 'settings',
        'disabledMailServices' => 'settings',
        'disabledUserPreferences' => 'settings',
        'selfService' => 'settings',
        'catchall' => 'catchall',
        'senderBcc' => 'bcc',
        'recipientBcc' => 'bcc',
        'relayhost' => 'relay',
    ];

    /**
     * Returns why a domain key may not send $data, or null when it may.
     */
    private static function domainKeyError(array $data, DomainSettings $settings): ?string
    {
        $openPages = ProfileToggles::openDomainPages($settings, false);
        foreach (array_keys($data) as $field) {
            $page = self::DOMAIN_KEY_FIELDS[$field] ?? null;
            if ($page === null) {
                return "{$field} requires a global API key";
            }
            if (!in_array($page, $openPages, true)) {
                return "{$field} is on the domain page '{$page}', which is disabled for domain admins";
            }
        }

        return null;
    }

    public static function update(string $domain): void
    {
        // A domain key sets the fields of the domain pages that a domain admin edits in the web panel.
        ApiMiddleware::requireDomainAccess($domain);
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getDomainRepository();
        $existing = $repo->getDomain($domain);
        if ($existing === null) {
            ApiResponse::error('Domain not found', 404);
            return;
        }

        $data = ApiMiddleware::getJsonBody();
        $isGlobalKey = ApiMiddleware::getCurrentKey()?->isGlobal() ?? false;
        $keyError = $isGlobalKey ? null : self::domainKeyError($data, DomainSettings::fromSettingsString($existing->settings));
        if ($keyError !== null) {
            ApiResponse::error($keyError, 403);
            return;
        }
        try {
            // Fields missing from the body keep their stored values.
            $form = Domain::fromFormData($data + [
                'description' => $existing->description,
                'active' => $existing->active,
                'maxQuota' => $existing->maxQuota,
                'quota' => $existing->quota,
                'mailboxes' => $existing->mailboxes,
                'aliases' => $existing->aliases,
                'lists' => $existing->lists,
                'backupMx' => $existing->backupMx,
                'primaryMx' => $existing->primaryMx,
                'transport' => $existing->transport,
            ]);
            $routing = self::routingFromBody($data);
            self::applySettings($existing, $data, $isGlobalKey);
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        // updateDomain() writes every column.
        $existing->applyProfile($form);
        $repo->updateDomain($existing);
        self::writeRouting($domain, $routing);
        ApiResponse::success(['message' => 'Domain updated']);
    }

    public static function delete(string $domain): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        try {
            $deleteDate = ApiInput::deleteDate();
        } catch (InvalidInputException $e) {
            ApiResponse::invalidInput($e);
            return;
        }
        $repo = RepositoryFactory::getDomainRepository();
        if ($repo->getDomain($domain) === null) {
            ApiResponse::error('Domain not found', 404);
            return;
        }

        MailingListService::deleteDomainLists($domain);
        $repo->deleteDomain($domain, 'api', $deleteDate);
        AccountSettingsService::deleteDomain($domain);
        ApiResponse::deleted();
    }
}
