<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Utils\SqlLike;

/**
 * Selects the settings rows of some mail accounts or of a whole domain, for
 * the tables that key them by address (Amavisd, iRedAPD).
 */
final class AccountMatch
{
    /**
     * @param string[] $exact addresses that match as a whole
     * @param ?string $pattern a LIKE pattern built with SqlLike::escape()
     */
    private function __construct(private readonly array $exact, private readonly ?string $pattern = null) {}

    /** @param string[] $accounts */
    public static function accounts(array $accounts): self
    {
        return new self(array_values(array_unique(array_filter($accounts, static fn(string $a): bool => $a !== ''))));
    }

    /**
     * Matches '@domain', '@.domain' and every address of the domain.
     *
     * @throws \InvalidArgumentException for an empty value or an address, which would match other domains
     */
    public static function domain(string $domain): self
    {
        if ($domain === '' || str_contains($domain, '@')) {
            throw new \InvalidArgumentException("Invalid domain: {$domain}");
        }

        return new self(['@' . $domain, '@.' . $domain], '%@' . SqlLike::escape($domain));
    }

    public function isEmpty(): bool
    {
        return $this->exact === [];
    }

    /**
     * @param string $placeholder sprintf pattern that turns a text placeholder into the
     *        column type, for example "convert_to(%s, 'UTF8')" for a PostgreSQL bytea column
     * @return array{string, array<string, string>} the condition and its parameters
     */
    public function where(string $column, string $placeholder = '%s'): array
    {
        $params = [];
        $holders = [];
        foreach ($this->exact as $i => $value) {
            $params["m{$i}"] = $value;
            $holders[] = sprintf($placeholder, ":m{$i}");
        }
        $sql = "{$column} IN (" . implode(', ', $holders) . ')';

        if ($this->pattern !== null) {
            $params['mp'] = $this->pattern;
            $sql = "({$sql} OR {$column} LIKE " . sprintf($placeholder, ':mp') . ' ' . SqlLike::ESCAPE . ')';
        }

        return [$sql, $params];
    }
}
