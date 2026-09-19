<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Releases a quarantined message through the Amavisd AM.PDP protocol, as
 * iRedAdmin does. Amavisd serves the protocol on a TCP port (9998 in
 * iRedMail), so the panel does not need the amavisd-release command.
 *
 * See "Releasing a message from a quarantine" in Amavisd README.protocol.
 */
class AmavisdReleaseClient
{
    private const TIMEOUT_SECONDS = 10;

    private const ID_PATTERN = '/^[A-Za-z0-9+_-]+$/';

    /**
     * @throws \RuntimeException when Amavisd cannot be reached or refuses the release
     */
    public static function release(string $host, int $port, string $mailId, string $secretId, string $requestedBy, ?string $recipient = null): void
    {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, self::TIMEOUT_SECONDS);
        if ($socket === false) {
            throw new \RuntimeException("Cannot connect to Amavisd at {$host}:{$port}: {$errstr}");
        }

        try {
            stream_set_timeout($socket, self::TIMEOUT_SECONDS);
            if (fwrite($socket, self::buildRequest($mailId, $secretId, $requestedBy, $recipient)) === false) {
                throw new \RuntimeException('Cannot send the release request to Amavisd');
            }
            $reply = self::readReply($socket);
        } finally {
            fclose($socket);
        }

        self::assertReleased($reply);
    }

    /**
     * Returns the AM.PDP release request for one quarantined message. With $recipient, Amavisd
     * delivers the message to that address only, as `amavisd-release <id> <secret> <recipient>` does.
     */
    public static function buildRequest(string $mailId, string $secretId, string $requestedBy, ?string $recipient = null): string
    {
        if ($recipient !== null && filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Invalid release recipient');
        }
        foreach (['mail_id' => $mailId, 'secret_id' => $secretId] as $name => $value) {
            if (preg_match(self::ID_PATTERN, $value) !== 1) {
                throw new \InvalidArgumentException("Invalid Amavisd {$name}");
            }
        }

        return "request=release\r\n"
            . "mail_id={$mailId}\r\n"
            . "secret_id={$secretId}\r\n"
            . 'requested_by=' . rawurlencode($requestedBy) . "\r\n"
            . "quar_type=Q\r\n"
            . ($recipient !== null ? "recipient={$recipient}\r\n" : '')
            . "\r\n";
    }

    /**
     * Throws unless the Amavisd reply carries a 2xx "setreply" status.
     */
    public static function assertReleased(string $reply): void
    {
        if (preg_match('/^setreply=(.+)$/m', str_replace("\r", '', $reply), $match) !== 1) {
            throw new \RuntimeException('Amavisd sent no release status');
        }
        $status = rawurldecode($match[1]);
        if (!str_starts_with($status, '2')) {
            throw new \RuntimeException("Amavisd refused the release: {$status}");
        }
    }

    /**
     * Reads the reply up to the empty line that ends it.
     *
     * @param resource $socket
     */
    private static function readReply($socket): string
    {
        $reply = '';
        while (($line = fgets($socket)) !== false) {
            if (rtrim($line, "\r\n") === '') {
                return $reply;
            }
            $reply .= $line;
        }
        if (stream_get_meta_data($socket)['timed_out']) {
            throw new \RuntimeException('Amavisd did not answer the release request');
        }
        return $reply;
    }
}
