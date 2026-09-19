<?php

declare(strict_types=1);

namespace App\Services\Replication;

use App\Models\AccountResource;
use App\Models\ReplicatedAccount;
use App\Services\Directory\DirectoryEntry;

/**
 * Maps Active Directory and Samba entries to SourceAccount values with the
 * attribute settings of an account resource.
 */
final class AdAttributeMapper
{
    /** userAccountControl flag ACCOUNTDISABLE. */
    private const ACCOUNT_DISABLE = 0x2;

    private const INACTIVE_VALUES = ['0', 'false', 'no', 'disabled', 'inactive'];

    public function __construct(private readonly AccountResource $resource) {}

    public function user(DirectoryEntry $entry): SourceAccount
    {
        $profile = [];
        foreach ($this->resource->userAttributes as $property => $attribute) {
            if ($property !== 'accountStatus' && $attribute !== '') {
                $profile[$property] = trim($entry->first($attribute));
            }
        }
        [$address, $skip] = $this->address($entry->first($this->resource->userMailAttribute));

        return new SourceAccount(
            guid: self::guid($entry),
            kind: ReplicatedAccount::KIND_USER,
            dn: $entry->dn,
            address: $address,
            skipReason: $skip,
            active: $this->status($entry),
            profile: $profile,
        );
    }

    public function group(DirectoryEntry $entry): SourceAccount
    {
        [$address, $skip] = $this->address($entry->first($this->resource->groupMailAttribute));

        return new SourceAccount(
            guid: self::guid($entry),
            kind: ReplicatedAccount::KIND_GROUP,
            dn: $entry->dn,
            address: $address,
            skipReason: $skip,
            name: $this->resource->groupNameAttribute === '' ? '' : trim($entry->first($this->resource->groupNameAttribute)),
            memberDns: $entry->all('member'),
        );
    }

    /**
     * objectGUID is binary; the hex form keys the link to the local account.
     */
    public static function guid(DirectoryEntry $entry): string
    {
        return bin2hex($entry->first('objectGUID'));
    }

    /**
     * @return array{string, string} the lowercased address and the skip reason ('' when usable)
     */
    private function address(string $value): array
    {
        $address = strtolower(trim($value));
        if ($address === '') {
            return ['', SourceAccount::SKIP_NO_ADDRESS];
        }
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return [$address, SourceAccount::SKIP_INVALID_ADDRESS];
        }
        if (substr($address, strrpos($address, '@') + 1) !== strtolower($this->resource->domain)) {
            return [$address, SourceAccount::SKIP_OTHER_DOMAIN];
        }

        return [$address, ''];
    }

    /**
     * userAccountControl is a bit field; any other attribute is active unless its value reads as off.
     * Null means the status is not replicated.
     */
    private function status(DirectoryEntry $entry): ?bool
    {
        $attribute = $this->resource->userAttributes['accountStatus'] ?? '';
        if ($attribute === '') {
            return null;
        }
        $value = strtolower(trim($entry->first($attribute)));
        if ($value === '') {
            // An entry without the attribute gives no status, so the local status stays.
            return null;
        }
        if (strcasecmp($attribute, 'userAccountControl') === 0) {
            return ctype_digit($value) && ((int) $value & self::ACCOUNT_DISABLE) === 0;
        }

        return !in_array($value, self::INACTIVE_VALUES, true);
    }
}
