<?php

declare(strict_types=1);

namespace App\Api;

use App\Models\Admin;
use App\Repositories\RepositoryFactory;
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

        $validationErrors = \App\Models\UserPassword::validate($password, $password);
        if (!empty($validationErrors)) {
            ApiResponse::error('Password policy violation: ' . implode(', ', $validationErrors));
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
        $admin = $repo->getAdmin($email);
        if ($admin === null) {
            ApiResponse::error('Admin not found', 404);
            return;
        }

        $data = ApiMiddleware::getJsonBody();

        if (isset($data['password'])) {
            $validationErrors = \App\Models\UserPassword::validate($data['password'], $data['password']);
            if (!empty($validationErrors)) {
                ApiResponse::error('Password policy violation: ' . implode(', ', $validationErrors));
                return;
            }
            $passwordHash = PasswordUtils::generatePasswordHash($data['password']);
            $repo->updateAdminPassword($email, $passwordHash);
        }

        if (isset($data['active'])) {
            // Prevent disabling the last global admin
            if (!$data['active'] && $admin->isGlobalAdmin && $repo->countGlobalAdmins() <= 1) {
                ApiResponse::error('Cannot disable the last global admin', 403);
                return;
            }
            $repo->enableDisableAdmin($email, (bool) $data['active']);
        }

        ApiResponse::success(['message' => 'Admin updated']);
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
