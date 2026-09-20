<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Reads a quarantined message as Amavisd stored it: the raw RFC 5322 text.
 * The panel never renders the message as HTML, so every part stays plain text.
 */
final class QuarantinedMail
{
    /** The headers the view page shows above the raw header block, in this order. */
    public const SHOWN_HEADERS = [
        'date',
        'from',
        'to',
        'cc',
        'subject',
        'message-id',
        'x-spam-status',
        'x-spam-score',
        'x-spam-level',
        'x-spam-flag',
    ];

    /** Bytes of the body the view page shows; the download carries the whole message. */
    public const BODY_LIMIT = 262144;

    /**
     * The template variables of the message view, shared by the admin page and the
     * self-service page.
     *
     * @return array{mailId: string, headers: array<string, string>, shownHeaders: list<string>, headerText: string, body: string, bodyTruncated: bool, downloadUrl: string}
     */
    public static function viewData(string $raw, string $mailId, string $downloadUrl): array
    {
        $body = self::body($raw);

        return [
            'mailId' => $mailId,
            'headers' => self::headers($raw),
            'shownHeaders' => self::SHOWN_HEADERS,
            'headerText' => self::headerText($raw),
            'body' => $body,
            'bodyTruncated' => strlen($body) >= self::BODY_LIMIT,
            'downloadUrl' => $downloadUrl,
        ];
    }

    /**
     * Sends the message as an .eml attachment and ends the request.
     */
    public static function download(string $mailId, string $raw): never
    {
        header('Content-Type: message/rfc822');
        header('Content-Disposition: attachment; filename="' . self::fileName($mailId) . '"');
        header('Content-Length: ' . strlen($raw));
        header('X-Content-Type-Options: nosniff');
        echo $raw;
        exit;
    }

    /**
     * The download file name. The mail ID comes from the URL, so every character
     * outside the Amavisd ID alphabet becomes an underscore.
     */
    public static function fileName(string $mailId): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $mailId) ?? '';

        return ($safe === '' ? 'message' : $safe) . '.eml';
    }

    /**
     * The header block of the message, without the body.
     */
    public static function headerText(string $raw): string
    {
        $end = self::bodyOffset($raw);

        return $end === null ? rtrim($raw, "\r\n") : rtrim(substr($raw, 0, $end), "\r\n");
    }

    /**
     * The headers as a map of lowercase name => value. A repeated header keeps its
     * first value, because a spam message may carry a forged second copy.
     *
     * @return array<string, string>
     */
    public static function headers(string $raw): array
    {
        $headers = [];
        foreach (self::unfold(self::headerText($raw)) as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $name = strtolower(trim($parts[0]));
            if ($name !== '' && !isset($headers[$name])) {
                $headers[$name] = trim($parts[1]);
            }
        }

        return $headers;
    }

    /**
     * The body, truncated to $limit bytes. Returns '' when the message has no body.
     */
    public static function body(string $raw, int $limit = self::BODY_LIMIT): string
    {
        $offset = self::bodyOffset($raw);

        return $offset === null ? '' : substr($raw, $offset, $limit);
    }

    /**
     * The offset of the body, or null when the message holds no empty separator line.
     */
    private static function bodyOffset(string $raw): ?int
    {
        $found = null;
        $offset = null;
        foreach (["\r\n\r\n", "\n\n"] as $separator) {
            $position = strpos($raw, $separator);
            if ($position !== false && ($found === null || $position < $found)) {
                $found = $position;
                $offset = $position + strlen($separator);
            }
        }

        return $offset;
    }

    /**
     * Joins every folded header line with its continuation lines.
     *
     * @return list<string>
     */
    private static function unfold(string $headerText): array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\n|\r/', $headerText) ?: [] as $line) {
            if ($lines !== [] && ($line !== '' && ($line[0] === ' ' || $line[0] === "\t"))) {
                $lines[count($lines) - 1] .= ' ' . trim($line);
                continue;
            }
            $lines[] = $line;
        }

        return $lines;
    }
}
