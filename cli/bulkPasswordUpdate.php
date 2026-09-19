<?php

declare(strict_types=1);

/**
 * Bulk password update from a CSV file.
 * CSV format: email,newpassword (one per line, no header).
 *
 * Usage: php cli/bulkPasswordUpdate.php --file=passwords.csv
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\DomainSettings;
use App\Models\UserPassword;
use App\Repositories\RepositoryFactory;
use App\Utils\PasswordUtils;

/**
 * Checks one line as the password page and the REST API do: the mailbox exists
 * and the password meets the password policy of its domain.
 *
 * @return array{string, string, string}|string the uid, the domain and the password hash, or why the line is skipped
 */
function preparePassword(string $email, string $password): array|string
{
    [$uid, $domain] = array_pad(explode('@', strtolower($email), 2), 2, '');
    if ($uid === '' || $domain === '') {
        return "invalid email '{$email}'";
    }
    if (RepositoryFactory::getUserRepository()->getUser($domain, $uid) === null) {
        return "not found: {$email}";
    }

    $settings = DomainSettings::fromSettingsString(RepositoryFactory::getDomainRepository()->getDomain($domain)?->settings ?? '');
    $validationErrors = UserPassword::validate($password, $password, $settings);
    if ($validationErrors !== []) {
        return "{$email}: password policy violation: {$validationErrors['password']}";
    }

    return [$uid, $domain, PasswordUtils::generatePasswordHash($password)];
}

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
    $parts = array_map('trim', str_getcsv($line, escape: ''));
    if (count($parts) < 2) {
        echo "Skipping line " . ($lineNum + 1) . ": invalid format\n";
        $failed++;
        continue;
    }

    $prepared = preparePassword($parts[0], $parts[1]);
    if (is_string($prepared)) {
        echo "Skipping line " . ($lineNum + 1) . ": {$prepared}\n";
        $failed++;
        continue;
    }

    [$uid, $domain, $passwordHash] = $prepared;
    try {
        $userRepo->updateUserPassword($domain, $uid, $passwordHash);
        echo "Updated: {$uid}@{$domain}\n";
        $success++;
    } catch (\Exception $e) {
        echo "Failed: {$uid}@{$domain}: {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\nDone. Success: {$success}, Failed: {$failed}\n";
