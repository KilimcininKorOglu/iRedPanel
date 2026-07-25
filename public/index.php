<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\AliasController;
use App\Controllers\AmavisdController;
use App\Controllers\AuthController;
use App\Controllers\BaseController;
use App\Controllers\DashboardController;
use App\Controllers\DeletedMailboxController;
use App\Api\AdminApiController;
use App\Api\AliasApiController;
use App\Api\ApiMiddleware;
use App\Api\DomainAliasApiController;
use App\Api\DomainApiController;
use App\Api\GreylistApiController;
use App\Api\MailingListApiController;
use App\Api\SpamPolicyApiController;
use App\Api\ThrottleApiController;
use App\Api\UserApiController;
use App\Api\WhiteBlacklistApiController;
use App\Controllers\DomainAliasController;
use App\Controllers\DomainController;
use App\Controllers\ExportController;
use App\Controllers\Fail2banController;
use App\Controllers\SpamPolicyController;
use App\Controllers\WhiteBlacklistController;
use App\Controllers\IredapdController;
use App\Controllers\NewsletterController;
use App\Controllers\LogController;
use App\Controllers\MailingListController;
use App\Controllers\SearchController;
use App\Controllers\PanelSettingsController;
use App\Controllers\SystemSettingsController;
use App\Controllers\UserController;
use App\Exceptions\BackendConnectionException;
use App\Router;
use App\TemplateEngine;

$tpl = new TemplateEngine(__DIR__ . '/../templates');
$router = new Router();

// Register routes
$router->addRoute('GET', '/', function () {
    header('Location: /dashboard');
    exit;
});

// Dashboard
$router->addRoute('GET', '/dashboard', function () use ($tpl) {
    DashboardController::dashboard($tpl);
});

// Authentication
$router->addRoute(['GET', 'POST'], '/login', function () use ($tpl) {
    AuthController::loginPage($tpl);
});

$router->addRoute('POST', '/logout', function () {
    \App\CsrfProtection::validateToken();
    AuthController::logout();
});

// Language switch (available to everyone, including the login page)
$router->addRoute('POST', '/language', function () {
    AuthController::changeLanguage();
});

// Search
$router->addRoute('GET', '/search', function () use ($tpl) {
    SearchController::search($tpl);
});

// Domain management
$router->addRoute('GET', '/domains', function () use ($tpl) {
    DomainController::domainList($tpl);
});

$router->addRoute('POST', '/domains/bulk', function () use ($tpl) {
    DomainController::bulkAction($tpl);
});

$router->addRoute(['GET', 'POST'], '/domains/create', function () use ($tpl) {
    DomainController::domainCreate($tpl);
});

$router->addRoute(['GET', 'POST'], '/domains/{domain}/edit', function (string $domain) use ($tpl) {
    DomainController::domainView($tpl, $domain, 'general');
});

$router->addRoute(['GET', 'POST'], '/domains/{domain}/settings', function (string $domain) use ($tpl) {
    DomainController::domainView($tpl, $domain, 'settings');
});

$router->addRoute('POST', '/domains/{domain}/delete', function (string $domain) use ($tpl) {
    DomainController::domainDelete($tpl, $domain);
});

// Domain alias management
$router->addRoute('GET', '/domain-aliases', function () use ($tpl) {
    DomainAliasController::aliasList($tpl);
});

$router->addRoute(['GET', 'POST'], '/domain-aliases/create', function () use ($tpl) {
    DomainAliasController::aliasCreate($tpl);
});

$router->addRoute('POST', '/domain-aliases/{aliasDomain}/delete', function (string $aliasDomain) use ($tpl) {
    DomainAliasController::aliasDelete($tpl, $aliasDomain);
});

// Mail alias management
$router->addRoute('GET', '/aliases', function () use ($tpl) {
    AliasController::list($tpl);
});

$router->addRoute('POST', '/aliases/bulk', function () use ($tpl) {
    AliasController::bulkAction($tpl);
});

$router->addRoute(['GET', 'POST'], '/aliases/create', function () use ($tpl) {
    AliasController::createForm($tpl);
});

$router->addRoute(['GET', 'POST'], '/aliases/{address}', function (string $address) use ($tpl) {
    AliasController::view($tpl, $address);
});

$router->addRoute('POST', '/aliases/{address}/delete', function (string $address) use ($tpl) {
    AliasController::delete($tpl, $address);
});

// Catch-all management
$router->addRoute(['GET', 'POST'], '/domains/{domain}/catchall', function (string $domain) use ($tpl) {
    DomainController::domainView($tpl, $domain, 'catchall');
});

// Domain BCC and relay
$router->addRoute(['GET', 'POST'], '/domains/{domain}/bcc', function (string $domain) use ($tpl) {
    DomainController::domainView($tpl, $domain, 'bcc');
});

$router->addRoute(['GET', 'POST'], '/domains/{domain}/relay', function (string $domain) use ($tpl) {
    DomainController::domainView($tpl, $domain, 'relay');
});

// Mailing list management
$router->addRoute('GET', '/mailing-lists', function () use ($tpl) {
    MailingListController::list($tpl);
});

$router->addRoute('POST', '/mailing-lists/bulk', function () use ($tpl) {
    MailingListController::bulkAction($tpl);
});

$router->addRoute(['GET', 'POST'], '/mailing-lists/create', function () use ($tpl) {
    MailingListController::createForm($tpl);
});

$router->addRoute(['GET', 'POST'], '/mailing-lists/{address}', function (string $address) use ($tpl) {
    MailingListController::view($tpl, $address);
});

$router->addRoute('POST', '/mailing-lists/{address}/delete', function (string $address) use ($tpl) {
    MailingListController::delete($tpl, $address);
});

// Admin management
$router->addRoute('GET', '/admins', function () use ($tpl) {
    AdminController::adminList($tpl);
});

$router->addRoute('POST', '/admins/bulk', function () use ($tpl) {
    AdminController::bulkAction($tpl);
});

$router->addRoute(['GET', 'POST'], '/admins/create', function () use ($tpl) {
    AdminController::adminCreate($tpl);
});

$router->addRoute(['GET', 'POST'], '/admins/{adminEmail}/{editMode}', function (string $adminEmail, string $editMode) use ($tpl) {
    AdminController::adminView($tpl, $adminEmail, $editMode);
});

$router->addRoute('POST', '/admins/{adminEmail}/delete', function (string $adminEmail) use ($tpl) {
    AdminController::adminDelete($tpl, $adminEmail);
});

// Activity log
$router->addRoute('GET', '/logs', function () use ($tpl) {
    LogController::logList($tpl);
});

$router->addRoute('POST', '/logs/delete', function () use ($tpl) {
    LogController::deleteLogs($tpl);
});

// Domain ownership verification
$router->addRoute(['GET', 'POST'], '/verify/domain-ownership', function () use ($tpl) {
    \App\Middleware::globalAdminRequired();

    $repo = \App\Repositories\RepositoryFactory::getDomainOwnershipRepository();
    $success = null;
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        \App\CsrfProtection::validateToken();
        $action = $_POST['action'] ?? '';
        $domain = $_POST['domain'] ?? '';

        if ($action === 'verify' && $domain !== '') {
            $code = $repo->getVerifyCode($domain);
            if ($code !== null && $repo->verifyDnsTxt($domain, $code)) {
                $repo->markVerified($domain);
                $success = "Domain '{$domain}' verified successfully!";
            } else {
                $error = "DNS TXT record not found for domain '{$domain}'.";
            }
        } elseif ($action === 'force_verify' && $domain !== '' && !empty($_SESSION['isGlobalAdmin'])) {
            $repo->markVerified($domain);
            $success = "Domain '{$domain}' force-verified!";
        }
    }

    $tpl->render('domainOwnership.php', [
        'pendingDomains' => $repo->getPendingDomains(),
        'success' => $success,
        'error' => $error,
    ]);
});

// System settings
$router->addRoute('GET', '/system-settings', function () use ($tpl) {
    SystemSettingsController::view($tpl);
});

// Panel settings (editable via DB)
$router->addRoute('GET', '/panel-settings', function () use ($tpl) {
    PanelSettingsController::view($tpl);
});

$router->addRoute('POST', '/panel-settings', function () use ($tpl) {
    PanelSettingsController::save($tpl);
});

// Last login tracking
$router->addRoute('GET', '/last-logins', function () use ($tpl) {
    SystemSettingsController::lastLogins($tpl);
});

// Export
$router->addRoute('GET', '/export/domain/{domain}', function (string $domain) {
    ExportController::domainExport($domain);
});

$router->addRoute('GET', '/export/admins', function () {
    ExportController::adminStats();
});

// User rename
$router->addRoute('POST', '/{domain}/users/{userUid}/rename', function (string $domain, string $userUid) use ($tpl) {
    UserController::renameUser($tpl, $domain, $userUid);
});

// Deleted mailboxes
$router->addRoute('GET', '/deleted-mailboxes', function () use ($tpl) {
    DeletedMailboxController::list($tpl);
});

$router->addRoute('POST', '/deleted-mailboxes/{id}/cancel', function (string $id) use ($tpl) {
    DeletedMailboxController::cancel($tpl, $id);
});

$router->addRoute('POST', '/deleted-mailboxes/{id}/reschedule', function (string $id) use ($tpl) {
    DeletedMailboxController::reschedule($tpl, $id);
});

// User management
$router->addRoute('GET', '/{domain}/users', function (string $domain) use ($tpl) {
    UserController::userList($tpl, $domain);
});

$router->addRoute(['GET', 'POST'], '/{domain}/users/create', function (string $domain) use ($tpl) {
    UserController::userCreateView($tpl, $domain);
});

$router->addRoute('POST', '/{domain}/users/bulk', function (string $domain) use ($tpl) {
    UserController::bulkAction($tpl, $domain);
});

$router->addRoute('POST', '/{domain}/users/{userUid}/delete', function (string $domain, string $userUid) use ($tpl) {
    UserController::userDelete($tpl, $domain, $userUid);
});

$router->addRoute(['GET', 'POST'], '/{domain}/users/{userUid}/{editMode}', function (string $domain, string $userUid, string $editMode) use ($tpl) {
    UserController::userView($tpl, $domain, $userUid, $editMode);
});

// Amavisd integration
$router->addRoute('GET', '/amavisd/quarantine', function () use ($tpl) {
    AmavisdController::quarantineList($tpl);
});

$router->addRoute('POST', '/amavisd/quarantine/{mailId}/release', function (string $mailId) use ($tpl) {
    AmavisdController::releaseMessage($tpl, $mailId);
});

$router->addRoute('POST', '/amavisd/quarantine/{mailId}/delete', function (string $mailId) use ($tpl) {
    AmavisdController::deleteMessage($tpl, $mailId);
});

$router->addRoute('GET', '/amavisd/maillog', function () use ($tpl) {
    AmavisdController::mailLog($tpl);
});

$router->addRoute('POST', '/amavisd/cleanup', function () use ($tpl) {
    AmavisdController::cleanup($tpl);
});

// Spam policy management
$router->addRoute(['GET', 'POST'], '/amavisd/spam-policy', function () use ($tpl) {
    $account = $_GET['account'] ?? '@.';
    SpamPolicyController::accountPolicy($tpl, $account);
});

$router->addRoute(['GET', 'POST'], '/amavisd/spam-policy/{account}', function (string $account) use ($tpl) {
    SpamPolicyController::accountPolicy($tpl, $account);
});

// White/blacklist management
$router->addRoute(['GET', 'POST'], '/amavisd/wblist', function () use ($tpl) {
    $account = $_GET['account'] ?? '@.';
    WhiteBlacklistController::accountList($tpl, $account);
});

$router->addRoute(['GET', 'POST'], '/amavisd/wblist/{account}', function (string $account) use ($tpl) {
    WhiteBlacklistController::accountList($tpl, $account);
});

// Fail2ban integration
$router->addRoute('GET', '/fail2ban', function () use ($tpl) {
    Fail2banController::status($tpl);
});

$router->addRoute('POST', '/fail2ban/ban', function () use ($tpl) {
    Fail2banController::banIp($tpl);
});

$router->addRoute('POST', '/fail2ban/unban', function () use ($tpl) {
    Fail2banController::unbanIp($tpl);
});

// iRedAPD integration
$router->addRoute(['GET', 'POST'], '/iredapd/throttle/{account}', function (string $account) use ($tpl) {
    IredapdController::throttleView($tpl, $account);
});

$router->addRoute(['GET', 'POST'], '/iredapd/greylist/{account}', function (string $account) use ($tpl) {
    IredapdController::greylistView($tpl, $account);
});

$router->addRoute('GET', '/iredapd/greylist-tracking', function () use ($tpl) {
    IredapdController::greylistTracking($tpl);
});

$router->addRoute(['GET', 'POST'], '/iredapd/wblist-rdns', function () use ($tpl) {
    IredapdController::wblistRdns($tpl);
});

$router->addRoute(['GET', 'POST'], '/iredapd/wblist-senderscore', function () use ($tpl) {
    IredapdController::wblistSenderScore($tpl);
});

// Newsletter (public endpoints — no authentication required)
$router->addRoute(['GET', 'POST'], '/newsletters/subscribe/{mlid}', function (string $mlid) use ($tpl) {
    NewsletterController::subscribe($tpl, $mlid);
});

$router->addRoute(['GET', 'POST'], '/newsletters/unsubscribe/{mlid}', function (string $mlid) use ($tpl) {
    NewsletterController::unsubscribe($tpl, $mlid);
});

$router->addRoute(['GET', 'POST'], '/newsletters/confirm-sub/{mlid}/{token}', function (string $mlid, string $token) use ($tpl) {
    NewsletterController::confirmSub($tpl, $mlid, $token);
});

$router->addRoute(['GET', 'POST'], '/newsletters/confirm-unsub/{mlid}/{token}', function (string $mlid, string $token) use ($tpl) {
    NewsletterController::confirmUnsub($tpl, $mlid, $token);
});

// ============================================================
// REST API v1
// ============================================================

$apiAuth = function () { ApiMiddleware::authenticate(); };

// Domains API
$router->addRoute('GET', '/api/v1/domains', function () use ($apiAuth) {
    $apiAuth(); DomainApiController::list();
});
$router->addRoute('POST', '/api/v1/domains', function () use ($apiAuth) {
    $apiAuth(); DomainApiController::create();
});
$router->addRoute('GET', '/api/v1/domains/{domain}', function (string $domain) use ($apiAuth) {
    $apiAuth(); DomainApiController::get($domain);
});
$router->addRoute('PUT', '/api/v1/domains/{domain}', function (string $domain) use ($apiAuth) {
    $apiAuth(); DomainApiController::update($domain);
});
$router->addRoute('DELETE', '/api/v1/domains/{domain}', function (string $domain) use ($apiAuth) {
    $apiAuth(); DomainApiController::delete($domain);
});

// Users API
$router->addRoute('GET', '/api/v1/domains/{domain}/users', function (string $domain) use ($apiAuth) {
    $apiAuth(); UserApiController::list($domain);
});
$router->addRoute('POST', '/api/v1/domains/{domain}/users', function (string $domain) use ($apiAuth) {
    $apiAuth(); UserApiController::create($domain);
});
$router->addRoute('GET', '/api/v1/users/{email}', function (string $email) use ($apiAuth) {
    $apiAuth(); UserApiController::get($email);
});
$router->addRoute('PUT', '/api/v1/users/{email}', function (string $email) use ($apiAuth) {
    $apiAuth(); UserApiController::update($email);
});
$router->addRoute('DELETE', '/api/v1/users/{email}', function (string $email) use ($apiAuth) {
    $apiAuth(); UserApiController::delete($email);
});

// Aliases API
$router->addRoute('GET', '/api/v1/aliases', function () use ($apiAuth) {
    $apiAuth(); AliasApiController::list();
});
$router->addRoute('POST', '/api/v1/aliases', function () use ($apiAuth) {
    $apiAuth(); AliasApiController::create();
});
$router->addRoute('GET', '/api/v1/aliases/{address}', function (string $address) use ($apiAuth) {
    $apiAuth(); AliasApiController::get($address);
});
$router->addRoute('PUT', '/api/v1/aliases/{address}', function (string $address) use ($apiAuth) {
    $apiAuth(); AliasApiController::update($address);
});
$router->addRoute('DELETE', '/api/v1/aliases/{address}', function (string $address) use ($apiAuth) {
    $apiAuth(); AliasApiController::delete($address);
});

// Mailing Lists API
$router->addRoute('GET', '/api/v1/mailing-lists', function () use ($apiAuth) {
    $apiAuth(); MailingListApiController::list();
});
$router->addRoute('POST', '/api/v1/mailing-lists', function () use ($apiAuth) {
    $apiAuth(); MailingListApiController::create();
});
$router->addRoute('GET', '/api/v1/mailing-lists/{address}', function (string $address) use ($apiAuth) {
    $apiAuth(); MailingListApiController::get($address);
});
$router->addRoute('PUT', '/api/v1/mailing-lists/{address}', function (string $address) use ($apiAuth) {
    $apiAuth(); MailingListApiController::update($address);
});
$router->addRoute('DELETE', '/api/v1/mailing-lists/{address}', function (string $address) use ($apiAuth) {
    $apiAuth(); MailingListApiController::delete($address);
});

// Admins API
$router->addRoute('GET', '/api/v1/admins', function () use ($apiAuth) {
    $apiAuth(); AdminApiController::list();
});
$router->addRoute('POST', '/api/v1/admins', function () use ($apiAuth) {
    $apiAuth(); AdminApiController::create();
});
$router->addRoute('GET', '/api/v1/admins/{email}', function (string $email) use ($apiAuth) {
    $apiAuth(); AdminApiController::get($email);
});
$router->addRoute('PUT', '/api/v1/admins/{email}', function (string $email) use ($apiAuth) {
    $apiAuth(); AdminApiController::update($email);
});
$router->addRoute('DELETE', '/api/v1/admins/{email}', function (string $email) use ($apiAuth) {
    $apiAuth(); AdminApiController::delete($email);
});

// Domain Aliases API
$router->addRoute('GET', '/api/v1/domain-aliases', function () use ($apiAuth) {
    $apiAuth(); DomainAliasApiController::list();
});
$router->addRoute('POST', '/api/v1/domain-aliases', function () use ($apiAuth) {
    $apiAuth(); DomainAliasApiController::create();
});
$router->addRoute('DELETE', '/api/v1/domain-aliases/{aliasDomain}', function (string $aliasDomain) use ($apiAuth) {
    $apiAuth(); DomainAliasApiController::delete($aliasDomain);
});

// Password Verification API
$router->addRoute('POST', '/api/v1/verify-password/{accountType}/{email}', function (string $accountType, string $email) use ($apiAuth) {
    $apiAuth(); UserApiController::verifyPassword($accountType, $email);
});

// Spam Policy API
$router->addRoute('GET', '/api/v1/spam-policy/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); SpamPolicyApiController::get($account);
});
$router->addRoute('PUT', '/api/v1/spam-policy/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); SpamPolicyApiController::update($account);
});

// White/Blacklist API
$router->addRoute('GET', '/api/v1/wblist/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); WhiteBlacklistApiController::get($account);
});
$router->addRoute('POST', '/api/v1/wblist/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); WhiteBlacklistApiController::update($account);
});
$router->addRoute('DELETE', '/api/v1/wblist/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); WhiteBlacklistApiController::delete($account);
});

// Throttle API
$router->addRoute('GET', '/api/v1/throttle/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); ThrottleApiController::get($account);
});
$router->addRoute('PUT', '/api/v1/throttle/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); ThrottleApiController::update($account);
});

// Greylist API
$router->addRoute('GET', '/api/v1/greylist/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); GreylistApiController::get($account);
});
$router->addRoute('PUT', '/api/v1/greylist/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); GreylistApiController::update($account);
});

$router->setNotFoundHandler(function () use ($tpl) {
    BaseController::page404($tpl);
});

// Dispatch request — catch backend connection errors at top level
try {
    $router->dispatch(
        $_SERVER['REQUEST_URI'] ?? '/',
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    );
} catch (BackendConnectionException $e) {
    header('Location: /logout');
    exit;
}
