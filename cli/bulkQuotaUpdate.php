<?php

declare(strict_types=1);

/**
 * Bulk quota update from a CSV file.
 * CSV format: email,quotaMb (one per line, no header).
 *
 * Usage: php cli/bulkQuotaUpdate.php --file=quotas.csv
 */

require_once __DIR__ . '/bootstrap.php';

use App\Repositories\RepositoryFactory;

$options = getopt('', ['file:']);

if (empty($options['file'])) {
    echo "Usage: php cli/bulkQuotaUpdate.php --file=quotas.csv\n";
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

    [$email, $quotaMb] = $parts;
    $email = trim($email);
    $quotaMb = (int) trim($quotaMb);

    if (!str_contains($email, '@')) {
        echo "Skipping line " . ($lineNum + 1) . ": invalid email '{$email}'\n";
        $failed++;
        continue;
    }

    [$uid, $domain] = explode('@', $email, 2);

    try {
        $user = $userRepo->getUser($domain, $uid);
        if ($user === null) {
            echo "Not found: {$email}\n";
            $failed++;
            continue;
        }

        $user->mailQuota = $quotaMb;
        $userRepo->updateUser($domain, $user);
        echo "Updated: {$email} → {$quotaMb} MB\n";
        $success++;
    } catch (\Exception $e) {
        echo "Failed: {$email} — {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\nDone. Success: {$success}, Failed: {$failed}\n";
