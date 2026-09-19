<?php

declare(strict_types=1);

namespace App\Api;

use App\Controllers\AdminController;
use App\Models\Admin;
use App\Repositories\RepositoryFactory;
use App\Utils\FormValue;
use App\Utils\PasswordUtils;

class AdminApiController
{
    public static function list(): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = RepositoryFactory::getAdminRepository();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['perPage'] ?? 50);

        $result = $repo->getAdminsPaginated($page, $perPage);
        ApiResponse::paginated($result, fn(Admin $a) => [
            'email' => $a->username,
            'name' => $a->name,
            'isGlobalAdmin' => $a->isGlobalAdmin,
            'active' => $a->active,
        ]);
    }

    public static function get(string $email): void
    {
        ApiMiddleware::requireGlobalKey();
        $admin = RepositoryFactory::getAdminRepository()->getAdmin($email);
        if ($admin === null) {
            ApiResponse::error('Admin not found', 404);
            return;
        }
        ApiResponse::success((array) $admin);
    }

    public static function create(): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            ApiResponse::error('email and password are required');
            return;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            ApiResponse::error('email is not a valid email address');
            return;
        }

        $repo = RepositoryFactory::getAdminRepository();
        if ($repo->getAdmin($email) !== null) {
            ApiResponse::error('Admin already exists', 409);
            return;
        }
        if (AdminController::isHostedDomain(substr(strrchr($email, '@'), 1))) {
            ApiResponse::error('A standalone admin cannot use a hosted domain; make a mailbox an admin instead', 409);
            return;
        }

        $validationErrors = \App\Models\UserPassword::validate($password, $password);
        if (!empty($validationErrors)) {
            // The API has no repeat field, so every policy error is under the password key.
            ApiResponse::error('Password policy violation: ' . $validationErrors['password']);
            return;
        }

        $passwordHash = PasswordUtils::generatePasswordHash($password);
        $admin = new Admin(
            username: $email,
            name: trim((string) ($data['name'] ?? '')),
            active: (bool) ($data['active'] ?? true),
            isGlobalAdmin: (bool) ($data['isGlobalAdmin'] ?? false),
        );
        $repo->createAdmin($admin, $passwordHash);
        ApiResponse::created(['email' => $email]);
    }

    public static function update(string $email): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getAdminRepository();
        $existing = $repo->getAdmin($email);
        if ($existing === null) {
            ApiResponse::error('Admin not found', 404);
            return;
        }

        // Every field GET returns and the web pages can change: name, active, isGlobalAdmin,
        // the limits, and the password. The whole body is validated before the first write.
        $data = ApiMiddleware::getJsonBody();
        $admin = clone $existing;
        try {
            $admin->name = FormValue::text($data, 'name', $existing->name);
            $admin->active = (bool) ($data['active'] ?? $existing->active);
            $admin->isGlobalAdmin = (bool) ($data['isGlobalAdmin'] ?? $existing->isGlobalAdmin);
            $limitsChanged = $admin->applyLimitsFromJson($data);
            $passwordHash = self::passwordHashFromBody($data);
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }
        if ($existing->isGlobalAdmin && (!$admin->isGlobalAdmin || !$admin->active) && $repo->countGlobalAdmins() <= 1) {
            ApiResponse::error('Cannot disable or demote the last global admin', 403);
            return;
        }

        $repo->updateAdmin($admin);
        if ($limitsChanged) {
            $repo->updateAdminSettings($admin);
        }
        if ($passwordHash !== null) {
            $repo->updateAdminPassword($email, $passwordHash);
        }
        ApiResponse::success(['message' => 'Admin updated']);
    }

    /**
     * @return string|null the hash of the new password, or null when the body sets none
     * @throws \InvalidArgumentException when the password breaks the policy
     */
    private static function passwordHashFromBody(array $data): ?string
    {
        if (!isset($data['password'])) {
            return null;
        }
        $password = FormValue::text($data, 'password');
        $validationErrors = \App\Models\UserPassword::validate($password, $password);
        if (!empty($validationErrors)) {
            // The API has no repeat field, so every policy error is under the password key.
            throw new \InvalidArgumentException('Password policy violation: ' . $validationErrors['password']);
        }

        return PasswordUtils::generatePasswordHash($password);
    }

    public static function delete(string $email): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getAdminRepository();
        $admin = $repo->getAdmin($email);
        if ($admin === null) {
            ApiResponse::error('Admin not found', 404);
            return;
        }

        // Prevent last global admin deletion
        if ($admin->isGlobalAdmin && $repo->countGlobalAdmins() <= 1) {
            ApiResponse::error('Cannot delete the last global admin account', 403);
            return;
        }

        $repo->deleteAdmin($email);
        ApiResponse::deleted();
    }
}
