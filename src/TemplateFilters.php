<?php

declare(strict_types=1);

namespace App;

use App\I18n\Translator;

class TemplateFilters
{
    /**
     * Converts status/boolean values to icon indicators with a translated label.
     */
    public static function localize(string|bool $data): string
    {
        // (string) true is "1" and (string) false is "", which match no arm.
        $data = is_bool($data) ? ($data ? 'true' : 'false') : strtolower($data);
        return match ($data) {
            'yes', 'active', 'true' => self::indicator('bi-check-circle-fill text-success', 'common.yes'),
            'no', 'disabled', 'false' => self::indicator('bi-x-circle text-body-secondary', 'common.no'),
            default => htmlspecialchars($data, ENT_QUOTES, 'UTF-8'),
        };
    }

    private static function indicator(string $classes, string $labelKey): string
    {
        $label = htmlspecialchars(Translator::translate($labelKey), ENT_QUOTES, 'UTF-8');

        return '<i class="bi ' . $classes . '" role="img" aria-label="' . $label . '" title="' . $label . '"></i>';
    }

    /**
     * Formats a megabyte value for display (0 = unlimited).
     */
    public static function asMegabytes(string|int $data): string
    {
        $value = (int) $data;
        if ($value <= 0) {
            return '0';
        }
        return number_format($value, 0, '.', ',');
    }
}
