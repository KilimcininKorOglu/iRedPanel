<?php

declare(strict_types=1);

namespace App\Controllers;

use App\I18n\Translator;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\TemplateEngine;

class NewsletterController
{
    public static function subscribe(TemplateEngine $tpl, string $mlid): void
    {
        $ml = RepositoryFactory::getMailingListRepository()->getMailingList($mlid);
        if ($ml === null) {
            $tpl->render('newsletterError.php', ['message' => 'Mailing list not found.']);
            return;
        }

        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = Translator::translate('newsletter.msg_invalid_email');
            } else {
                $token = bin2hex(random_bytes(16));
                $expireHours = (int) (Settings::getInstance()->env('IREDPANEL_NEWSLETTER_EXPIRE_HOURS', '24') ?? 24);
                $expired = time() + ($expireHours * 3600);

                self::saveConfirmation($mlid, $ml->address, $email, 'subscribe', $token, $expired);
                $success = Translator::translate('newsletter.msg_confirmation_sent');
            }
        }

        $tpl->render('newsletterSubscribe.php', [
            'ml' => $ml,
            'action' => 'subscribe',
            'success' => $success,
            'error' => $error,
        ]);
    }

    public static function unsubscribe(TemplateEngine $tpl, string $mlid): void
    {
        $ml = RepositoryFactory::getMailingListRepository()->getMailingList($mlid);
        if ($ml === null) {
            $tpl->render('newsletterError.php', ['message' => 'Mailing list not found.']);
            return;
        }

        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = Translator::translate('newsletter.msg_invalid_email');
            } else {
                $token = bin2hex(random_bytes(16));
                $expireHours = (int) (Settings::getInstance()->env('IREDPANEL_NEWSLETTER_EXPIRE_HOURS', '24') ?? 24);
                $expired = time() + ($expireHours * 3600);

                self::saveConfirmation($mlid, $ml->address, $email, 'unsubscribe', $token, $expired);
                $success = Translator::translate('newsletter.msg_confirmation_sent');
            }
        }

        $tpl->render('newsletterSubscribe.php', [
            'ml' => $ml,
            'action' => 'unsubscribe',
            'success' => $success,
            'error' => $error,
        ]);
    }

    public static function confirmSub(TemplateEngine $tpl, string $mlid, string $token): void
    {
        $record = self::findConfirmation($mlid, $token, 'subscribe');

        if ($record === null) {
            $tpl->render('newsletterError.php', ['message' => 'Invalid or expired confirmation token.']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $aliasRepo = RepositoryFactory::getAliasRepository();
            $aliasRepo->addAliasMember($record['mail'], $record['subscriber']);
            self::deleteConfirmation($record['id']);

            $tpl->render('newsletterConfirm.php', [
                'message' => 'You have been successfully subscribed to ' . $record['mail'],
            ]);
            return;
        }

        $tpl->render('newsletterConfirm.php', [
            'message' => 'Click the button below to confirm your subscription to ' . $record['mail'],
            'showConfirmButton' => true,
        ]);
    }

    public static function confirmUnsub(TemplateEngine $tpl, string $mlid, string $token): void
    {
        $record = self::findConfirmation($mlid, $token, 'unsubscribe');

        if ($record === null) {
            $tpl->render('newsletterError.php', ['message' => 'Invalid or expired confirmation token.']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $aliasRepo = RepositoryFactory::getAliasRepository();
            $aliasRepo->removeAliasMember($record['mail'], $record['subscriber']);
            self::deleteConfirmation($record['id']);

            $tpl->render('newsletterConfirm.php', [
                'message' => 'You have been successfully unsubscribed from ' . $record['mail'],
            ]);
            return;
        }

        $tpl->render('newsletterConfirm.php', [
            'message' => 'Click the button below to confirm your unsubscription from ' . $record['mail'],
            'showConfirmButton' => true,
        ]);
    }

    private static function saveConfirmation(string $mlid, string $mail, string $subscriber, string $kind, string $token, int $expired): void
    {
        $pdo = self::getPdo();
        if ($pdo === null) {
            return;
        }

        try {
            // Remove existing pending tokens for this combination to prevent flooding
            $delStmt = $pdo->prepare(
                "DELETE FROM newsletter_subunsub_confirms WHERE mlid = :mlid AND subscriber = :subscriber AND kind = :kind"
            );
            $delStmt->execute(['mlid' => $mlid, 'subscriber' => $subscriber, 'kind' => $kind]);

            $stmt = $pdo->prepare(
                "INSERT INTO newsletter_subunsub_confirms (mlid, mail, subscriber, kind, token, expired)
                 VALUES (:mlid, :mail, :subscriber, :kind, :token, :expired)"
            );
            $stmt->execute([
                'mlid' => $mlid,
                'mail' => $mail,
                'subscriber' => $subscriber,
                'kind' => $kind,
                'token' => $token,
                'expired' => $expired,
            ]);
        } catch (\PDOException $e) {
            error_log("Newsletter confirmation save failed: " . $e->getMessage());
        }
    }

    private static function findConfirmation(string $mlid, string $token, string $kind): ?array
    {
        $pdo = self::getPdo();
        if ($pdo === null) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM newsletter_subunsub_confirms
                 WHERE mlid = :mlid AND token = :token AND kind = :kind AND expired > :now
                 LIMIT 1"
            );
            $stmt->execute(['mlid' => $mlid, 'token' => $token, 'kind' => $kind, 'now' => time()]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row !== false ? $row : null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    private static function deleteConfirmation(int $id): void
    {
        $pdo = self::getPdo();
        if ($pdo === null) {
            return;
        }

        $pdo->prepare("DELETE FROM newsletter_subunsub_confirms WHERE id = :id")->execute(['id' => $id]);
    }

    private static function getPdo(): ?\PDO
    {
        $settings = Settings::getInstance();
        $backend = $settings->backend;

        try {
            if ($backend === 'pgsql') {
                return \App\Repositories\Pgsql\IredadminPgsqlConnection::getInstance()->getPdo();
            }
            return \App\Repositories\Mysql\IredadminConnection::getInstance()->getPdo();
        } catch (\Exception $e) {
            return null;
        }
    }
}
