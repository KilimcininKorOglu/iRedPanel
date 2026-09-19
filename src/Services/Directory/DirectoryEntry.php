<?php

declare(strict_types=1);

namespace App\Services\Directory;

/**
 * One entry of a directory search. Attribute names are compared case-insensitively.
 */
final class DirectoryEntry
{
    /** @var array<string, string[]> lowercased attribute => values */
    private readonly array $values;

    /**
     * @param array<string, string[]> $values
     */
    public function __construct(public readonly string $dn, array $values)
    {
        $this->values = array_change_key_case($values, CASE_LOWER);
    }

    /**
     * Builds an entry from one element of ldap_get_entries().
     */
    public static function fromLdap(array $entry): self
    {
        $values = [];
        for ($i = 0; $i < ($entry['count'] ?? 0); $i++) {
            $attribute = $entry[$i];
            $list = $entry[$attribute];
            unset($list['count']);
            $values[$attribute] = array_values($list);
        }

        return new self((string) $entry['dn'], $values);
    }

    public function first(string $attribute): string
    {
        return $this->values[strtolower($attribute)][0] ?? '';
    }

    /** @return string[] */
    public function all(string $attribute): array
    {
        return $this->values[strtolower($attribute)] ?? [];
    }

    /**
     * Returns the values of a ranged attribute such as `member;range=0-1499`, and the
     * start of the next range, or null when the server sent the last range.
     *
     * @return array{string[], ?int}|null null when the entry holds no ranged value of the attribute
     */
    public function range(string $attribute): ?array
    {
        $prefix = strtolower($attribute) . ';range=';
        foreach ($this->values as $name => $values) {
            if (!str_starts_with($name, $prefix)) {
                continue;
            }
            $end = explode('-', substr($name, strlen($prefix)), 2)[1] ?? '*';

            return [$values, $end === '*' ? null : (int) $end + 1];
        }

        return null;
    }

    /**
     * Returns a copy with the values of an attribute replaced.
     *
     * @param string[] $values
     */
    public function with(string $attribute, array $values): self
    {
        return new self($this->dn, [strtolower($attribute) => $values] + $this->values);
    }
}
