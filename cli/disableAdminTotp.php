<?php

declare(strict_types=1);

/**
 * Removes the two-factor secret and the recovery codes of an admin who lost
 * the authenticator app and has no recovery code left. The admin signs in with
 * the password alone afterwards and can set two-factor up again.
 *
 * Usage: php cli/disableAdminTotp.php --email=admin@example.com
 */

require_once __DIR__ . '/bootstrap.php';

use App\Services\AdminTotp;

$options = getopt('', ['email:']);

if (empty($options['email'])) {
    echo "Usage: php cli/disableAdminTotp.php --email=admin@example.com\n";
    exit(1);
}

$email = strtolower(trim((string) $options['email']));

if (!str_contains($email, '@')) {
    echo "Invalid email address: {$email}\n";
    exit(1);
}

$totp = AdminTotp::forBackend();

if ($totp->status($email)['state'] === AdminTotp::STATE_DISABLED) {
    echo "Admin {$email} has no two-factor setup.\n";
    exit(0);
}

$totp->delete($email);

echo "Two-factor authentication removed for {$email}.\n";
