<?php

declare(strict_types=1);

namespace App\Controllers;

use App\I18n\Translator;
use App\TemplateEngine;

class BaseController
{
    /**
     * Returns the flash text for a failed operation.
     *
     * Database errors carry SQL and schema details, so they go to the server
     * log and the admin sees a generic message. Other exceptions are the
     * repositories' own validation messages and are shown as they are.
     */
    public static function errorMessage(\Throwable $e): string
    {
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
}
