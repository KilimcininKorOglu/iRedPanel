<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The answer of one DNS check of a domain.
 */
final class DnsCheckRow
{
    public const OK = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    /** The check could not run, because another check found nothing to check. */
    public const UNKNOWN = 'unknown';

    /**
     * @param string $key the check name: mx, a, spf, dkim, dmarc or ptr
     * @param string $status OK, WARN, FAIL or UNKNOWN
     * @param string[] $values the records that the check found, as the page shows them
     * @param string $detail the translation key of the message, without the `dns.` prefix
     * @param array<string, string> $params the `:param` values of the message
     */
    public function __construct(
        public readonly string $key,
        public readonly string $status,
        public readonly array $values = [],
        public readonly string $detail = '',
        public readonly array $params = [],
    ) {
    }
}
