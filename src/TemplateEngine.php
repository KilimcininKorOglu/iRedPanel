<?php

declare(strict_types=1);

namespace App;

use App\Models\Settings;

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
        // A static file URL with its modification time, so a browser reloads it after an upgrade.
        $asset = function (string $path) use ($e): string {
            $file = dirname($this->templateDir) . '/public' . $path;
            return $e(is_file($file) ? $path . '?v=' . filemtime($file) : $path);
        };
        $localize = [TemplateFilters::class, 'localize'];
        $asMegabytes = [TemplateFilters::class, 'asMegabytes'];
        $tone = [BadgeTone::class, 'classes'];

        // Translation helpers: $t() returns raw text, $te() returns HTML-escaped text.
        $t = function (string $key, array $params = []): string {
            return \App\I18n\Translator::translate($key, $params);
        };
        $te = function (string $key, array $params = []) use ($e): string {
            return $e(\App\I18n\Translator::translate($key, $params));
        };
        // The help icon of a field label; empty when the label has no explanation.
        $help = [HelpText::class, 'icon'];
        $currentLocale = \App\I18n\Translator::currentLocale();
        $availableLocales = \App\I18n\Translator::availableLocales();
        $csrfToken = CsrfProtection::generateToken();
        $csrfField = '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '" />';

        // Branding and feature flags
        $settings = Settings::getInstance();
        $brand = [
            'name' => $settings->brandName,
            'logoUrl' => $settings->brandLogoUrl,
            'footerText' => $settings->brandFooterText,
            // The value goes into a <style> block. The panel settings form checks the
            // pattern, but an .env value reaches this point unchecked.
            'primaryColor' => preg_match(Settings::COLOR_PATTERN, $settings->brandPrimaryColor) === 1 ? $settings->brandPrimaryColor : '',
        ];
        $features = [
            'amavisd' => $settings->amavisdEnabled,
            ...self::amavisdPages($settings->amavisdEnabled),
            'fail2ban' => $settings->fail2banEnabled,
            'iredapd' => $settings->iredapdEnabled,
            'domainOwnership' => $settings->requireDomainOwnershipVerification,
            // Only the LDAP backend has group accounts with managed members.
            'mailLists' => $settings->backend === 'ldap',
            // Account replication stores its settings and log in the iredadmin database.
            'accountResources' => !empty($_SESSION['isGlobalAdmin'])
                && \App\Repositories\RepositoryFactory::getAccountResourceRepository()->isAvailable(),
        ];
        // A mailbox user sees only the self-service pages that the controller found open.
        $navGroups = Middleware::isSelfServiceUser()
            ? Navigation::selfServiceGroups($vars['selfServicePages'] ?? [])
            : Navigation::groups(!empty($_SESSION['isGlobalAdmin']), $features);
        $navActive = Navigation::activeHref($navGroups, (string) ($_SERVER['REQUEST_URI'] ?? '/'));
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

        // A redirect after a POST leaves its outcome here. Controllers that show
        // the message inside their own page have already taken it.
        $flashError = $_SESSION['flash_error'] ?? null;
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        include $this->templateDir . '/base.php';
    }

    /**
     * The menu flags of the Amavisd pages that a domain admin may also open, unless a
     * permission toggle of the admin closes them.
     *
     * @return array{quarantine: bool, mailLog: bool}
     */
    private static function amavisdPages(bool $enabled): array
    {
        $admin = $enabled && !empty($_SESSION['email']) && !Middleware::isSelfServiceUser();

        return [
            'quarantine' => $admin && \App\Services\AdminLimits::allows('disableManagingQuarantinedMails'),
            'mailLog' => $admin && \App\Services\AdminLimits::allows('disableViewingMailLog'),
        ];
    }
}
