<?php

declare(strict_types=1);

namespace App\Services\Directory;

use App\Exceptions\DirectoryException;
use App\Models\AccountResource;

/**
 * A connection to the directory server of an account resource. It is separate
 * from App\Models\LdapConnection, which serves the panel's own LDAP backend.
 */
final class DirectoryClient
{
    public const SSL_PORT = 636;
    private const PAGE_SIZE = 500;
    private const LDAP_SUCCESS = 0;
    private const LDAP_SIZELIMIT_EXCEEDED = 4;

    private function __construct(private readonly \LDAP\Connection $conn, private readonly int $timeout) {}

    /**
     * Connects and binds. Port 636 uses LDAP over SSL; another port uses StartTLS when TLS is on.
     *
     * @throws DirectoryException when the server cannot be reached or rejects the bind
     */
    public static function connect(AccountResource $resource, string $bindPassword): self
    {
        if (!extension_loaded('ldap')) {
            throw new DirectoryException('ext-ldap is required to replicate accounts');
        }
        if ($bindPassword === '') {
            // An empty password makes an unauthenticated bind succeed.
            throw new DirectoryException('The bind password is empty');
        }
        $ssl = $resource->port === self::SSL_PORT;
        $verify = $resource->tlsVerify ? LDAP_OPT_X_TLS_DEMAND : LDAP_OPT_X_TLS_NEVER;
        if ($ssl) {
            // ldap_connect() builds the TLS context of an ldaps:// URI from the global options.
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, $verify);
        }
        $scheme = $ssl ? 'ldaps' : 'ldap';
        $conn = @ldap_connect("{$scheme}://{$resource->host}:{$resource->port}");
        if ($conn === false) {
            throw new DirectoryException("Invalid server address: {$resource->host}:{$resource->port}");
        }
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, $resource->timeout);
        ldap_set_option($conn, LDAP_OPT_TIMELIMIT, $resource->timeout * 6);

        if (!$ssl && $resource->tls) {
            ldap_set_option($conn, LDAP_OPT_X_TLS_REQUIRE_CERT, $verify);
            if (!@ldap_start_tls($conn)) {
                throw new DirectoryException('StartTLS failed: ' . self::diagnostic($conn));
            }
        }
        if (!@ldap_bind($conn, $resource->bindDn, $bindPassword)) {
            throw new DirectoryException("Bind as {$resource->bindDn} failed: " . self::diagnostic($conn));
        }

        return new self($conn, $resource->timeout);
    }

    public function __destruct()
    {
        @ldap_unbind($this->conn);
    }

    /**
     * Returns every entry below $baseDn that matches $filter, page by page. With $max the
     * search stops after that many entries. Ranged values of $rangedAttribute are completed.
     *
     * @param string[] $attributes
     * @return DirectoryEntry[]
     * @throws DirectoryException when the search fails
     */
    public function search(string $baseDn, string $filter, array $attributes, ?int $max = null, string $rangedAttribute = ''): array
    {
        $entries = [];
        $cookie = '';
        $pageSize = $max === null ? self::PAGE_SIZE : min(self::PAGE_SIZE, $max);
        do {
            [$page, $cookie] = $this->searchPage($baseDn, $filter, $attributes, $pageSize, $cookie);
            array_push($entries, ...$page);
        } while ($cookie !== '' && ($max === null || count($entries) < $max));

        $entries = $max === null ? $entries : array_slice($entries, 0, $max);
        if ($rangedAttribute === '') {
            return $entries;
        }

        return array_map(fn(DirectoryEntry $e): DirectoryEntry => $this->completeRange($e, $rangedAttribute), $entries);
    }

    /**
     * @param string[] $attributes
     * @return array{DirectoryEntry[], string} the entries and the cookie of the next page
     */
    private function searchPage(string $baseDn, string $filter, array $attributes, int $pageSize, string $cookie): array
    {
        $controls = [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => $pageSize, 'cookie' => $cookie]]];
        $result = @ldap_search($this->conn, $baseDn, $filter, $attributes, 0, 0, $this->timeout * 6, LDAP_DEREF_NEVER, $controls);
        if ($result === false) {
            throw new DirectoryException("Search below {$baseDn} failed: " . self::diagnostic($this->conn));
        }
        if (!ldap_parse_result($this->conn, $result, $code, $matched, $message, $referrals, $responseControls)) {
            throw new DirectoryException('Cannot read the search result: ' . self::diagnostic($this->conn));
        }
        if ($code !== self::LDAP_SUCCESS && $code !== self::LDAP_SIZELIMIT_EXCEEDED) {
            throw new DirectoryException("Search below {$baseDn} failed: " . ldap_err2str($code) . ($message ? " ({$message})" : ''));
        }
        $raw = ldap_get_entries($this->conn, $result);
        $entries = [];
        for ($i = 0; $i < $raw['count']; $i++) {
            $entries[] = DirectoryEntry::fromLdap($raw[$i]);
        }

        return [$entries, (string) ($responseControls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '')];
    }

    /**
     * Active Directory returns at most 1500 values of a large attribute per read and names
     * the part `member;range=0-1499`. Reads the remaining ranges and joins them.
     */
    private function completeRange(DirectoryEntry $entry, string $attribute): DirectoryEntry
    {
        $range = $entry->range($attribute);
        if ($range === null) {
            return $entry;
        }
        [$values, $next] = $range;
        while ($next !== null) {
            $result = @ldap_read($this->conn, $entry->dn, '(objectClass=*)', ["{$attribute};range={$next}-*"]);
            if ($result === false) {
                throw new DirectoryException("Reading {$attribute} of {$entry->dn} failed: " . self::diagnostic($this->conn));
            }
            $part = DirectoryEntry::fromLdap(ldap_get_entries($this->conn, $result)[0])->range($attribute);
            if ($part === null) {
                break;
            }
            array_push($values, ...$part[0]);
            $next = $part[1];
        }

        return $entry->with($attribute, $values);
    }

    private static function diagnostic(\LDAP\Connection $conn): string
    {
        $detail = '';
        ldap_get_option($conn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detail);

        return ldap_error($conn) . ($detail ? " ({$detail})" : '');
    }
}
