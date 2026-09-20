<?php

/**
 * Send notification emails to users about quarantined messages.
 *
 * Queries amavisd database for quarantined messages and sends HTML email
 * notifications to users who have opted in (mailbox.settings contains 'quar_notify:yes').
 *
 * Usage: php cli/notifyQuarantinedRecipients.php [--force-all]
 *
 * Options:
 *   --force-all  Notify all users, not just those with quar_notify:yes
 *
 * Recommended cron: 0 *\/6 * * * php /path/to/cli/notifyQuarantinedRecipients.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Exceptions\MailDeliveryException;
use App\Models\Settings;
use App\Services\Mailer;

$forceAll = in_array('--force-all', $argv ?? [], true);
$settings = Settings::getInstance();

if (!$settings->amavisdEnabled) {
    echo "Amavisd integration is not enabled.\n";
    exit(1);
}

if (!in_array($settings->backend, ['mysql', 'pgsql'], true)) {
    echo "This tool supports only the mysql and pgsql backends.\n";
    exit(1);
}

try {
    $vmailPdo = getVmailPdo($settings);
    // Optional: without it every run reports all quarantined mail again.
    $iredadminPdo = getIredadminPdo($settings);
    $mailer = Mailer::fromSettings();
} catch (\Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

// Get last notification time
$lastNotifyTime = 0;
if ($iredadminPdo !== null) {
    $stmt = $iredadminPdo->prepare("SELECT v FROM tracking WHERE k = 'quarantine_notify_time' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row !== false) {
        $lastNotifyTime = (int) $row['v'];
    }
}

// Find users to notify
$users = [];
if ($forceAll) {
    $stmt = $vmailPdo->query("SELECT username FROM mailbox WHERE active = 1");
    while ($row = $stmt->fetch()) {
        $users[] = $row['username'];
    }
} else {
    $stmt = $vmailPdo->query("SELECT username, settings FROM mailbox WHERE active = 1");
    while ($row = $stmt->fetch()) {
        $userSettings = $row['settings'] ?? '';
        if (str_contains($userSettings, 'quar_notify:yes')) {
            $users[] = $row['username'];
        }
    }
}

if (empty($users)) {
    echo "No users to notify.\n";
    exit(0);
}

echo "Found " . count($users) . " users to check.\n";

$notified = 0;
$failed = 0;
$quarDays = $settings->amavisdRemoveQuarantinedInDays;

$amavisdRepo = \App\Repositories\RepositoryFactory::getAmavisdRepository();

foreach ($users as $userEmail) {
    // Quarantined messages since the last notification
    $messages = $amavisdRepo->getQuarantinedForRecipient($userEmail, $lastNotifyTime);

    if (empty($messages)) {
        continue;
    }

    // Build notification email
    $body = buildNotificationBody($userEmail, $messages, $quarDays);

    $msgCount = count($messages);
    $subject = "Quarantine notification: {$msgCount} message(s)";

    try {
        $mailer->send($userEmail, $subject, $body, true);
        echo "  Notified: {$userEmail} ({$msgCount} messages)\n";
        $notified++;
    } catch (MailDeliveryException $e) {
        echo "  Failed: {$userEmail}: {$e->getMessage()}\n";
        $failed++;
    }
}

// Update last notification time
if ($iredadminPdo !== null && $notified > 0) {
    $now = time();
    $stmt = $iredadminPdo->prepare("SELECT 1 FROM tracking WHERE k = 'quarantine_notify_time' LIMIT 1");
    $stmt->execute();

    if ($stmt->fetch() !== false) {
        $iredadminPdo->prepare("UPDATE tracking SET v = :val WHERE k = 'quarantine_notify_time'")
            ->execute(['val' => (string) $now]);
    } else {
        $iredadminPdo->prepare("INSERT INTO tracking (k, v) VALUES ('quarantine_notify_time', :val)")
            ->execute(['val' => (string) $now]);
    }
}

echo "\nDone: {$notified} users notified, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);

// --- Helper functions ---

function buildNotificationBody(string $userEmail, array $messages, int $quarDays): string
{
    $rows = '';
    foreach ($messages as $msg) {
        $date = date('Y-m-d H:i', (int) ($msg['time_num'] ?? 0));
        $from = htmlspecialchars($msg['from_addr'] ?? '', ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars($msg['subject'] ?? '(no subject)', ENT_QUOTES, 'UTF-8');
        $score = htmlspecialchars((string) ($msg['spam_level'] ?? ''), ENT_QUOTES, 'UTF-8');
        $rows .= "<tr><td>{$date}</td><td>{$from}</td><td>{$subject}</td><td>{$score}</td></tr>\n";
    }

    $count = count($messages);
    $user = htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8');

    return <<<HTML
    <html>
    <body style="font-family: sans-serif; padding: 20px;">
    <h2>Quarantine Notification</h2>
    <p>Dear {$user},</p>
    <p>You have <strong>{$count}</strong> quarantined message(s). These messages will be automatically deleted after {$quarDays} days.</p>
    <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%;">
    <thead><tr><th>Date</th><th>From</th><th>Subject</th><th>Spam Score</th></tr></thead>
    <tbody>{$rows}</tbody>
    </table>
    <p>Please log in to the mail administration panel to review or release these messages.</p>
    </body>
    </html>
    HTML;
}

function getVmailPdo(Settings $settings): \PDO
{
    return $settings->backend === 'pgsql'
        ? \App\Repositories\Pgsql\PgsqlConnection::getInstance()->getPdo()
        : \App\Repositories\Mysql\MysqlConnection::getInstance()->getPdo();
}

function getIredadminPdo(Settings $settings): ?\PDO
{
    return $settings->backend === 'pgsql'
        ? \App\Repositories\Pgsql\IredadminPgsqlConnection::getInstance()->getPdo()
        : \App\Repositories\Mysql\IredadminConnection::getInstance()->getPdo();
}
