<?php

/**
 * Bulk import users from a CSV file.
 *
 * CSV format: email, password, quota_mb, display_name, mailing_lists, employee_id
 *   - email (REQUIRED): user@domain.com
 *   - password (REQUIRED): plain text or {SCHEME}hash
 *   - quota_mb (optional): mailbox quota in MB (integer)
 *   - display_name (optional): user's display name
 *   - mailing_lists (optional): colon-separated list addresses (list1@domain.com:list2@domain.com)
 *   - employee_id (optional): employee ID string
 *
 * Usage: php cli/importUsers.php /path/to/users.csv
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Models\User;
use App\Repositories\RepositoryFactory;
use App\Services\MailingListService;
use App\Utils\PasswordUtils;

if ($argc < 2) {
    echo "Usage: php cli/importUsers.php <csv_file>\n";
    echo "\nCSV format: email, password, quota_mb, display_name, mailing_lists, employee_id\n";
    exit(1);
}

$csvFile = $argv[1];

if (!file_exists($csvFile) || !is_readable($csvFile)) {
    echo "Error: Cannot read file: {$csvFile}\n";
    exit(1);
}

$userRepo = RepositoryFactory::getUserRepository();

if (!$userRepo->supportsCreateUser()) {
    echo "Error: User creation is not supported for the current backend.\n";
    exit(1);
}

$handle = fopen($csvFile, 'r');
if ($handle === false) {
    echo "Error: Cannot open file: {$csvFile}\n";
    exit(1);
}

$lineNumber = 0;
$created = 0;
$skipped = 0;
$errors = 0;

while (($line = fgets($handle)) !== false) {
    $lineNumber++;
    $line = trim($line);

    // Skip empty lines and comments
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    $fields = array_map('trim', str_getcsv($line, escape: ''));
    $email = $fields[0] ?? '';
    $password = $fields[1] ?? '';
    $quotaMb = (int) ($fields[2] ?? 0);
    $displayName = $fields[3] ?? '';
    $mailingLists = $fields[4] ?? '';
    $employeeId = $fields[5] ?? '';

    if ($email === '' || !str_contains($email, '@')) {
        echo "  Line {$lineNumber}: SKIP — invalid email: {$email}\n";
        $skipped++;
        continue;
    }

    if ($password === '') {
        echo "  Line {$lineNumber}: SKIP — no password for: {$email}\n";
        $skipped++;
        continue;
    }

    [$uid, $domain] = explode('@', $email, 2);

    // Check if user already exists
    $existingUser = $userRepo->getUser($domain, $uid);
    if ($existingUser !== null) {
        echo "  Line {$lineNumber}: SKIP — already exists: {$email}\n";
        $skipped++;
        continue;
    }

    try {
        // Generate password hash
        if (str_starts_with($password, '{')) {
            $passwordHash = $password; // Already a hash
        } else {
            $passwordHash = PasswordUtils::generatePasswordHash($password);
        }

        // An imported mailbox starts active, as one created in the web form or the API.
        $user = new User(
            uid: $uid,
            accountStatus: true,
            cn: $displayName,
            mailQuota: $quotaMb,
            employeeNumber: $employeeId,
        );

        $userRepo->createUser($domain, $user, $passwordHash);
        echo "  Line {$lineNumber}: CREATED — {$email}\n";
        $created++;

        // Subscribe to mailing lists if specified. The subscribers live in mlmmj only.
        if ($mailingLists !== '') {
            $lists = array_filter(array_map('trim', explode(':', $mailingLists)));
            foreach ($lists as $listAddr) {
                try {
                    MailingListService::addSubscribers($listAddr, [$email]);
                    echo "    Subscribed to: {$listAddr}\n";
                } catch (\Exception $e) {
                    echo "    Warning: Could not subscribe to {$listAddr}: {$e->getMessage()}\n";
                }
            }
        }
    } catch (\Exception $e) {
        echo "  Line {$lineNumber}: ERROR — {$email}: {$e->getMessage()}\n";
        $errors++;
    }
}

fclose($handle);

echo "\nImport complete: {$created} created, {$skipped} skipped, {$errors} errors.\n";
