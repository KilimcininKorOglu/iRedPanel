<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\Mysql\IredadminConnection;
use App\Repositories\Pgsql\IredadminPgsqlConnection;
use App\Services\ActivityLogger;
use App\TemplateEngine;

class LogController
{
    private static function getConnection(): object
    {
        return Settings::getInstance()->backend === 'pgsql'
            ? IredadminPgsqlConnection::getInstance()
            : IredadminConnection::getInstance();
    }

    /**
     * Displays the activity log list page.
     */
    public static function logList(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $conn = self::getConnection();
        if (!$conn->isAvailable()) {
            $tpl->render('logList.php', [
                'logs' => [],
                'paginatedResult' => new PaginatedResult([], 0, 1, 50),
                'filterDomain' => '',
                'filterEvent' => '',
                'loggingEnabled' => false,
            ]);
            return;
        }

        $pdo = $conn->getPdo();
        $settings = Settings::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;
        $offset = ($page - 1) * $perPage;
        $filterDomain = $_GET['domain'] ?? '';
        $filterEvent = $_GET['event'] ?? '';

        // Build WHERE clause
        $where = '1=1';
        $params = [];

        if ($filterDomain !== '') {
            $where .= ' AND domain = :domain';
            $params['domain'] = $filterDomain;
        }

        if ($filterEvent === ActivityLogger::EVENT_ENABLE) {
            // Earlier panel versions wrote "enable" instead of iRedAdmin's "active".
            $where .= " AND event IN ('active', 'enable')";
        } elseif ($filterEvent !== '') {
            $where .= ' AND event = :event';
            $params['event'] = $filterEvent;
        }

        // Count
        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM log WHERE {$where}");
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetch()['total'];

        // Fetch
        $stmt = $pdo->prepare(
            "SELECT id, admin, ip, domain, username, event, loglevel, msg, timestamp
             FROM log WHERE {$where}
             ORDER BY timestamp DESC
             LIMIT :perPage OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $logs = $stmt->fetchAll();

        $tpl->render('logList.php', [
            'logs' => $logs,
            'paginatedResult' => new PaginatedResult($logs, $totalCount, $page, $perPage),
            'filterDomain' => $filterDomain,
            'filterEvent' => $filterEvent,
            'loggingEnabled' => true,
        ]);
    }

    /**
     * Handles log entry deletion (POST only).
     */
    public static function deleteLogs(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $conn = self::getConnection();
        if (!$conn->isAvailable()) {
            BaseController::flashError(Translator::translate('log.not_configured'));
            header("Location: /logs");
            exit;
        }

        $pdo = $conn->getPdo();
        $ids = $_POST['ids'] ?? [];

        if (isset($_POST['deleteAll'])) {
            $deleted = (int) $pdo->exec("DELETE FROM log");
            ActivityLogger::log('delete', '', '', 'All log entries deleted');
            BaseController::flashSuccess(Translator::translate('common.msg_bulk_done', ['count' => $deleted]));
        } elseif (!empty($ids) && is_array($ids)) {
            $safeIds = array_map('intval', $ids);
            $placeholders = implode(',', array_fill(0, count($safeIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM log WHERE id IN ({$placeholders})");
            $stmt->execute($safeIds);
            ActivityLogger::log('delete', '', '', 'Deleted ' . $stmt->rowCount() . ' log entries');
            BaseController::flashSuccess(Translator::translate('common.msg_bulk_done', ['count' => $stmt->rowCount()]));
        }

        header("Location: /logs");
        exit;
    }
}
