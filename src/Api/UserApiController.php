<?php

declare(strict_types=1);

namespace App\Api;

use App\Models\DomainSettings;
use App\Models\ProfileToggles;
use App\Models\User;
use App\Repositories\RepositoryFactory;
use App\Services\AccountSettingsService;
use App\Services\Replication\ReplicatedAccountGuard;
use App\Utils\PasswordUtils;

class UserApiController
{
    public static function list(string $domain): void
    {
        ApiMiddleware::requireDomainAccess($domain);
        $repo = RepositoryFactory::getUserRepository();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['perPage'] ?? 50);

        $result = $repo->getUsersPaginated($domain, $page, $perPage);
        ApiResponse::paginated($result, fn(User $u) => [
            'uid' => $u->uid,
            'email' => $u->uid . '@' . $domain,
            'name' => $u->cn,
            'mailQuota' => $u->mailQuota,
            'active' => $u->accountStatus,
        ]);
    }

    public static function get(string $email): void
    {
        [$uid, $domain] = self::parseEmail($email);
        if ($uid === null) {
            ApiResponse::error('Invalid email format');
            return;
        }
        ApiMiddleware::requireDomainAccess($domain);

        $user = RepositoryFactory::getUserRepository()->getUser($domain, $uid);
        if ($user === null) {
            ApiResponse::error('User not found', 404);
            return;
        }

        ApiResponse::success((array) $user + self::routing("{$uid}@{$domain}"));
    }

    public static function create(string $domain): void
    {
        ApiMiddleware::requireDomainAccess($domain);
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        $repo = RepositoryFactory::getUserRepository();

        if (!$repo->supportsCreateUser()) {
            ApiResponse::error('User creation not supported for this backend', 501);
            return;
        }

        // Prevent privilege escalation — only global API keys may set global admin flag
        if (!ApiMiddleware::getCurrentKey()?->isGlobal()) {
            unset($data['domainGlobalAdmin']);
        }

        $data['uid'] = strtolower(trim((string) ($data['uid'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $domainObj = RepositoryFactory::getDomainRepository()->getDomain($domain);
        $failure = self::newUserError($data['uid'], $password, $domain, $domainObj !== null);
        if ($failure !== null) {
            ApiResponse::error($failure[0], $failure[1]);
            return;
        }
        $domainSettings = DomainSettings::fromSettingsString($domainObj->settings);

        // A new mailbox starts active unless the request sets accountStatus.
        $defaults = ['accountStatus' => true];
        if ($domainSettings->defaultUserQuota > 0) {
            $defaults['mailQuota'] = $domainSettings->defaultUserQuota;
        }
        $user = self::userFromBody($data + $defaults);
        if ($user === null) {
            return;
        }
        $user->setNewMailboxServices($domainSettings->disabledMailServices);

        $limitError = $domainObj->newMailboxError($user->mailQuota);
        if ($limitError !== null) {
            ApiResponse::error($limitError->getMessage(), 403);
            return;
        }

        $validationErrors = \App\Models\UserPassword::validate($password, $password, $domainSettings);
        if (!empty($validationErrors)) {
            // The API has no repeat field, so every policy error is under the password key.
            ApiResponse::error('Password policy violation: ' . $validationErrors['password']);
            return;
        }

        $passwordHash = PasswordUtils::generatePasswordHash($password);
        $repo->createUser($domain, $user, $passwordHash);
        ApiResponse::created(['email' => $user->uid . '@' . $domain]);
    }

    public static function update(string $email): void
    {
        ApiMiddleware::requireWriteAccess();
        [$uid, $domain] = self::parseEmail($email);
        if ($uid === null) {
            ApiResponse::error('Invalid email format');
            return;
        }
        ApiMiddleware::requireDomainAccess($domain);

        $repo = RepositoryFactory::getUserRepository();
        $existing = $repo->getUser($domain, $uid);
        if ($existing === null) {
            ApiResponse::error('User not found', 404);
            return;
        }

        $data = ApiMiddleware::getJsonBody();

        // Prevent privilege escalation via API: domainGlobalAdmin cannot be changed via API
        unset($data['domainGlobalAdmin']);

        $pageError = self::closedPageError($data, $domain);
        if ($pageError !== null) {
            ApiResponse::error($pageError, 403);
            return;
        }

        // Validate the body before the password change, so a bad field changes nothing.
        $user = self::userFromBody(array_merge((array) $existing, $data));
        if ($user === null) {
            return;
        }
        $routing = self::routingFromBody($data, "{$uid}@{$domain}");
        if ($routing === null) {
            return;
        }
        $user->uid = $uid;

        $locked = ReplicatedAccountGuard::changedUserFields("{$uid}@{$domain}", $user, $existing);
        if ($locked !== []) {
            ApiResponse::error('Managed by the directory, read-only fields: ' . implode(', ', $locked), 409);
            return;
        }

        if (isset($data['password']) && !self::changePassword($domain, $uid, (string) $data['password'])) {
            return;
        }

        $limitError = RepositoryFactory::getDomainRepository()->getDomain($domain)
            ?->quotaChangeError($existing->mailQuota, $user->mailQuota);
        if ($limitError !== null) {
            ApiResponse::error($limitError->getMessage(), 403);
            return;
        }

        $repo->updateUser($domain, $user);
        self::writeRouting("{$uid}@{$domain}", $domain, $routing);
        ApiResponse::success(['message' => 'User updated']);
    }

    /**
     * Sets a new password after the password policy of the domain accepts it.
     *
     * @return bool false after the error response
     */
    private static function changePassword(string $domain, string $uid, string $password): bool
    {
        $domainSettings = DomainSettings::fromSettingsString(
            RepositoryFactory::getDomainRepository()->getDomain($domain)?->settings ?? ''
        );
        $validationErrors = \App\Models\UserPassword::validate($password, $password, $domainSettings);
        if (!empty($validationErrors)) {
            // The API has no repeat field, so every policy error is under the password key.
            ApiResponse::error('Password policy violation: ' . $validationErrors['password']);
            return false;
        }
        RepositoryFactory::getUserRepository()->updateUserPassword($domain, $uid, PasswordUtils::generatePasswordHash($password));
        return true;
    }

    /**
     * Returns the forwarding, BCC and relay settings of a mailbox.
     */
    private static function routing(string $email): array
    {
        $forwarding = RepositoryFactory::getForwardingRepository();
        $bcc = RepositoryFactory::getBccRepository();
        return [
            'forwardings' => $forwarding->getForwardings($email),
            'keepCopy' => $forwarding->getKeepCopy($email),
            'senderBcc' => $bcc->getUserSenderBcc($email),
            'recipientBcc' => $bcc->getUserRecipientBcc($email),
            'relayhost' => RepositoryFactory::getRelayRepository()->getRelayhost($email),
        ];
    }

    /**
     * Reads the forwarding, BCC and relay fields that the body sets, and answers 400
     * when one is invalid. `forwardings` replaces the list; `addForwardings` and
     * `removeForwardings` change it.
     *
     * @return ?array<string, mixed> null after the error response
     */
    private static function routingFromBody(array $data, string $email): ?array
    {
        try {
            $routing = [];
            $forwardings = ApiInput::listChange(
                $data,
                ['forwardings', 'addForwardings', 'removeForwardings'],
                RepositoryFactory::getForwardingRepository()->getForwardings($email),
                ApiInput::addresses(...),
            );
            if ($forwardings !== null) {
                $routing['forwardings'] = $forwardings;
            }
            if (array_key_exists('keepCopy', $data)) {
                $routing['keepCopy'] = ApiInput::bool($data, 'keepCopy', true);
            }
            foreach (['senderBcc', 'recipientBcc'] as $field) {
                if (array_key_exists($field, $data)) {
                    $routing[$field] = ApiInput::address($data, $field);
                }
            }
            if (array_key_exists('relayhost', $data)) {
                $routing['relayhost'] = ApiInput::relayhost($data, 'relayhost');
            }
            return $routing;
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return null;
        }
    }

    /**
     * @param array<string, mixed> $routing from routingFromBody()
     */
    private static function writeRouting(string $email, string $domain, array $routing): void
    {
        $forwarding = RepositoryFactory::getForwardingRepository();
        $bcc = RepositoryFactory::getBccRepository();
        foreach ($routing as $field => $value) {
            match ($field) {
                'forwardings' => $forwarding->setForwardings($email, $domain, $value),
                'keepCopy' => $forwarding->setKeepCopy($email, $domain, $value),
                'senderBcc' => $bcc->setUserSenderBcc($email, $value),
                'recipientBcc' => $bcc->setUserRecipientBcc($email, $value),
                'relayhost' => RepositoryFactory::getRelayRepository()->setRelayhost($email, $value),
            };
        }
    }

    public static function verifyPassword(string $accountType, string $email): void
    {
        if ($accountType === 'admin') {
            ApiMiddleware::requireGlobalKey();
        } else {
            [, $domain] = self::parseEmail($email);
            if ($domain !== null) {
                ApiMiddleware::requireDomainAccess($domain);
            }
        }

        $data = ApiMiddleware::getJsonBody();
        $password = $data['password'] ?? '';

        if ($password === '') {
            ApiResponse::error('password is required');
            return;
        }

        if ($accountType === 'user') {
            [$uid, $domain] = self::parseEmail($email);
            if ($uid === null) {
                ApiResponse::error('Invalid email format');
                return;
            }

            $repo = RepositoryFactory::getUserRepository();
            $verified = $repo->verifyUserPassword($domain, $uid, $password);
            ApiResponse::success(['verified' => $verified]);
        } elseif ($accountType === 'admin') {
            $repo = RepositoryFactory::getAuthRepository();
            try {
                $result = $repo->authenticate($email, $password);
                ApiResponse::success(['verified' => $result !== null]);
            } catch (\Exception $e) {
                ApiResponse::success(['verified' => false]);
            }
        } else {
            ApiResponse::error('accountType must be "user" or "admin"');
        }
    }

    public static function delete(string $email): void
    {
        ApiMiddleware::requireWriteAccess();
        [$uid, $domain] = self::parseEmail($email);
        if ($uid === null) {
            ApiResponse::error('Invalid email format');
            return;
        }
        ApiMiddleware::requireDomainAccess($domain);

        $repo = RepositoryFactory::getUserRepository();
        if ($repo->getUser($domain, $uid) === null) {
            ApiResponse::error('User not found', 404);
            return;
        }

        $repo->deleteUser($domain, $uid, 'api');
        AccountSettingsService::deleteAccounts(["{$uid}@{$domain}"]);
        ApiResponse::deleted();
    }

    /** Body fields that are not on the user page 'general' => their user page. */
    private const FIELD_PAGES = [
        'password' => 'password',
        'forwardings' => 'forwarding',
        'addForwardings' => 'forwarding',
        'removeForwardings' => 'forwarding',
        'keepCopy' => 'forwarding',
        'senderBcc' => 'bcc',
        'recipientBcc' => 'bcc',
        'relayhost' => 'relay',
    ];

    /**
     * Returns why a domain key may not send $data, or null when it may. The global admin can
     * close user pages for the domain admins of a domain, and a domain key acts as a domain admin.
     */
    private static function closedPageError(array $data, string $domain): ?string
    {
        if (ApiMiddleware::getCurrentKey()?->isGlobal() ?? false) {
            return null;
        }
        $settings = DomainSettings::fromSettingsString(
            RepositoryFactory::getDomainRepository()->getDomain($domain)?->settings ?? ''
        );
        $servicePages = array_fill_keys(array_values(User::SERVICE_TOGGLES), 'services');
        foreach (array_keys($data) as $field) {
            $page = self::FIELD_PAGES[$field] ?? $servicePages[$field] ?? 'general';
            if (!ProfileToggles::userPageOpen($settings, false, $page)) {
                return "{$field} is on the user page '{$page}', which is disabled for domain admins";
            }
        }

        return null;
    }

    /**
     * Builds a User from a JSON body and answers 400 when a field is invalid.
     *
     * @return ?User null after the error response
     */
    private static function userFromBody(array $data): ?User
    {
        try {
            return User::fromFormData($data);
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return null;
        }
    }

    /**
     * Checks the input of a new mailbox.
     *
     * @param string $uid lowercased local part
     * @return array{0: string, 1: int}|null the error message and HTTP status, or null when the input is valid
     */
    private static function newUserError(string $uid, string $password, string $domain, bool $domainExists): ?array
    {
        if ($uid === '' || $password === '') {
            return ['uid and password are required', 400];
        }
        $address = "{$uid}@{$domain}";
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return ['Invalid uid', 400];
        }
        // A global key passes the domain access check for any domain name.
        if (!$domainExists) {
            return ['Domain not found', 404];
        }
        if (RepositoryFactory::getAliasRepository()->isAddressInUse($address)) {
            return ['Address already in use', 409];
        }

        return null;
    }

    /** @return array{?string, ?string} */
    private static function parseEmail(string $email): array
    {
        if (!str_contains($email, '@')) {
            return [null, null];
        }
        [$uid, $domain] = explode('@', $email, 2);
        return [$uid, $domain];
    }
}
