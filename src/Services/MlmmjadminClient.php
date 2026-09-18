<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BackendConnectionException;
use App\Models\Settings;

/**
 * Client for the mlmmjadmin RESTful API, which owns the mlmmj mailing list
 * spool on the mail server. The panel keeps the SQL/LDAP account itself, and
 * mlmmjadmin creates the list directory, its profile files and subscribers.
 *
 * See https://github.com/iredmail/mlmmjadmin/blob/master/docs/API.md
 */
class MlmmjadminClient
{
    private const TIMEOUT_SECONDS = 15;

    private const TOKEN_HEADER = 'X-MLMMJADMIN-API-AUTH-TOKEN';

    /** Access policies whose posting rule mlmmj enforces itself. */
    private const POST_RULES = [
        'membersonly' => ['only_subscriber_can_post' => 'yes', 'only_moderator_can_post' => 'no'],
        'moderatorsonly' => ['only_subscriber_can_post' => 'no', 'only_moderator_can_post' => 'yes'],
    ];

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {}

    /**
     * @throws BackendConnectionException when the API URL or token is not configured
     */
    public static function fromSettings(): self
    {
        $settings = Settings::getInstance();
        if ($settings->mlmmjadminApiUrl === '' || $settings->mlmmjadminApiToken === '') {
            throw new BackendConnectionException('mlmmjadmin API is not configured');
        }

        return new self($settings->mlmmjadminApiUrl, $settings->mlmmjadminApiToken);
    }

    /**
     * Returns the mlmmj profile parameters for a list.
     *
     * @param string[] $owners
     * @return array<string, string>
     */
    public static function listParams(string $name, string $accessPolicy, int $maxMsgSize, array $owners): array
    {
        $postRule = self::POST_RULES[strtolower($accessPolicy)]
            ?? ['only_subscriber_can_post' => 'no', 'only_moderator_can_post' => 'no'];

        return [
            'name' => $name,
            'max_message_size' => (string) max(0, $maxMsgSize),
            'owner' => implode(',', $owners),
        ] + $postRule;
    }

    /**
     * @param array<string, string> $params
     */
    public function createList(string $mail, array $params): void
    {
        $this->request('POST', self::path($mail), $params);
    }

    /**
     * @param array<string, string> $params
     */
    public function updateList(string $mail, array $params): void
    {
        $this->request('PUT', self::path($mail), $params);
    }

    /**
     * Deletes the list and keeps its data under the mlmmj archive directory.
     */
    public function deleteList(string $mail): void
    {
        $this->request('DELETE', self::path($mail) . '?archive=yes');
    }

    /**
     * @return string[] subscriber addresses, sorted
     */
    public function subscribers(string $mail): array
    {
        $data = $this->request('GET', self::path($mail) . '/subscribers?email_only=yes');
        $subscribers = array_values(array_unique(array_map('strval', is_array($data) ? $data : [])));
        sort($subscribers);

        return $subscribers;
    }

    /**
     * Adds subscribers to the normal subscription without a confirmation mail.
     *
     * @param string[] $subscribers
     */
    public function addSubscribers(string $mail, array $subscribers): void
    {
        $this->request('POST', self::path($mail) . '/subscribers', [
            'add_subscribers' => implode(',', self::emails($subscribers)),
            'subscription' => 'normal',
            'require_confirm' => 'no',
        ]);
    }

    /**
     * @param string[] $subscribers
     */
    public function removeSubscribers(string $mail, array $subscribers): void
    {
        $this->request('POST', self::path($mail) . '/subscribers', [
            'remove_subscribers' => implode(',', self::emails($subscribers)),
        ]);
    }

    /**
     * Returns `_data` of a successful mlmmjadmin JSON response.
     *
     * @throws \RuntimeException when the response is not JSON or reports a failure
     */
    public static function parseResponse(string $body): mixed
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !array_key_exists('_success', $decoded)) {
            throw new \RuntimeException('mlmmjadmin sent an invalid response');
        }
        if ($decoded['_success'] !== true) {
            throw new \RuntimeException('mlmmjadmin error: ' . (string) ($decoded['_msg'] ?? 'unknown'));
        }

        return $decoded['_data'] ?? null;
    }

    /**
     * @param array<string, string>|null $form
     * @throws BackendConnectionException when the API cannot be reached
     */
    private function request(string $method, string $path, ?array $form = null): mixed
    {
        $headers = [self::TOKEN_HEADER . ': ' . $this->token];
        $options = ['method' => $method, 'timeout' => self::TIMEOUT_SECONDS, 'ignore_errors' => true];
        if ($form !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options['content'] = http_build_query($form);
        }
        $options['header'] = implode("\r\n", $headers);

        $body = @file_get_contents($this->baseUrl . $path, false, stream_context_create(['http' => $options]));
        if ($body === false) {
            throw new BackendConnectionException('mlmmjadmin API not available');
        }

        return self::parseResponse($body);
    }

    private static function path(string $mail): string
    {
        return '/' . self::emails([$mail])[0];
    }

    /**
     * @param string[] $addresses
     * @return string[] lowercased addresses
     * @throws \InvalidArgumentException for an address that is not a plain email
     */
    private static function emails(array $addresses): array
    {
        $valid = [];
        foreach ($addresses as $address) {
            $address = strtolower(trim($address));
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException($address);
            }
            $valid[] = $address;
        }

        return $valid;
    }
}
