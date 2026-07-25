<?php

declare(strict_types=1);

namespace App;

class TemplateEngine
{
    private string $templateDir;

    public function __construct(string $templateDir)
    {
        $this->templateDir = rtrim($templateDir, '/');
    }

    /**
     * Renders a child template wrapped in the base layout.
     *
     * The child template should set $pageTitle and output body HTML.
     * The engine captures the body via output buffering and includes base.php.
     */
    public function render(string $template, array $vars = []): void
    {
        $vars['session'] = $_SESSION ?? [];

        // Make helper functions available in template scope
        $e = function (mixed $value): string {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };
        $localize = [TemplateFilters::class, 'localize'];
        $asMegabytes = [TemplateFilters::class, 'asMegabytes'];

        // Translation helpers: $t() returns raw text, $te() returns HTML-escaped text.
        $t = function (string $key, array $params = []): string {
            return \App\I18n\Translator::translate($key, $params);
        };
        $te = function (string $key, array $params = []) use ($e): string {
            return $e(\App\I18n\Translator::translate($key, $params));
        };
        $currentLocale = \App\I18n\Translator::currentLocale();
        $availableLocales = \App\I18n\Translator::availableLocales();
        $csrfToken = CsrfProtection::generateToken();
        $csrfField = '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '" />';

        // Branding and feature flags
        $settings = \App\Models\Settings::getInstance();
        $brand = [
            'name' => $settings->brandName,
            'logoUrl' => $settings->brandLogoUrl,
            'footerText' => $settings->brandFooterText,
            'primaryColor' => $settings->brandPrimaryColor,
        ];
        $features = [
            'amavisd' => $settings->amavisdEnabled,
            'fail2ban' => $settings->fail2banEnabled,
            'iredapd' => $settings->iredapdEnabled,
        ];
        $passwordPolicy = json_encode([
            'minLength' => $settings->passwordMinLength,
            'uppercase' => $settings->passwordIncludesUppercase,
            'lowercase' => $settings->passwordIncludesLowercase,
            'numbers' => $settings->passwordIncludesNumbers,
            'special' => $settings->passwordIncludesSpecialChars,
        ]);

        // EXTR_SKIP prevents overwriting local scope variables ($e, $localize, etc.)
        // Controller-provided keys become template variables (e.g., $domain, $users)
        extract($vars, EXTR_SKIP);

        $pageTitle = '';

        ob_start();
        include $this->templateDir . '/' . $template;
        $bodyContent = ob_get_clean();

        include $this->templateDir . '/base.php';
    }
}
