<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\InvalidInputException;
use App\I18n\LocaleResolver;
use App\I18n\Translator;
use App\Middleware;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Models\SpamPolicy;
use App\Models\User;
use App\Models\UserPassword;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\Services\QuarantinedMail;
use App\Services\Replication\ReplicatedAccountGuard;
use App\Services\WhiteBlacklistService;
use App\Services\SelfService;
use App\TemplateEngine;
use App\Utils\AmavisdAddress;
use App\Utils\PasswordUtils;

/**
 * Self-service pages of a mailbox user: profile, password, forwarding, white/blacklist,
 * spam policy, quarantined mail and received mail. Each POST redirects back to its page.
 */
class SelfServiceController
{
    /** Page slug => the method that applies a POST of the page. */
    private const SAVE_HANDLERS = [
        'profile' => 'saveProfile',
        'password' => 'savePassword',
        'forwarding' => 'saveForwarding',
        'wblist' => 'saveWblist',
        'spam-policy' => 'saveSpamPolicy',
        'quarantine' => 'handleQuarantine',
        'received' => 'handleReceived',
    ];

    /** Page slug => the template that renders the page. */
    private const TEMPLATES = [
        'profile' => 'selfServiceProfile.php',
        'password' => 'selfServicePassword.php',
        'forwarding' => 'selfServiceForwarding.php',
        'wblist' => 'selfServiceWblist.php',
        'spam-policy' => 'selfServiceSpamPolicy.php',
        'quarantine' => 'selfServiceMail.php',
        'received' => 'selfServiceMail.php',
        'sent' => 'selfServiceMail.php',
    ];

    /**
     * Opens the first page that the user may use, or explains that no page is open.
     */
    public static function index(TemplateEngine $tpl): void
    {
        Middleware::selfServiceRequired();
        $openPages = self::openPages();
        if ($openPages !== []) {
            header('Location: /self/' . $openPages[0]);
            exit;
        }

        $tpl->render('selfServiceEmpty.php', ['selfServicePages' => []]);
    }

    public static function page(TemplateEngine $tpl, string $page): void
    {
        Middleware::selfServiceRequired();
        if (!isset(self::TEMPLATES[$page])) {
            http_response_code(404);
            $tpl->render('page404.php', ['selfServicePages' => self::openPages()]);
            return;
        }
        $openPages = self::openPages();
        if (!in_array($page, $openPages, true)) {
            http_response_code(403);
            echo 'Access denied: this page is disabled for your domain';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset(self::SAVE_HANDLERS[$page])) {
                http_response_code(405);
                echo 'This page has no action';
                return;
            }
            self::applyPost($page);
            header('Location: ' . self::pageUrl($page));
            exit;
        }

        $tpl->render(self::TEMPLATES[$page], ['page' => $page, 'selfServicePages' => $openPages] + self::viewData($page));
    }

    /**
     * Shows one quarantined message of the user, or sends it as an .eml attachment.
     */
    public static function quarantineMail(TemplateEngine $tpl, string $mailId, bool $download): void
    {
        Middleware::selfServiceRequired();
        $openPages = self::openPages();
        if (!in_array('quarantine', $openPages, true)) {
            http_response_code(403);
            echo 'Access denied: this page is disabled for your domain';
            return;
        }

        $raw = self::readableMail($mailId);
        if ($download) {
            QuarantinedMail::download($mailId, $raw);
        }

        $tpl->render('quarantineView.php', QuarantinedMail::viewData($raw, $mailId, "/self/quarantine/{$mailId}/download") + [
            'backUrl' => '/self/quarantine',
            'selfServicePages' => $openPages,
        ]);
    }

    /**
     * The raw message, but only while it waits in the quarantine of the user.
     * Another message sends the user back to the list with a flash error.
     */
    private static function readableMail(string $mailId): string
    {
        $account = SelfService::account();
        $repo = RepositoryFactory::getAmavisdRepository();
        $waiting = in_array($account['email'], $repo->pendingQuarantineRecipients($mailId, $account['domain']), true);

        $raw = $waiting ? $repo->getQuarantinedMailText($mailId) : '';
        if ($raw === '') {
            BaseController::flashError(Translator::translate('common.msg_item_not_found'));
            header('Location: /self/quarantine');
            exit;
        }

        return $raw;
    }

    /**
     * @return list<string>
     */
    private static function openPages(): array
    {
        return SelfService::openPages(SelfService::domainSettings(), Settings::getInstance()->amavisdEnabled);
    }

    /**
     * Keeps the page number of a list page, so that the user stays on the page of the row.
     */
    private static function pageUrl(string $page): string
    {
        $number = (int) ($_GET['page'] ?? 1);

        return '/self/' . $page . ($number > 1 ? '?page=' . $number : '');
    }

    private static function applyPost(string $page): void
    {
        try {
            // An empty message means the handler already reported the outcome itself.
            $message = [self::class, self::SAVE_HANDLERS[$page]](SelfService::account());
            if ($message !== '') {
                BaseController::flashSuccess($message);
            }
        } catch (\InvalidArgumentException $e) {
            BaseController::flashError(SpamPolicyController::levelError($e));
        } catch (\Exception $e) {
            BaseController::flashError(BaseController::errorMessage($e));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function viewData(string $page): array
    {
        $email = SelfService::account()['email'];
        $number = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = Settings::getInstance()->paginationPerPage;

        return match ($page) {
            'profile' => ['user' => self::user(), 'lockedFields' => ReplicatedAccountGuard::lockedUserFields($email)],
            'forwarding' => [
                'forwardings' => RepositoryFactory::getForwardingRepository()->getForwardings($email),
                'keepCopy' => RepositoryFactory::getForwardingRepository()->getKeepCopy($email),
            ],
            'wblist' => [
                'inboundList' => RepositoryFactory::getWhiteBlacklistRepository()->getInboundList($email),
                'outboundList' => RepositoryFactory::getWhiteBlacklistRepository()->getOutboundList($email),
            ],
            'spam-policy' => ['policy' => RepositoryFactory::getSpamPolicyRepository()->getPolicy($email)],
            'quarantine' => self::mailData(RepositoryFactory::getAmavisdRepository()->getQuarantinedForUser($email, $number, $perPage)),
            'received' => self::mailData(RepositoryFactory::getAmavisdRepository()->getReceivedMail($email, $number, $perPage)),
            'sent' => self::mailData(RepositoryFactory::getAmavisdRepository()->getSentMail($email, $number, $perPage)),
            default => [],
        };
    }

    /**
     * @return array{messages: list<array<string, mixed>>, paginatedResult: PaginatedResult}
     */
    private static function mailData(PaginatedResult $result): array
    {
        return ['messages' => $result->items, 'paginatedResult' => $result];
    }

    private static function user(): User
    {
        return SelfService::user() ?? throw new \RuntimeException('Mailbox not found: ' . SelfService::account()['email']);
    }

    /**
     * @param array{uid: string, domain: string, email: string} $account
     */
    private static function saveProfile(array $account): string
    {
        $user = self::user();
        $stored = clone $user;
        $user->cn = trim((string) ($_POST['cn'] ?? ''));
        $user->language = self::postedLanguage();
        ReplicatedAccountGuard::keepUserFields($account['email'], $user, $stored);

        RepositoryFactory::getUserRepository()->updateUser($account['domain'], $user);
        ActivityLogger::logUpdate($account['domain'], $account['uid'], 'Profile updated (self-service)');
        self::applyLanguage($user->language);

        return Translator::translate('user.msg_info_updated');
    }

    /**
     * @throws InvalidInputException when the language is no locale of the panel
     */
    private static function postedLanguage(): string
    {
        $language = (string) User::validLanguage((string) ($_POST['language'] ?? ''));
        if ($language !== '' && !Translator::isSupported($language)) {
            throw new InvalidInputException("unsupported language: {$language}", 'user.msg_invalid_language');
        }

        return $language;
    }

    /**
     * Shows the pages in the saved language at once; '' returns to the default language.
     */
    private static function applyLanguage(string $language): void
    {
        $language = $language !== '' ? $language : Settings::getInstance()->defaultLanguage;
        if (!Translator::isSupported($language)) {
            return;
        }
        $_SESSION['lang'] = $language;
        LocaleResolver::persistCookie($language);
        // The success message of this request is also in the new language.
        Translator::init($language);
    }

    /**
     * The user always confirms the current password, because a self-service session
     * must not let another person take over the mailbox.
     *
     * @param array{uid: string, domain: string, email: string} $account
     */
    private static function savePassword(array $account): string
    {
        $userRepo = RepositoryFactory::getUserRepository();
        if (!$userRepo->verifyUserPassword($account['domain'], $account['uid'], (string) ($_POST['old_password'] ?? ''))) {
            throw new \RuntimeException(Translator::translate('user.msg_current_password_incorrect'));
        }

        $password = (string) ($_POST['password'] ?? '');
        $errors = UserPassword::validateLocalized($password, (string) ($_POST['password_repeat'] ?? ''), SelfService::domainSettings());
        if ($errors !== []) {
            throw new \RuntimeException(implode(' ', $errors));
        }

        $userRepo->updateUserPassword($account['domain'], $account['uid'], PasswordUtils::generatePasswordHash($password));
        ActivityLogger::logUpdate($account['domain'], $account['uid'], 'Password changed (self-service)');

        return Translator::translate('common.msg_password_updated');
    }

    /**
     * @param array{uid: string, domain: string, email: string} $account
     */
    private static function saveForwarding(array $account): string
    {
        $forwardingRepo = RepositoryFactory::getForwardingRepository();
        $forwardingRepo->setForwardings($account['email'], $account['domain'], BaseController::postedAddresses('forwardingAddresses'));
        $forwardingRepo->setKeepCopy($account['email'], $account['domain'], (bool) ($_POST['keepCopy'] ?? false));
        ActivityLogger::logUpdate($account['domain'], $account['uid'], 'Forwarding settings updated (self-service)');

        return Translator::translate('user.msg_forwarding_updated');
    }

    /**
     * @param array{uid: string, domain: string, email: string} $account
     */
    private static function saveWblist(array $account): string
    {
        $repo = RepositoryFactory::getWhiteBlacklistRepository();
        $outbound = ($_POST['direction'] ?? 'inbound') === 'outbound';
        $sender = strtolower(trim((string) ($_POST['sender'] ?? '')));

        if (($_POST['action'] ?? '') === 'remove') {
            $removed = $outbound ? $repo->removeOutboundEntry($account['email'], $sender) : $repo->removeInboundEntry($account['email'], $sender);
            if (!$removed) {
                throw new \RuntimeException(BaseController::itemError($sender, BaseController::itemNotFound()));
            }
            ActivityLogger::logDelete($account['domain'], $account['email'], "Removed entry for {$sender} (self-service)");
            return Translator::translate('wblist.msg_entry_removed');
        }

        if (!AmavisdAddress::isValidWblistAddress($sender)) {
            throw new \RuntimeException(Translator::translate('common.msg_invalid_address', ['address' => $sender]));
        }
        $wb = ($_POST['wb'] ?? 'W') === 'B' ? 'B' : 'W';
        $outbound ? $repo->addOutboundEntry($account['email'], $sender, $wb) : $repo->addInboundEntry($account['email'], $sender, $wb);
        ActivityLogger::logUpdate($account['domain'], $account['email'], "Added {$wb} entry for {$sender} (self-service)");

        return Translator::translate('wblist.msg_entry_added');
    }

    /**
     * @param array{uid: string, domain: string, email: string} $account
     */
    private static function saveSpamPolicy(array $account): string
    {
        $repo = RepositoryFactory::getSpamPolicyRepository();
        if (($_POST['action'] ?? 'save') === 'delete') {
            $repo->deletePolicy($account['email']);
            ActivityLogger::logDelete($account['domain'], $account['email'], 'Deleted spam policy (self-service)');
            return Translator::translate('spampolicy.msg_deleted');
        }

        $policy = SpamPolicy::fromFormData($_POST)->keepAdminFields($repo->getPolicy($account['email']));
        $repo->createOrUpdatePolicy($account['email'], $policy);
        ActivityLogger::logUpdate($account['domain'], $account['email'], 'Spam policy updated (self-service)');

        return Translator::translate('spampolicy.msg_updated');
    }

    /**
     * Releases or deletes the copies of the posted quarantined messages that wait for
     * the user: one message from a row button, or every selected message.
     *
     * @param array{uid: string, domain: string, email: string} $account
     */
    private static function handleQuarantine(array $account): string
    {
        $wblist = self::postedQuarantineWblist();
        if ($wblist !== null) {
            return self::wblistFromQuarantine($account, $wblist[0], $wblist[1]);
        }

        [$mailIds, $action] = self::postedQuarantineRequest();
        if (!BaseController::isValidBulkRequest($mailIds, $action, ['release', 'delete'])) {
            // isValidBulkRequest reported the missing selection or action itself.
            return '';
        }

        $release = $action === 'release';
        $done = BaseController::runBulk($mailIds, static fn (string $mailId) => self::applyQuarantine($account, $mailId, $release));
        if (count($done) !== 1) {
            // runBulk reported both the count and every failure.
            return '';
        }

        return Translator::translate($release ? 'self.msg_released' : 'self.msg_deleted');
    }

    /**
     * The posted quarantine request: the mail IDs and the action. A row button posts
     * `release` or `delete` with the mail ID as its value; the bulk menu posts `action`
     * with the selected IDs.
     *
     * @return array{0: list<string>, 1: string}
     */
    private static function postedQuarantineRequest(): array
    {
        foreach (['release', 'delete'] as $button) {
            if (is_string($_POST[$button] ?? null) && $_POST[$button] !== '') {
                return [[$_POST[$button]], $button];
            }
        }

        $selected = is_array($_POST['selected'] ?? null) ? array_values(array_filter($_POST['selected'], is_string(...))) : [];

        return [$selected, is_string($_POST['action'] ?? null) ? $_POST['action'] : ''];
    }

    /**
     * The mail ID and the kind of a posted white/blacklist button, or null when the
     * request carries none.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function postedQuarantineWblist(): ?array
    {
        foreach (['whitelist' => 'W', 'blacklist' => 'B'] as $button => $wb) {
            if (is_string($_POST[$button] ?? null) && $_POST[$button] !== '') {
                return [$_POST[$button], $wb];
            }
        }

        return null;
    }

    /**
     * Adds the sender of a quarantined message to the list of the user. The sender comes
     * from the message, and only a message that waits for the user is read.
     *
     * @param array{uid: string, domain: string, email: string} $account
     */
    private static function wblistFromQuarantine(array $account, string $mailId, string $wb): string
    {
        $addresses = RepositoryFactory::getAmavisdRepository()->quarantinedAddresses($mailId);
        if ($addresses['sender'] === '' || !in_array($account['email'], $addresses['recipients'], true)) {
            throw new \RuntimeException(Translator::translate('common.msg_item_not_found'));
        }

        WhiteBlacklistService::add($account['email'], [$addresses['sender']], $wb, 'inbound');
        ActivityLogger::logUpdate($account['domain'], $account['email'], "Added {$wb} entry for {$addresses['sender']} (self-service)");

        return Translator::translate('wblist.msg_entry_added');
    }

    /**
     * @param array{uid: string, domain: string, email: string} $account
     * @throws \Exception when the message does not wait for the user or Amavisd refuses
     */
    private static function applyQuarantine(array $account, string $mailId, bool $release): void
    {
        $repo = RepositoryFactory::getAmavisdRepository();
        if ($release) {
            $repo->releaseForRecipient($mailId, $account['email']);
            ActivityLogger::logUpdate($account['domain'], $account['email'], "Released quarantined message {$mailId} (self-service)");
            return;
        }

        $repo->deleteForRecipient($mailId, $account['email']);
        ActivityLogger::logDelete($account['domain'], $account['email'], "Deleted quarantined message {$mailId} (self-service)");
    }

    /**
     * Blacklists the sender of a received message for the inbound mail of the user.
     *
     * @param array{uid: string, domain: string, email: string} $account
     */
    private static function handleReceived(array $account): string
    {
        $sender = strtolower(trim((string) ($_POST['sender'] ?? '')));
        if (!AmavisdAddress::isValidWblistAddress($sender)) {
            throw new \RuntimeException(Translator::translate('common.msg_invalid_address', ['address' => $sender]));
        }
        RepositoryFactory::getWhiteBlacklistRepository()->addInboundEntry($account['email'], $sender, 'B');
        ActivityLogger::logUpdate($account['domain'], $account['email'], "Added B entry for {$sender} (self-service)");

        return Translator::translate('self.msg_sender_blacklisted', ['sender' => $sender]);
    }
}
