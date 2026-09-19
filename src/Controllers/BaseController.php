<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\BackendConnectionException;
use App\I18n\Translator;
use App\TemplateEngine;

class BaseController
{
    /** Actions that the list pages offer in their bulk action menu. */
    public const BULK_ACTIONS = ['enable', 'disable', 'delete'];

    /**
     * Stores an error message that the next rendered page shows.
     */
    public static function flashError(string $message): void
    {
        $_SESSION['flash_error'] = $message;
    }

    /**
     * Stores a success message that the next rendered page shows.
     */
    public static function flashSuccess(string $message): void
    {
        $_SESSION['flash_success'] = $message;
    }

    /**
     * Stores the success message for a deleted item.
     */
    public static function flashDeleted(string $item): void
    {
        self::flashSuccess(Translator::translate('common.msg_item_deleted', ['item' => $item]));
    }

    /**
     * Applies $apply to every selected item and stores the outcome as flash
     * messages. $apply throws to report a failure for one item; the other
     * items still run.
     *
     * @return list<string> the items that succeeded
     */
    public static function runBulk(array $items, callable $apply): array
    {
        $done = [];
        $failed = [];
        foreach (array_filter($items, 'is_string') as $item) {
            try {
                $apply($item);
                $done[] = $item;
            } catch (\Exception $e) {
                $failed[] = self::itemFailure($item, $e);
            }
        }

        if ($failed !== []) {
            self::flashError(Translator::translate('common.msg_bulk_failed', ['items' => implode(', ', $failed)]));
        }
        if ($done !== []) {
            self::flashSuccess(Translator::translate('common.msg_bulk_done', ['count' => count($done)]));
        }
        return $done;
    }

    /**
     * Stores the failure of an action on one item as the flash error.
     */
    public static function flashItemError(string $item, \Throwable $e): void
    {
        self::flashError(Translator::translate('common.msg_bulk_failed', ['items' => self::itemFailure($item, $e)]));
    }

    private static function itemFailure(string $item, \Throwable $e): string
    {
        return "{$item} (" . self::errorMessage($e) . ')';
    }

    /**
     * Returns the exception for an item that an action cannot find.
     */
    public static function itemNotFound(): \RuntimeException
    {
        return new \RuntimeException(Translator::translate('common.msg_item_not_found'));
    }
    /**
     * Returns the flash text for a failed operation.
     *
     * Database errors carry SQL and schema details, so they go to the server
     * log and the admin sees a generic message. Other exceptions are the
     * repositories' own validation messages and are shown as they are.
     */
    public static function errorMessage(\Throwable $e): string
    {
        if ($e instanceof BackendConnectionException) {
            error_log('Backend connection error: ' . $e->getMessage());
            return Translator::translate('misc.backend_down_body');
        }
        if (!$e instanceof \PDOException) {
            return $e->getMessage();
        }
        error_log('Database error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return Translator::translate('common.msg_operation_failed');
    }

    /**
     * Renders the 404 error page.
     */
    public static function page404(TemplateEngine $tpl): void
    {
        http_response_code(404);
        $tpl->render('page404.php');
    }

    /**
     * Renders the 403 page for a form with a missing or invalid CSRF token.
     */
    public static function pageCsrf(TemplateEngine $tpl): void
    {
        http_response_code(403);
        $tpl->render('pageCsrf.php');
    }

    /**
     * Renders the 503 page when the mail server backend is not reachable.
     */
    public static function pageBackendDown(TemplateEngine $tpl, BackendConnectionException $e): void
    {
        error_log('Backend connection error: ' . $e->getMessage());
        http_response_code(503);
        $tpl->render('pageBackendDown.php');
    }
}
