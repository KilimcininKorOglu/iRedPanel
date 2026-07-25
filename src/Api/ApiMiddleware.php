<?php

declare(strict_types=1);

namespace App\Api;

use App\Models\ApiKey;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;

class ApiMiddleware
{
    private static ?ApiKey $currentKey = null;

    /**
     * Authenticates the API request via X-API-Key header.
     * Supports database-backed keys with RBAC and legacy env-based key.
     */
    public static function authenticate(): void
    {
        $settings = Settings::getInstance();

        if (!$settings->apiEnabled) {
            ApiResponse::error('API is not enabled', 403);
            exit;
        }

        $providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($providedKey === '') {
            ApiResponse::error('Unauthorized: X-API-Key header required', 401);
            exit;
        }

        // Try database-backed key first
        $apiKeyRepo = RepositoryFactory::getApiKeyRepository();
        $apiKeyRepo->ensureTableExists();
        $keyRecord = $apiKeyRepo->findByKey($providedKey);

        if ($keyRecord !== null) {
            self::$currentKey = $keyRecord;
        } elseif ($settings->apiKey !== '' && hash_equals($settings->apiKey, $providedKey)) {
            // Legacy env-based key: treated as global with full access
            self::$currentKey = new ApiKey(
                id: 0,
                apiKey: $providedKey,
                label: 'Legacy env key',
                role: 'global',
                readOnly: false,
                active: true,
            );
        } else {
            ApiResponse::error('Invalid API key', 401);
            exit;
        }

        // IP whitelist (applies to all keys) — reuses Middleware CIDR logic
        $allowedIps = $settings->apiAllowedIps;
        if ($allowedIps !== '') {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!$clientIp || !\App\Middleware::isIpAllowed($clientIp, $allowedIps)) {
                ApiResponse::error('IP not allowed', 403);
                exit;
            }
        }
    }

    /**
     * Returns the authenticated API key context.
     */
    public static function getCurrentKey(): ?ApiKey
    {
        return self::$currentKey;
    }

    /**
     * Requires the current key to have global admin role.
     */
    public static function requireGlobalKey(): void
    {
        if (self::$currentKey === null || !self::$currentKey->isGlobal()) {
            ApiResponse::error('This operation requires a global API key', 403);
            exit;
        }
    }

    /**
     * Requires the current key to have access to the given domain.
     */
    public static function requireDomainAccess(string $domain): void
    {
        if (self::$currentKey === null || !self::$currentKey->hasDomainAccess($domain)) {
            ApiResponse::error('API key does not have access to this domain', 403);
            exit;
        }
    }

    /**
     * Requires the current key to allow write operations.
     */
    public static function requireWriteAccess(): void
    {
        if (self::$currentKey === null || !self::$currentKey->canWrite()) {
            ApiResponse::error('This API key is read-only', 403);
            exit;
        }
    }

    /** Maximum accepted API request body size in bytes. */
    private const MAX_BODY_BYTES = 2 * 1024 * 1024;

    /** Maximum accepted JSON nesting depth. */
    private const MAX_JSON_DEPTH = 64;

    public static function getJsonBody(): array
    {
        // Reject oversized bodies early via the declared Content-Length.
        $declaredLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($declaredLength > self::MAX_BODY_BYTES) {
            ApiResponse::error('Request body too large', 413);
            exit;
        }

        // Read at most MAX_BODY_BYTES + 1 so an under-declared length cannot bypass the cap.
        $raw = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if ($raw === '' || $raw === false) {
            return [];
        }
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            ApiResponse::error('Request body too large', 413);
            exit;
        }

        $data = json_decode($raw, true, self::MAX_JSON_DEPTH);
        if (json_last_error() !== JSON_ERROR_NONE) {
            ApiResponse::error('Malformed JSON body: ' . json_last_error_msg(), 400);
            exit;
        }
        // Reject scalars, null, and root-level JSON arrays; require a JSON object.
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            ApiResponse::error('Request body must be a JSON object', 400);
            exit;
        }

        return $data;
    }
}
