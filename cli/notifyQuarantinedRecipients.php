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

use App\Models\Settings;

$forceAll = in_array('--force-all', $argv ?? [], true);
$settings = Settings::getInstance();

if (!$settings->amavisdEnabled) {
    echo "Amavisd integration is not enabled.\n";
    exit(1);
}

// Get vmail DB connection
$vmailPdo = getVmailPdo($settings);
if ($vmailPdo === null) {
    echo "Cannot connect to vmail database.\n";
    exit(1);
}

// Get amavisd DB connection
$amavisdPdo = getAmavisdPdo($settings);
if ($amavisdPdo === null) {
    echo "Cannot connect to amavisd database.\n";
    exit(1);
}

// Get iredadmin DB connection for tracking
$iredadminPdo = getIredadminPdo($settings);

// Get last notification time
$lastNotifyTime = 0;
if ($iredadminPdo !== null) {
    try {
        $stmt = $iredadminPdo->prepare("SELECT value FROM tracking WHERE k = 'quarantine_notify_time' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row !== false) {
            $lastNotifyTime = (int) $row['value'];
        }
    } catch (\PDOException $e) {
        // tracking table may not exist
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
$quarDays = $settings->amavisdRemoveQuarantinedInDays;

foreach ($users as $userEmail) {
    // Find user's maddr ID
    $stmt = $amavisdPdo->prepare("SELECT id FROM maddr WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $userEmail]);
    $maddrRow = $stmt->fetch();

    if ($maddrRow === false) {
        continue;
    }

    $maddrId = (int) $maddrRow['id'];

    // Find quarantined messages since last notification
    $stmt = $amavisdPdo->prepare(
        "SELECT m.mail_id, m.subject, m.from_addr, m.spam_level, m.time_num
         FROM msgs m
         JOIN msgrcpt mr ON m.mail_id = mr.mail_id
         WHERE mr.rid = :rid AND m.quar_type = 'Q' AND m.time_num > :since
         ORDER BY m.time_num DESC
         LIMIT 100"
    );
    $stmt->execute(['rid' => $maddrId, 'since' => $lastNotifyTime]);

    $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($messages)) {
        continue;
    }

    // Build notification email
    $body = buildNotificationBody($userEmail, $messages, $quarDays);

    $subject = "Quarantine notification: " . count($messages) . " message(s)";
    $headers = "From: postmaster@" . explode('@', $userEmail, 2)[1] . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: iRedPanel Quarantine Notifier\r\n";

    $msgCount = count($messages);
    if (@mail($userEmail, $subject, $body, $headers)) {
        echo "  Notified: {$userEmail} ({$msgCount} messages)\n";
        $notified++;
    } else {
        echo "  Failed: {$userEmail}\n";
    }
}

// Update last notification time
if ($iredadminPdo !== null && $notified > 0) {
    try {
        $now = time();
        $stmt = $iredadminPdo->prepare("SELECT 1 FROM tracking WHERE k = 'quarantine_notify_time' LIMIT 1");
        $stmt->execute();

        if ($stmt->fetch() !== false) {
            $iredadminPdo->prepare("UPDATE tracking SET value = :val WHERE k = 'quarantine_notify_time'")
                ->execute(['val' => (string) $now]);
        } else {
            $iredadminPdo->prepare("INSERT INTO tracking (k, value) VALUES ('quarantine_notify_time', :val)")
                ->execute(['val' => (string) $now]);
        }
    } catch (\PDOException $e) {
        // tracking table may not exist
    }
}

echo "\nDone: {$notified} users notified.\n";

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

function getVmailPdo(Settings $settings): ?\PDO
{
    try {
        if ($settings->backend === 'pgsql') {
            return \App\Repositories\Pgsql\PgsqlConnection::getInstance()->getPdo();
        }
        if ($settings->backend === 'mysql') {
            return \App\Repositories\Mysql\MysqlConnection::getInstance()->getPdo();
        }
    } catch (\Exception $e) {
        // Connection failed
    }
    return null;
}

function getAmavisdPdo(Settings $settings): ?\PDO
{
    try {
        if ($settings->backend === 'pgsql') {
            return \App\Repositories\Pgsql\AmavisdPgsqlConnection::getInstance()->getPdo();
        }
        return \App\Repositories\Mysql\AmavisdConnection::getInstance()->getPdo();
    } catch (\Exception $e) {
        // Connection failed
    }
    return null;
}

function getIredadminPdo(Settings $settings): ?\PDO
{
    try {
        if ($settings->backend === 'pgsql') {
            return \App\Repositories\Pgsql\IredadminPgsqlConnection::getInstance()->getPdo();
        }
        return \App\Repositories\Mysql\IredadminConnection::getInstance()->getPdo();
    } catch (\Exception $e) {
        // Connection failed
    }
    return null;
}
