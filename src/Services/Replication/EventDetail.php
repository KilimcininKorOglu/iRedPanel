<?php

declare(strict_types=1);

namespace App\Services\Replication;

use App\I18n\Translator;

/**
 * Translates the detail tokens that the runner stores with a replication event.
 * An error message is not a token and stays as the server wrote it.
 */
final class EventDetail
{
    private const TOKENS = ['removed', 'recreated', 'no_address', 'invalid_address', 'other_domain', 'duplicate_address'];

    public static function describe(string $action, string $detail): string
    {
        if ($action === ReplicationAction::ERROR || $detail === '') {
            return $detail;
        }
        $parts = [];
        $words = explode(' ', $detail);
        for ($i = 0, $n = count($words); $i < $n; $i++) {
            $parts[] = self::word($words, $i);
        }

        return implode('; ', array_filter($parts, static fn(string $p): bool => $p !== ''));
    }

    /**
     * @param string[] $words
     */
    private static function word(array $words, int &$i): string
    {
        $word = $words[$i];
        if ($word === 'from' && isset($words[$i + 1])) {
            $i++;
            return Translator::translate('resource.detail_from', ['address' => $words[$i]]);
        }
        if (preg_match('/^unresolved_members:(\d+)$/', $word, $m) === 1) {
            return Translator::translate('resource.detail_unresolved_members', ['count' => (int) $m[1]]);
        }

        return in_array($word, self::TOKENS, true) ? Translator::translate("resource.detail_{$word}") : $word;
    }
}
