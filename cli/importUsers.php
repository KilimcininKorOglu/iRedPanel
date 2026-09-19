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

use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\User;
use App\Models\UserPassword;
use App\Repositories\RepositoryFactory;
use App\Services\MailingListService;
use App\Utils\PasswordUtils;

/**
 * Returns why the address cannot get a new mailbox, as the web form and the REST API check it.
 */
function addressError(string $uid, string $email, ?Domain $domain): ?string
{
    if ($uid === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return "invalid email: {$email}";
    }
    if ($domain === null) {
        return "domain not found: {$email}";
    }
    if (RepositoryFactory::getUserRepository()->getUser($domain->domainName, $uid) !== null) {
        return "already exists: {$email}";
    }
    if (RepositoryFactory::getAliasRepository()->isAddressInUse($email)) {
        return "address already in use: {$email}";
    }

    return null;
}

/**
 * An empty quota field takes the default user quota of the domain; 0 means unlimited.
 *
 * @throws \InvalidArgumentException when the field is not a whole number of 0 or more
 */
function mailQuota(string $field, DomainSettings $settings): int
{
    if ($field === '') {
        return max(0, $settings->defaultUserQuota);
    }

    return User::validMailQuota($field);
}

/**
 * A value that starts with '{' is taken as a {SCHEME} hash; a plain password must meet the domain policy.
 *
 * @throws \InvalidArgumentException when the plain password breaks the policy
 */
function passwordHash(string $password, DomainSettings $settings): string
{
    if (str_starts_with($password, '{')) {
        return $password;
    }
    $validationErrors = UserPassword::validate($password, $password, $settings);
    if ($validationErrors !== []) {
        throw new \InvalidArgumentException('password policy violation: ' . $validationErrors['password']);
    }

    return PasswordUtils::generatePasswordHash($password);
}

/**
 * Checks one CSV line and builds its mailbox.
 *
 * @param string[] $fields
 * @return array{User, string, string}|string the user, its domain and its password hash, or why the line is skipped
 */
function prepareLine(array $fields): array|string
{
    $email = strtolower($fields[0] ?? '');
    $password = $fields[1] ?? '';
    if ($password === '') {
        return "no password for: {$email}";
    }
    [$uid, $domainName] = array_pad(explode('@', $email, 2), 2, '');
    $domain = $domainName === '' ? null : RepositoryFactory::getDomainRepository()->getDomain($domainName);
    $error = addressError($uid, $email, $domain);
    if ($error !== null) {
        return $error;
    }

    try {
        $settings = DomainSettings::fromSettingsString($domain->settings);
        // An imported mailbox starts active, as one created in the web form or the API.
        $user = new User(
            uid: $uid,
            accountStatus: true,
            mailQuota: mailQuota($fields[2] ?? '', $settings),
            cn: $fields[3] ?? '',
            employeeNumber: $fields[5] ?? '',
        );
        $limitError = $domain->newMailboxError($user->mailQuota);
        if ($limitError !== null) {
            return "{$email}: {$limitError->getMessage()}";
        }

        return [$user, $domainName, passwordHash($password, $settings)];
    } catch (\InvalidArgumentException $e) {
        return "{$email}: {$e->getMessage()}";
    }
}

/**
 * Subscribes the new mailbox to the lists of the mailing_lists field. The subscribers live in mlmmj only.
 */
function subscribe(string $email, string $mailingLists): void
{
    foreach (array_filter(array_map('trim', explode(':', $mailingLists))) as $listAddr) {
        try {
            MailingListService::addSubscribers($listAddr, [$email]);
            echo "    Subscribed to: {$listAddr}\n";
        } catch (\Exception $e) {
            echo "    Warning: Could not subscribe to {$listAddr}: {$e->getMessage()}\n";
        }
    }
}

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
    $prepared = prepareLine($fields);
    if (is_string($prepared)) {
        echo "  Line {$lineNumber}: SKIP: {$prepared}\n";
        $skipped++;
        continue;
    }

    [$user, $domain, $passwordHash] = $prepared;
    $email = "{$user->uid}@{$domain}";
    try {
        $userRepo->createUser($domain, $user, $passwordHash);
        echo "  Line {$lineNumber}: CREATED: {$email}\n";
        $created++;
        subscribe($email, $fields[4] ?? '');
    } catch (\Exception $e) {
        echo "  Line {$lineNumber}: ERROR: {$email}: {$e->getMessage()}\n";
        $errors++;
    }
}

fclose($handle);

echo "\nImport complete: {$created} created, {$skipped} skipped, {$errors} errors.\n";
