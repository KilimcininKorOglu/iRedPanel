<?php

declare(strict_types=1);

namespace App\Repositories;

interface AuthRepositoryInterface
{
    /**
     * Authenticates a user by email and password.
     * Throws \Exception on failure (invalid credentials, not admin, etc.).
     */
    public function authenticate(string $email, string $password): bool;

    /**
     * Whether the given email is a global admin.
     */
    public function isGlobalAdmin(string $email): bool;

    /**
     * Returns domains managed by the given admin email.
     *
     * @return string[]
     */
    public function getManagedDomains(string $email): array;

    /**
     * Returns the admin's stored preferred locale (e.g. 'en_US'), or an empty
     * string when none is set. The caller falls back to the resolver default.
     */
    public function getLanguage(string $email): string;

    /**
     * Persists the admin's preferred locale. No-op backends that cannot store
     * a preference should report false from supportsLanguagePersistence().
     */
    public function setLanguage(string $email, string $locale): void;

    /**
     * Whether this backend can persist a per-admin language preference.
     */
    public function supportsLanguagePersistence(): bool;
}
