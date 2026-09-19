<?php

declare(strict_types=1);

namespace App;

use App\I18n\Translator;

/**
 * The help icon of a form field. A field label carries the key `X.y`, and its
 * explanation the key `X.y_help`. The icon opens a Bootstrap popover that says
 * what the value does and what changing it means. A field without such a key
 * gets no icon, so a page can be filled in step by step.
 */
class HelpText
{
    public const SUFFIX = '_help';

    /**
     * @param array<string,string|int> $params placeholders of both texts
     */
    public static function icon(string $labelKey, array $params = []): string
    {
        $key = $labelKey . self::SUFFIX;
        if (!Translator::has($key)) {
            return '';
        }

        return '<button type="button" class="help-icon" data-bs-toggle="popover"'
            . ' data-bs-trigger="focus" data-bs-placement="top"'
            . ' data-bs-title="' . self::escape(Translator::translate($labelKey, $params)) . '"'
            . ' data-bs-content="' . self::escape(Translator::translate($key, $params)) . '"'
            . ' aria-label="' . self::escape(Translator::translate('common.help')) . '">'
            . '<i class="bi bi-question-circle" aria-hidden="true"></i></button>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
