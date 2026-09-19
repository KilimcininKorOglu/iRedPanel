<?php

declare(strict_types=1);

namespace App\Models;

use App\Utils\LdapUtils;

class LdapConnectionException extends \App\Exceptions\BackendConnectionException {}

/**
 * LDAP connection singleton. Handles TLS/STARTTLS and password verification.
 */
class LdapConnection
{
    private static ?self $instance = null;

    /** @var \LDAP\Connection */
    private \LDAP\Connection $conn;

    private function __construct(string $bindDn, string $password)
    {
        $settings = Settings::getInstance();
        $uri = $settings->ldapUri;

        $startTls = false;
        if (str_starts_with($uri, 'ldaps://')) {
            $startTls = true;
            $uri = str_replace('ldaps://', 'ldap://', $uri);
            $tlsVerify = $settings->ldapTlsVerify ? LDAP_OPT_X_TLS_DEMAND : LDAP_OPT_X_TLS_NEVER;
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, $tlsVerify);
        }

        $conn = @ldap_connect($uri);
        if ($conn === false) {
            throw new LdapConnectionException("Failed to connect to LDAP server: $uri");
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);

        if ($startTls) {
            if (!@ldap_start_tls($conn)) {
                throw new LdapConnectionException("Failed to start TLS: " . ldap_error($conn));
            }
        }

        if (!@ldap_bind($conn, $bindDn, $password)) {
            throw new \Exception("LDAP bind failed for {$bindDn}: " . ldap_error($conn));
        }

        $this->conn = $conn;
    }

    public function __destruct()
    {
        if (isset($this->conn)) {
            @ldap_unbind($this->conn);
        }
    }

    /**
     * Checks a password by binding a separate connection as $bindDn. The request keeps
     * the service connection, so the admin's own directory rights do not limit the panel.
     *
     * @throws \Exception when the bind fails
     */
    public static function verifyPassword(string $bindDn, string $password): void
    {
        // An empty password makes an unauthenticated bind succeed.
        if ($password === '') {
            throw new \Exception("LDAP bind failed for {$bindDn}: empty password");
        }
        new self($bindDn, $password);
    }

    /**
     * Returns the LDAP connection of this request. Every request, and the CLI, binds
     * with the service account IREDPANEL_LDAP_USER / IREDPANEL_LDAP_PASSWORD.
     *
     * @throws LdapConnectionException when the service account cannot bind
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $settings = Settings::getInstance();
            $error = null;
            foreach (self::serviceBindDns($settings) as $bindDn) {
                try {
                    self::$instance = new self($bindDn, $settings->ldapPassword);
                    return self::$instance;
                } catch (LdapConnectionException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    $error = $e;
                }
            }
            throw new LdapConnectionException('LDAP service bind failed: ' . $error?->getMessage(), 0, $error);
        }
        return self::$instance;
    }

    /**
     * An admin address may belong to a mailbox or to a standalone admin; a full DN
     * (cn=vmailadmin,dc=example,dc=com) is used as is; a bare CN is placed under the root DN.
     *
     * @return string[]
     */
    private static function serviceBindDns(Settings $settings): array
    {
        $user = $settings->ldapUser;
        if (str_contains($user, '@')) {
            return [
                LdapUtils::getEmailDn($user),
                'mail=' . ldap_escape(strtolower($user), '', LDAP_ESCAPE_DN) . ",o=domainAdmins,{$settings->ldapRootDn}",
            ];
        }

        return [str_contains($user, '=') ? $user : 'cn=' . ldap_escape($user, '', LDAP_ESCAPE_DN) . ",{$settings->ldapRootDn}"];
    }

    /**
     * Returns the raw LDAP connection resource for direct LDAP operations.
     */
    public function getConn(): \LDAP\Connection
    {
        return $this->conn;
    }
}
