<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware;
use App\Repositories\RepositoryFactory;

/**
 * JSON lookup for the account pickers of the web forms. It returns active accounts that
 * match a query, restricted to the domains the logged-in admin manages.
 */
class AccountLookupController
{
    public const MIN_QUERY_LENGTH = 2;
    public const LIMIT = 20;

    /** Account type => search result key. */
    private const TYPES = [
        'user' => 'users',
        'alias' => 'aliases',
        'ml' => 'mailingLists',
    ];

    public static function accounts(): void
    {
        Middleware::loginRequired();

        $query = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
        $types = self::parseTypes(is_string($_GET['types'] ?? null) ? $_GET['types'] : '');
        $domain = is_string($_GET['domain'] ?? null) ? $_GET['domain'] : '';
        $scope = self::scopeDomains(
            !empty($_SESSION['isGlobalAdmin']),
            $_SESSION['managedDomains'] ?? [],
            $domain
        );

        $options = [];
        if ($scope !== null && mb_strlen($query) >= self::MIN_QUERY_LENGTH) {
            $results = RepositoryFactory::getSearchRepository()->search($query, $types, ['active'], $scope);
            $options = self::toOptions($results, $types, self::LIMIT);
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($options, JSON_THROW_ON_ERROR);
    }

    /**
     * Keeps the known account types of a comma-separated list. An empty or unknown
     * list gives all types, because the search repository reads [] as "every type".
     *
     * @return string[]
     */
    public static function parseTypes(string $raw): array
    {
        $types = array_values(array_intersect(array_keys(self::TYPES), array_map(trim(...), explode(',', $raw))));

        return $types !== [] ? $types : array_keys(self::TYPES);
    }

    /**
     * Returns the domain list for the search repository: [] searches every domain,
     * null means the admin may not see any account.
     *
     * @param string[] $managedDomains
     * @return string[]|null
     */
    public static function scopeDomains(bool $isGlobalAdmin, array $managedDomains, string $domain): ?array
    {
        $domain = strtolower(trim($domain));
        if ($isGlobalAdmin) {
            return $domain === '' ? [] : [$domain];
        }
        if ($domain === '') {
            return $managedDomains !== [] ? array_values($managedDomains) : null;
        }

        return in_array($domain, $managedDomains, true) ? [$domain] : null;
    }

    /**
     * Flattens search results into picker options, without duplicate addresses.
     *
     * @param string[] $types
     * @return list<array{value: string, text: string, type: string}>
     */
    public static function toOptions(array $results, array $types, int $limit): array
    {
        $options = [];
        foreach ($types as $type) {
            foreach ($results[self::TYPES[$type]] ?? [] as $row) {
                $address = (string) ($row['username'] ?? $row['address'] ?? '');
                if ($address === '' || isset($options[$address])) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $options[$address] = [
                    'value' => $address,
                    'text' => $name !== '' ? "{$name} <{$address}>" : $address,
                    'type' => $type,
                ];
            }
        }

        return array_slice(array_values($options), 0, $limit);
    }
}
