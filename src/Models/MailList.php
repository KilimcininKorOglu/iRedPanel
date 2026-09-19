<?php

declare(strict_types=1);

namespace App\Models;

use App\Utils\AccessPolicy;
use App\Utils\AddressList;
use App\Utils\WholeNumber;

/**
 * A mail list of the LDAP backend: a group account whose members the admin
 * manages. A member never subscribes or unsubscribes itself, unlike a mlmmj
 * mailing list.
 */
class MailList
{
    /** Posting restrictions that iRedAPD enforces for a group account. */
    public const ACCESS_POLICIES = ['public', 'domain', 'subdomain', 'membersOnly', 'moderatorsOnly', 'allowedOnly'];

    /**
     * @param list<string> $members addresses that receive the mail of the list
     * @param list<string> $moderators addresses that may post to a moderatorsOnly list
     * @param list<string> $allowedSenders addresses that may post to an allowedOnly list
     * @param int $maxMessageSize in bytes; 0 means no limit
     */
    public function __construct(
        public readonly string $address,
        public readonly string $domain,
        public string $name = '',
        public string $accessPolicy = 'public',
        public bool $active = true,
        public array $members = [],
        public array $moderators = [],
        public array $allowedSenders = [],
        public int $maxMessageSize = 0,
    ) {}

    /**
     * Reads the form post of the web page or the JSON body of the REST API.
     *
     * @throws \InvalidArgumentException for an invalid field
     */
    public static function fromFormData(string $address, array $post): self
    {
        $address = strtolower($address);
        $domain = explode('@', $address, 2)[1] ?? '';
        $size = WholeNumber::parse($post['maxMessageSize'] ?? 0);
        if ($size === null) {
            throw new \InvalidArgumentException('maxMessageSize must be a whole number of bytes');
        }

        return new self(
            address: $address,
            domain: $domain,
            name: trim((string) ($post['name'] ?? '')),
            accessPolicy: self::validAccessPolicy($post['accessPolicy'] ?? 'public'),
            active: (bool) ($post['active'] ?? false),
            members: self::addresses($post['members'] ?? []),
            moderators: self::addresses($post['moderators'] ?? []),
            allowedSenders: self::addresses($post['allowedSenders'] ?? []),
            maxMessageSize: $size,
        );
    }

    /**
     * @throws \InvalidArgumentException when iRedAPD does not know the policy
     */
    public static function validAccessPolicy(mixed $policy): string
    {
        return AccessPolicy::validate($policy, self::ACCESS_POLICIES);
    }

    /**
     * @return array<string, mixed> the profile as the REST API answers it
     */
    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'domain' => $this->domain,
            'name' => $this->name,
            'accessPolicy' => $this->accessPolicy,
            'active' => $this->active,
            'members' => $this->members,
            'moderators' => $this->moderators,
            'allowedSenders' => $this->allowedSenders,
            'maxMessageSize' => $this->maxMessageSize,
        ];
    }

    /**
     * Accepts a text area with one address per line or an array of addresses.
     *
     * @return list<string>
     * @throws \InvalidArgumentException for an invalid address
     */
    private static function addresses(mixed $value): array
    {
        if (is_array($value)) {
            $value = implode("\n", array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        return AddressList::parse((string) $value);
    }
}
