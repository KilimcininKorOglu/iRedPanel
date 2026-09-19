<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\Exceptions\MailDeliveryException;
use App\I18n\Translator;
use App\Models\MailingList;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\Mailer;
use App\Services\MailingListService;
use App\Services\NewsletterConfirmations;
use App\TemplateEngine;

/**
 * Public newsletter pages. A visitor asks to subscribe or unsubscribe, gets
 * a confirmation link by mail, and the confirmation changes the mlmmj
 * subscribers. Only active lists with the newsletter flag are served.
 */
class NewsletterController
{
    public static function subscribe(TemplateEngine $tpl, string $mlid): void
    {
        self::request($tpl, $mlid, 'subscribe');
    }

    public static function unsubscribe(TemplateEngine $tpl, string $mlid): void
    {
        self::request($tpl, $mlid, 'unsubscribe');
    }

    public static function confirmSub(TemplateEngine $tpl, string $mlid, string $token): void
    {
        self::confirm($tpl, $mlid, $token, 'subscribe');
    }

    public static function confirmUnsub(TemplateEngine $tpl, string $mlid, string $token): void
    {
        self::confirm($tpl, $mlid, $token, 'unsubscribe');
    }

    private static function request(TemplateEngine $tpl, string $mlid, string $kind): void
    {
        $ml = self::newsletterList($mlid);
        if ($ml === null) {
            self::error($tpl, 404, 'newsletter.msg_list_not_found');
            return;
        }

        $success = null;
        $error = null;
        $email = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            [$success, $error] = self::handleRequest($ml, $kind, $email);
        }

        $tpl->render('newsletterSubscribe.php', [
            'ml' => $ml,
            'action' => $kind,
            'success' => $success,
            'error' => $error,
            'email' => $error !== null ? $email : '',
        ]);
    }

    /**
     * Stores the request and mails the confirmation link.
     *
     * @return array{0: ?string, 1: ?string} success and error message
     */
    private static function handleRequest(MailingList $ml, string $kind, string $email): array
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [null, Translator::translate('newsletter.msg_invalid_email')];
        }

        $settings = Settings::getInstance();
        $token = NewsletterConfirmations::create($ml->mlid, $ml->address, $email, $kind, $settings->newsletterExpireHours);
        if ($token === null) {
            // The same request is pending and its mail was just sent.
            return [Translator::translate('newsletter.msg_confirmation_sent'), null];
        }

        try {
            self::sendConfirmation($ml, $kind, $email, $token);
        } catch (MailDeliveryException $e) {
            error_log("Newsletter confirmation for {$email} to {$ml->address} failed: " . $e->getMessage());
            NewsletterConfirmations::deleteToken($ml->mlid, $token);
            return [null, Translator::translate('newsletter.msg_send_failed')];
        }

        return [Translator::translate('newsletter.msg_confirmation_sent'), null];
    }

    /**
     * @throws MailDeliveryException when the public URL or SMTP is not configured, or delivery fails
     */
    private static function sendConfirmation(MailingList $ml, string $kind, string $email, string $token): void
    {
        $settings = Settings::getInstance();
        if ($settings->publicUrl === '') {
            throw new MailDeliveryException('IREDPANEL_PUBLIC_URL is not set');
        }

        $path = $kind === 'subscribe' ? 'confirm-sub' : 'confirm-unsub';
        $params = [
            'list' => self::listLabel($ml),
            'email' => $email,
            'link' => "{$settings->publicUrl}/newsletters/{$path}/{$ml->mlid}/{$token}",
            'hours' => $settings->newsletterExpireHours,
        ];

        Mailer::fromSettings()->send(
            $email,
            Translator::translate("newsletter.mail_subject_{$kind}", $params),
            Translator::translate("newsletter.mail_body_{$kind}", $params),
        );
    }

    private static function confirm(TemplateEngine $tpl, string $mlid, string $token, string $kind): void
    {
        $ml = self::newsletterList($mlid);
        $record = $ml !== null ? NewsletterConfirmations::find($mlid, $token, $kind) : null;
        if ($ml === null || $record === null) {
            self::error($tpl, 404, 'newsletter.msg_invalid_token');
            return;
        }

        $label = self::listLabel($ml);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $tpl->render('newsletterConfirm.php', [
                'message' => Translator::translate("newsletter.confirm_{$kind}_hint", ['list' => $label]),
                'showConfirmButton' => true,
            ]);
            return;
        }

        CsrfProtection::validateToken();
        if ($kind === 'subscribe') {
            MailingListService::addSubscribers($ml->address, [$record['subscriber']]);
        } else {
            MailingListService::removeSubscribers($ml->address, [$record['subscriber']]);
        }
        NewsletterConfirmations::delete($record['id']);

        $tpl->render('newsletterConfirm.php', [
            'message' => Translator::translate("newsletter.msg_{$kind}d", ['list' => $label]),
        ]);
    }

    /**
     * Returns the list only when it is active and open for newsletter subscription.
     */
    private static function newsletterList(string $mlid): ?MailingList
    {
        if ($mlid === '') {
            return null;
        }
        $ml = RepositoryFactory::getMailingListRepository()->getMailingListById($mlid);

        return $ml !== null && $ml->active && $ml->isNewsletter ? $ml : null;
    }

    private static function listLabel(MailingList $ml): string
    {
        return $ml->name !== '' ? "{$ml->name} <{$ml->address}>" : $ml->address;
    }

    private static function error(TemplateEngine $tpl, int $status, string $key): void
    {
        http_response_code($status);
        $tpl->render('newsletterError.php', ['message' => Translator::translate($key)]);
    }
}
