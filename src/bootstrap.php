<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

define('APP_VERSION', \Composer\InstalledVersions::getRootPackage()['pretty_version'] ?? 'dev');

use App\Models\Settings;
use Dotenv\Dotenv;

// Load environment variables from .env or .env.prod
$dotenv = Dotenv::createImmutable(__DIR__ . '/..', ['.env', '.env.prod']);
$dotenv->safeLoad();

// Start session
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']),
    'samesite' => 'Lax',
]);
session_start();

// Validate settings and check required extensions
try {
    $settings = Settings::getInstance();

    if ($settings->backend === 'ldap' && !extension_loaded('ldap')) {
        throw new \RuntimeException("LDAP backend selected but ext-ldap is not installed");
    }
    if ($settings->backend === 'mysql' && !extension_loaded('pdo_mysql')) {
        throw new \RuntimeException("MySQL backend selected but ext-pdo_mysql is not installed");
    }
    if ($settings->backend === 'pgsql' && !extension_loaded('pdo_pgsql')) {
        throw new \RuntimeException("PostgreSQL backend selected but ext-pdo_pgsql is not installed");
    }
} catch (\RuntimeException $e) {
    error_log("Settings validation failed: " . $e->getMessage());
    http_response_code(500);
    echo "Configuration error. Check server logs.";
    exit(1);
}
