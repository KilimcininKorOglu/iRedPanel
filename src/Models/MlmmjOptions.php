<?php

declare(strict_types=1);

namespace App\Models;

use App\Utils\AddressList;

/**
 * The mlmmj profile options of a mailing list. mlmmj keeps them in the list
 * spool, so the panel reads them from mlmmjadmin and writes them back; nothing
 * is stored in the SQL or LDAP account.
 */
final class MlmmjOptions
{
    /** Panel field => mlmmjadmin parameter, all of them yes/no values. */
    public const BOOLEANS = [
        'closeList' => 'close_list',
        'disableSubscription' => 'disable_subscription',
        'disableSubscriptionConfirm' => 'disable_subscription_confirm',
        'moderated' => 'moderated',
        'moderateNonSubscriberPost' => 'moderate_non_subscriber_post',
        'moderateSubscription' => 'moderate_subscription',
        'disableArchive' => 'disable_archive',
        'disableDigestSubscription' => 'disable_digest_subscription',
        'disableDigestText' => 'disable_digest_text',
        'disableNomailSubscription' => 'disable_nomail_subscription',
        'disableRetrievingOldPosts' => 'disable_retrieving_old_posts',
        'onlySubscriberCanGetOldPosts' => 'only_subscriber_can_get_old_posts',
        'disableRetrievingSubscribers' => 'disable_retrieving_subscribers',
        'disableSendCopyToSender' => 'disable_send_copy_to_sender',
        'notifyOwnerWhenSubUnsub' => 'notify_owner_when_sub_unsub',
        'notifySenderWhenModerated' => 'notify_sender_when_moderated',
    ];

    /** Panel field => mlmmjadmin parameter, single text values. */
    public const TEXTS = [
        'subjectPrefix' => 'subject_prefix',
        'footerText' => 'footer_text',
        'footerHtml' => 'footer_html',
    ];

    /** Panel field => mlmmjadmin parameter, one value per line in the form. */
    public const LISTS = [
        'customHeaders' => 'custom_headers',
        'removeHeaders' => 'remove_headers',
        'extraAddresses' => 'extra_addresses',
        'subscriptionModerators' => 'subscription_moderators',
    ];

    /** The list fields whose values must be email addresses. */
    public const ADDRESS_LISTS = ['extraAddresses', 'subscriptionModerators'];

    /** The subscription versions of mlmmj. */
    public const SUBSCRIPTIONS = ['normal', 'digest', 'nomail'];

    /**
     * Headers that mlmmjadmin adds to every list by itself. They come back in the
     * profile, and writing them again is pointless, so the panel hides them.
     */
    private const MANAGED_HEADERS = [
        'precedence', 'list-id', 'reply-to', 'list-post', 'list-subscribe', 'list-unsubscribe', 'x-mailing-list',
    ];

    /**
     * @param array<string, bool> $booleans
     * @param array<string, string> $texts
     * @param array<string, list<string>> $lists
     */
    private function __construct(
        public readonly array $booleans,
        public readonly array $texts,
        public readonly array $lists,
    ) {}

    /**
     * Every option off, with empty texts and lists.
     */
    public static function empty(): self
    {
        return new self(
            array_fill_keys(array_keys(self::BOOLEANS), false),
            array_fill_keys(array_keys(self::TEXTS), ''),
            array_fill_keys(array_keys(self::LISTS), []),
        );
    }

    /**
     * Reads an mlmmjadmin profile response. A missing parameter counts as off or empty.
     *
     * @param array<string, mixed> $profile the `_data` of `GET /api/<mail>`
     */
    public static function fromProfile(string $address, array $profile): self
    {
        $booleans = [];
        foreach (self::BOOLEANS as $field => $param) {
            $booleans[$field] = ($profile[$param] ?? 'no') === 'yes';
        }

        $texts = [];
        foreach (self::TEXTS as $field => $param) {
            $value = $profile[$param] ?? '';
            $texts[$field] = is_scalar($value) ? (string) $value : '';
        }

        $lists = [];
        foreach (self::LISTS as $field => $param) {
            $lists[$field] = self::readList($field, $profile[$param] ?? [], $address);
        }

        return new self($booleans, $texts, $lists);
    }

    /**
     * Applies the given values to a copy of these options. A field that the input
     * does not carry keeps its current value.
     *
     * @param array<string, mixed> $input a JSON body, or a form post when $fromForm is true
     * @param bool $fromForm true reads a checkbox as present/absent instead of a JSON boolean
     * @throws \InvalidArgumentException when a value has the wrong type or holds an invalid address
     */
    public function with(array $input, bool $fromForm): self
    {
        $booleans = $this->booleans;
        foreach (self::BOOLEANS as $field => $_) {
            $booleans[$field] = $fromForm ? isset($input[$field]) : self::inputBool($input, $field, $booleans[$field]);
        }

        $texts = $this->texts;
        foreach (self::TEXTS as $field => $_) {
            if (array_key_exists($field, $input)) {
                $texts[$field] = self::inputText($input, $field);
            }
        }

        $lists = $this->lists;
        foreach (self::LISTS as $field => $_) {
            if (array_key_exists($field, $input)) {
                $lists[$field] = self::inputList($field, $input[$field], $fromForm);
            }
        }

        return new self($booleans, $texts, $lists);
    }

    /**
     * @return array<string, bool|string|list<string>> every option under its panel name
     */
    public function toArray(): array
    {
        return $this->booleans + $this->texts + $this->lists;
    }

    /**
     * The mlmmjadmin form parameters of every option.
     *
     * @return array<string, string>
     */
    public function params(): array
    {
        $params = [];
        foreach ($this->booleans as $field => $value) {
            $params[self::BOOLEANS[$field]] = $value ? 'yes' : 'no';
        }
        foreach ($this->texts as $field => $value) {
            $params[self::TEXTS[$field]] = $value;
        }
        foreach ($this->lists as $field => $values) {
            $params[self::LISTS[$field]] = implode($field === 'customHeaders' ? "\n" : ',', $values);
        }

        return $params;
    }

    /**
     * @return list<string> the stored values without the ones that mlmmjadmin manages
     */
    private static function readList(string $field, mixed $value, string $address): array
    {
        $values = array_map('strval', is_array($value) ? $value : []);
        $values = array_map('trim', $values);
        $values = array_filter($values, static fn (string $item): bool => $item !== '');
        if ($field === 'customHeaders') {
            $values = array_filter($values, static fn (string $item): bool => !self::isManagedHeader($item));
        }
        if ($field === 'extraAddresses') {
            $values = array_filter($values, static fn (string $item): bool => strtolower($item) !== strtolower($address));
        }

        return array_values($values);
    }

    private static function isManagedHeader(string $header): bool
    {
        $name = strtolower(trim(explode(':', $header, 2)[0]));

        return in_array($name, self::MANAGED_HEADERS, true);
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function inputBool(array $input, string $field, bool $current): bool
    {
        if (!array_key_exists($field, $input)) {
            return $current;
        }
        if (!is_bool($input[$field])) {
            throw new \InvalidArgumentException("{$field} must be true or false");
        }

        return $input[$field];
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function inputText(array $input, string $field): string
    {
        if (!is_string($input[$field])) {
            throw new \InvalidArgumentException("{$field} must be a string");
        }

        return trim($input[$field]);
    }

    /**
     * @return list<string>
     */
    private static function inputList(string $field, mixed $value, bool $fromForm): array
    {
        $items = $fromForm ? self::formLines($field, $value) : self::jsonStrings($field, $value);
        if (in_array($field, self::ADDRESS_LISTS, true)) {
            try {
                return AddressList::parse(implode("\n", $items));
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException("Invalid {$field} address: {$e->getMessage()}", 0, $e);
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private static function formLines(string $field, mixed $value): array
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("{$field} must be a string");
        }
        $lines = array_map('trim', preg_split('/\R/', $value) ?: []);

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    /**
     * @return list<string>
     */
    private static function jsonStrings(string $field, mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || array_filter($value, 'is_string') !== $value) {
            throw new \InvalidArgumentException("{$field} must be an array of strings");
        }
        $items = array_map('trim', $value);

        return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
    }

    /**
     * @throws \InvalidArgumentException when the version is not a subscription of mlmmj
     */
    public static function validSubscription(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, self::SUBSCRIPTIONS, true)) {
            throw new \InvalidArgumentException('subscription must be one of: ' . implode(', ', self::SUBSCRIPTIONS));
        }

        return $value;
    }
}
