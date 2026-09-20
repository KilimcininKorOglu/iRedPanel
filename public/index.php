<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Controllers\AccountLookupController;
use App\Controllers\AccountResourceController;
use App\Controllers\AdminController;
use App\Controllers\AliasController;
use App\Controllers\AmavisdController;
use App\Controllers\AuthController;
use App\Controllers\BaseController;
use App\Controllers\CompatibilityController;
use App\Controllers\DashboardController;
use App\Controllers\DeletedMailboxController;
use App\Api\AdminApiController;
use App\Api\AliasApiController;
use App\Api\ApiMiddleware;
use App\Api\ApiResponse;
use App\Api\DomainAliasApiController;
use App\Api\DomainApiController;
use App\Api\GreylistApiController;
use App\Api\LdifApiController;
use App\Api\MailListApiController;
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
use App\Controllers\MailListController;
use App\Controllers\MailingListController;
use App\Controllers\SearchController;
use App\Controllers\SelfServiceController;
use App\Controllers\PanelSettingsController;
use App\Controllers\SystemSettingsController;
use App\Controllers\UserController;
use App\Exceptions\BackendConnectionException;
use App\Exceptions\CsrfTokenException;
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

// Self-service pages of mailbox users. Register before the /{domain}/users routes.
$router->addRoute('GET', '/self', function () use ($tpl) {
    SelfServiceController::index($tpl);
});

$router->addRoute(['GET', 'POST'], '/self/{page}', function (string $page) use ($tpl) {
    SelfServiceController::page($tpl, $page);
});

// Search
$router->addRoute('GET', '/search', function () use ($tpl) {
    SearchController::search($tpl);
});

// Account picker lookup for the web forms
$router->addRoute('GET', '/ajax/accounts', function () {
    AccountLookupController::accounts();
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

$router->addRoute('POST', '/aliases/{address}/rename', function (string $address) use ($tpl) {
    AliasController::rename($tpl, $address);
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

$router->addRoute(['GET', 'POST'], '/domains/{domain}/admins', function (string $domain) use ($tpl) {
    DomainController::domainView($tpl, $domain, 'admins');
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

// Register before the {editMode} route: the router takes the first matching path.
$router->addRoute('POST', '/admins/{adminEmail}/delete', function (string $adminEmail) use ($tpl) {
    AdminController::adminDelete($tpl, $adminEmail);
});

$router->addRoute(['GET', 'POST'], '/admins/{adminEmail}/{editMode}', function (string $adminEmail, string $editMode) use ($tpl) {
    AdminController::adminView($tpl, $adminEmail, $editMode);
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
                $success = \App\I18n\Translator::translate('domainownership.msg_verified', ['domain' => $domain]);
            } else {
                $error = \App\I18n\Translator::translate('domainownership.msg_txt_missing', ['domain' => $domain]);
            }
        } elseif ($action === 'force_verify' && $domain !== '' && !empty($_SESSION['isGlobalAdmin'])) {
            $repo->markVerified($domain);
            $success = \App\I18n\Translator::translate('domainownership.msg_force_verified', ['domain' => $domain]);
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

// iRedMail compatibility list
$router->addRoute('GET', '/compatibility', function () use ($tpl) {
    CompatibilityController::view($tpl);
});

// Account resources: replication from Active Directory and Samba AD
$router->addRoute('GET', '/account-resources', function () use ($tpl) {
    AccountResourceController::list($tpl);
});

$router->addRoute('POST', '/account-resources', function () use ($tpl) {
    AccountResourceController::create($tpl);
});

$router->addRoute('GET', '/account-resources/{id}', function (string $id) use ($tpl) {
    AccountResourceController::edit($tpl, $id);
});

$router->addRoute('POST', '/account-resources/{id}', function (string $id) use ($tpl) {
    AccountResourceController::save($tpl, $id);
});

$router->addRoute('POST', '/account-resources/{id}/test', function (string $id) use ($tpl) {
    AccountResourceController::test($tpl, $id);
});

$router->addRoute('POST', '/account-resources/{id}/replicate', function (string $id) use ($tpl) {
    AccountResourceController::replicate($tpl, $id);
});

$router->addRoute('POST', '/account-resources/{id}/toggle', function (string $id) use ($tpl) {
    AccountResourceController::toggle($tpl, $id);
});

$router->addRoute('POST', '/account-resources/{id}/delete', function (string $id) use ($tpl) {
    AccountResourceController::delete($tpl, $id);
});

$router->addRoute('GET', '/account-resources/{id}/log', function (string $id) use ($tpl) {
    AccountResourceController::log($tpl, $id);
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

// Mail lists (LDAP backend only)
$router->addRoute('GET', '/mail-lists', function () use ($tpl) {
    MailListController::list($tpl);
});

$router->addRoute(['GET', 'POST'], '/mail-lists/create', function () use ($tpl) {
    MailListController::createForm($tpl);
});

$router->addRoute('POST', '/mail-lists/{address}/delete', function (string $address) use ($tpl) {
    MailListController::delete($tpl, $address);
});

$router->addRoute(['GET', 'POST'], '/mail-lists/{address}', function (string $address) use ($tpl) {
    MailListController::view($tpl, $address);
});

// Export
$router->addRoute('GET', '/export/domain/{domain}', function (string $domain) use ($tpl) {
    ExportController::domainExport($tpl, $domain);
});

$router->addRoute('GET', '/export/admins', function () {
    ExportController::adminStats();
});

$router->addRoute('GET', '/export/ldif', function () use ($tpl) {
    ExportController::treeLdif($tpl);
});

$router->addRoute('GET', '/export/ldif/{domain}', function (string $domain) use ($tpl) {
    ExportController::domainLdif($tpl, $domain);
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

$router->addRoute(['GET', 'POST'], '/iredapd/greylist-domains', function () use ($tpl) {
    IredapdController::greylistDomains($tpl);
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
$router->addRoute('PUT', '/api/v1/domains/{domain}/admins', function (string $domain) use ($apiAuth) {
    $apiAuth(); DomainApiController::admins($domain);
});

// Users API
$router->addRoute('GET', '/api/v1/domains/{domain}/users', function (string $domain) use ($apiAuth) {
    $apiAuth(); UserApiController::list($domain);
});
$router->addRoute('POST', '/api/v1/domains/{domain}/users', function (string $domain) use ($apiAuth) {
    $apiAuth(); UserApiController::create($domain);
});
$router->addRoute('PUT', '/api/v1/domains/{domain}/users', function (string $domain) use ($apiAuth) {
    $apiAuth(); UserApiController::bulkUpdate($domain);
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
$router->addRoute('POST', '/api/v1/users/{email}/rename', function (string $email) use ($apiAuth) {
    $apiAuth(); UserApiController::rename($email);
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
$router->addRoute('POST', '/api/v1/aliases/{address}/rename', function (string $address) use ($apiAuth) {
    $apiAuth(); AliasApiController::rename($address);
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
$router->addRoute('GET', '/api/v1/mailing-lists/{address}/subscribers', function (string $address) use ($apiAuth) {
    $apiAuth(); MailingListApiController::subscribers($address);
});
$router->addRoute('POST', '/api/v1/mailing-lists/{address}/subscribers', function (string $address) use ($apiAuth) {
    $apiAuth(); MailingListApiController::changeSubscribers($address, true);
});
$router->addRoute('DELETE', '/api/v1/mailing-lists/{address}/subscribers', function (string $address) use ($apiAuth) {
    $apiAuth(); MailingListApiController::changeSubscribers($address, false);
});
$router->addRoute('GET', '/api/v1/mailing-lists/{address}/moderators', function (string $address) use ($apiAuth) {
    $apiAuth(); MailingListApiController::moderators($address);
});
$router->addRoute('PUT', '/api/v1/mailing-lists/{address}/moderators', function (string $address) use ($apiAuth) {
    $apiAuth(); MailingListApiController::setModerators($address);
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
$router->addRoute('DELETE', '/api/v1/spam-policy/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); SpamPolicyApiController::delete($account);
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

// Mail list API (LDAP backend only)
$router->addRoute('GET', '/api/v1/mail-lists', function () use ($apiAuth) {
    $apiAuth(); MailListApiController::list();
});
$router->addRoute('POST', '/api/v1/mail-lists', function () use ($apiAuth) {
    $apiAuth(); MailListApiController::create();
});
$router->addRoute('GET', '/api/v1/mail-lists/{address}', function (string $address) use ($apiAuth) {
    $apiAuth(); MailListApiController::get($address);
});
$router->addRoute('PUT', '/api/v1/mail-lists/{address}', function (string $address) use ($apiAuth) {
    $apiAuth(); MailListApiController::update($address);
});
$router->addRoute('DELETE', '/api/v1/mail-lists/{address}', function (string $address) use ($apiAuth) {
    $apiAuth(); MailListApiController::delete($address);
});

// LDIF API (LDAP backend only)
$router->addRoute('GET', '/api/v1/ldif', function () use ($apiAuth) {
    $apiAuth(); LdifApiController::tree();
});
$router->addRoute('GET', '/api/v1/ldif/{domain}', function (string $domain) use ($apiAuth) {
    $apiAuth(); LdifApiController::domain($domain);
});

// Greylist API
$router->addRoute('GET', '/api/v1/greylist', function () use ($apiAuth) {
    $apiAuth(); GreylistApiController::listAccounts();
});
$router->addRoute('GET', '/api/v1/greylist-whitelist-domains', function () use ($apiAuth) {
    $apiAuth(); GreylistApiController::getDomains();
});
$router->addRoute('PUT', '/api/v1/greylist-whitelist-domains', function () use ($apiAuth) {
    $apiAuth(); GreylistApiController::updateDomains();
});
$router->addRoute('DELETE', '/api/v1/greylist/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); GreylistApiController::delete($account);
});
$router->addRoute('GET', '/api/v1/greylist/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); GreylistApiController::get($account);
});
$router->addRoute('PUT', '/api/v1/greylist/{account}', function (string $account) use ($apiAuth) {
    $apiAuth(); GreylistApiController::update($account);
});

// API clients parse JSON, so API errors must never render an HTML page.
$isApiRequest = str_starts_with((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/api/');

$router->setNotFoundHandler(function () use ($tpl, $isApiRequest) {
    if ($isApiRequest) {
        ApiResponse::error('Not found', 404);
        return;
    }
    BaseController::page404($tpl);
});

// Dispatch request, catch backend connection and CSRF errors at top level
try {
    $router->dispatch(
        $_SERVER['REQUEST_URI'] ?? '/',
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    );
} catch (BackendConnectionException $e) {
    if ($isApiRequest) {
        error_log('Backend connection error: ' . $e->getMessage());
        ApiResponse::error('Backend unavailable', 503);
    } else {
        BaseController::pageBackendDown($tpl, $e);
    }
} catch (CsrfTokenException) {
    BaseController::pageCsrf($tpl);
} catch (\Throwable $e) {
    if (!$isApiRequest) {
        throw $e;
    }
    // The log keeps the trace; the client gets no internals.
    error_log('API error: ' . $e);
    ApiResponse::error('Internal server error', 500);
}
