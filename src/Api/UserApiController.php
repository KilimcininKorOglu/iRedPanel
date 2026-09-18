<?php

declare(strict_types=1);

namespace App\Api;

use App\Models\DomainSettings;
use App\Models\User;
use App\Repositories\RepositoryFactory;
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

        ApiResponse::success((array) $user);
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

        $domainObj = RepositoryFactory::getDomainRepository()->getDomain($domain);
        $domainSettings = DomainSettings::fromSettingsString($domainObj?->settings ?? '');

        // A new mailbox starts active unless the request sets accountStatus.
        $defaults = ['accountStatus' => true];
        if ($domainSettings->defaultUserQuota > 0) {
            $defaults['mailQuota'] = $domainSettings->defaultUserQuota;
        }
        $user = User::fromFormData($data + $defaults);
        $password = $data['password'] ?? '';

        if ($user->uid === '' || $password === '') {
            ApiResponse::error('uid and password are required');
            return;
        }

        // Enforce domain limits
        if ($domainObj !== null) {
            if ($domainObj->mailboxes > 0 && $domainObj->currentUserCount >= $domainObj->mailboxes) {
                ApiResponse::error("Domain mailbox limit reached ({$domainObj->currentUserCount}/{$domainObj->mailboxes})", 403);
                return;
            }
            if ($domainObj->maxQuota > 0 && $user->mailQuota > $domainObj->maxQuota) {
                ApiResponse::error("User quota exceeds domain maximum ({$domainObj->maxQuota} MB)", 403);
                return;
            }
            if ($domainObj->quota > 0 && ($domainObj->currentQuotaUsed + $user->mailQuota) > $domainObj->quota) {
                ApiResponse::error("Total domain quota would be exceeded", 403);
                return;
            }
        }

        $validationErrors = \App\Models\UserPassword::validate($password, $password, $domainSettings);
        if (!empty($validationErrors)) {
            ApiResponse::error('Password policy violation: ' . implode(', ', $validationErrors));
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

        if (isset($data['password'])) {
            $domainSettings = DomainSettings::fromSettingsString(
                RepositoryFactory::getDomainRepository()->getDomain($domain)?->settings ?? ''
            );
            $validationErrors = \App\Models\UserPassword::validate($data['password'], $data['password'], $domainSettings);
            if (!empty($validationErrors)) {
                ApiResponse::error('Password policy violation: ' . implode(', ', $validationErrors));
                return;
            }
            $passwordHash = PasswordUtils::generatePasswordHash($data['password']);
            $repo->updateUserPassword($domain, $uid, $passwordHash);
        }

        $user = User::fromFormData(array_merge((array) $existing, $data));
        $user->uid = $uid;

        // Enforce domain quota limits on update
        $domainObj = RepositoryFactory::getDomainRepository()->getDomain($domain);
        if ($domainObj !== null) {
            if ($domainObj->maxQuota > 0 && $user->mailQuota > $domainObj->maxQuota) {
                ApiResponse::error("User quota exceeds domain maximum ({$domainObj->maxQuota} MB)", 403);
                return;
            }
            if ($domainObj->quota > 0) {
                $quotaDiff = $user->mailQuota - $existing->mailQuota;
                if ($quotaDiff > 0 && ($domainObj->currentQuotaUsed + $quotaDiff) > $domainObj->quota) {
                    ApiResponse::error("Total domain quota would be exceeded", 403);
                    return;
                }
            }
        }

        $repo->updateUser($domain, $user);
        ApiResponse::success(['message' => 'User updated']);
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
        ApiResponse::deleted();
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
