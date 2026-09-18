<?php

declare(strict_types=1);

/**
 * Bulk password update from a CSV file.
 * CSV format: email,newpassword (one per line, no header).
 *
 * Usage: php cli/bulkPasswordUpdate.php --file=passwords.csv
 */

require_once __DIR__ . '/bootstrap.php';

use App\Repositories\RepositoryFactory;
use App\Utils\PasswordUtils;

$options = getopt('', ['file:']);

if (empty($options['file'])) {
    echo "Usage: php cli/bulkPasswordUpdate.php --file=passwords.csv\n";
    exit(1);
}

$file = $options['file'];
if (!file_exists($file)) {
    echo "File not found: {$file}\n";
    exit(1);
}

$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$userRepo = RepositoryFactory::getUserRepository();
$success = 0;
$failed = 0;

foreach ($lines as $lineNum => $line) {
    $parts = str_getcsv($line, escape: '');
    if (count($parts) < 2) {
        echo "Skipping line " . ($lineNum + 1) . ": invalid format\n";
        $failed++;
        continue;
    }

    [$email, $password] = $parts;
    $email = trim($email);
    $password = trim($password);

    if (!str_contains($email, '@')) {
        echo "Skipping line " . ($lineNum + 1) . ": invalid email '{$email}'\n";
        $failed++;
        continue;
    }

    [$uid, $domain] = explode('@', $email, 2);

    try {
        $passwordHash = PasswordUtils::generatePasswordHash($password);
        $userRepo->updateUserPassword($domain, $uid, $passwordHash);
        echo "Updated: {$email}\n";
        $success++;
    } catch (\Exception $e) {
        echo "Failed: {$email} — {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\nDone. Success: {$success}, Failed: {$failed}\n";
