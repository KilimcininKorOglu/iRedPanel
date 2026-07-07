<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "This script can only be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/bootstrap.php';

use App\Repositories\Mysql\AmavisdConnection;

$options = getopt('', ['output:', 'since::']);

if (empty($options['output'])) {
    echo "Usage: php cli/dumpQuarantinedMails.php --output=/path/to/dir [--since=YYYY-MM-DD]\n";
    echo "\nExports quarantined emails from Amavisd database to .eml files.\n";
    exit(1);
}

$outputDir = rtrim($options['output'], '/');
if (!is_dir($outputDir)) {
    echo "Error: Output directory does not exist: {$outputDir}\n";
    exit(1);
}

$conn = AmavisdConnection::getInstance();
if (!$conn->isAvailable()) {
    echo "Error: Amavisd database not available. Check IREDPANEL_AMAVISD_* settings.\n";
    exit(1);
}

$pdo = $conn->getPdo();
$where = '1=1';
$params = [];

if (!empty($options['since'])) {
    $where .= ' AND m.time_iso >= :since';
    $params['since'] = $options['since'];
}

$stmt = $pdo->prepare(
    "SELECT q.mail_id, q.mail_text, m.from_addr, m.subject, m.time_iso
     FROM quarantine q
     JOIN msgs m ON q.mail_id = m.mail_id
     WHERE {$where}
     ORDER BY m.time_num DESC"
);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

$count = 0;
while ($row = $stmt->fetch()) {
    $mailId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $row['mail_id']);
    $filename = "spam-{$mailId}.eml";
    $mailText = $row['mail_text'] ?? '';

    if ($mailText !== '') {
        file_put_contents("{$outputDir}/{$filename}", $mailText);
        $count++;
    }
}

echo "Exported {$count} quarantined message(s) to {$outputDir}\n";
