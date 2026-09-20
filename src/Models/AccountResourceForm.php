<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;
use App\Utils\FormValue;
use App\Utils\WholeNumber;

/**
 * Reads the tabs of the account resource form into an AccountResource.
 */
final class AccountResourceForm
{
    public const TABS = ['connection', 'replication', 'users', 'groups'];

    /** Label key of each replicated user property; the user form uses the same labels. */
    public const PROPERTY_LABELS = [
        'accountStatus' => 'resource.account_status',
        'cn' => 'user.full_name',
        'givenName' => 'user.first_name',
        'sn' => 'user.last_name',
        'employeeNumber' => 'user.employee_number',
        'title' => 'user.position',
        'mobile' => 'user.mobile_phone',
        'telephoneNumber' => 'user.work_phone',
    ];

    /** A directory attribute name, as LDAP defines it (RFC 4512 descr). */
    private const ATTRIBUTE_PATTERN = '/^[A-Za-z][A-Za-z0-9-]*$/';
    private const HOST_PATTERN = '/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/';

    /**
     * Copies the posted fields of a tab into the resource.
     *
     * @return ?string the new bind password in plain text, or null to keep the stored one
     * @throws InvalidInputException naming the first invalid field
     */
    public static function apply(AccountResource $resource, string $tab, array $post): ?string
    {
        if ($tab === 'connection') {
            return self::applyConnection($resource, $post);
        }

        match ($tab) {
            'replication' => self::applyReplication($resource, $post),
            'users' => self::applyUsers($resource, $post),
            'groups' => self::applyGroups($resource, $post),
            default => throw new InvalidInputException("Unknown tab {$tab}", 'common.msg_invalid_input'),
        };

        return null;
    }

    private static function applyConnection(AccountResource $resource, array $post): ?string
    {
        $resource->domain = strtolower(FormValue::text($post, 'domain'));
        $resource->host = self::matching($post, 'host', self::HOST_PATTERN, 'resource.host');
        $resource->port = self::number($post, 'port', 1, 65535, 'resource.port');
        // Port 636 is LDAP over SSL, so it is always encrypted.
        $resource->tls = (bool) ($post['tls'] ?? false) || $resource->port === 636;
        $resource->tlsVerify = (bool) ($post['tlsVerify'] ?? false);
        $resource->timeout = self::number($post, 'timeout', 1, 60, 'resource.timeout');
        $resource->baseDn = self::dn($post, 'baseDn', 'resource.base_dn');
        $resource->bindDn = self::required($post, 'bindDn', 'resource.bind_dn');
        $resource->userFilter = self::filter($post, 'userFilter', 'resource.user_filter');
        $resource->groupFilter = self::filter($post, 'groupFilter', 'resource.group_filter');
        if (!Domain::isValidName($resource->domain)) {
            throw self::invalid('resource.target_domain');
        }
        $password = FormValue::text($post, 'bindPassword');

        return $password === '' ? null : $password;
    }

    private static function applyReplication(AccountResource $resource, array $post): void
    {
        $resource->intervalMinutes = self::number($post, 'intervalMinutes', 1, 1440, 'resource.interval');
        $resource->replicateGroups = (bool) ($post['replicateGroups'] ?? false);
    }

    private static function applyUsers(AccountResource $resource, array $post): void
    {
        $resource->userMailAttribute = self::matching($post, 'userMailAttribute', self::ATTRIBUTE_PATTERN, 'resource.mail_attribute');
        $attributes = [];
        foreach (self::PROPERTY_LABELS as $property => $label) {
            $attributes[$property] = self::optionalAttribute($post, "attr_{$property}", $label);
        }
        $resource->userAttributes = $attributes;
    }

    private static function applyGroups(AccountResource $resource, array $post): void
    {
        $resource->groupMailAttribute = self::matching($post, 'groupMailAttribute', self::ATTRIBUTE_PATTERN, 'resource.mail_attribute');
        $resource->groupNameAttribute = self::optionalAttribute($post, 'groupNameAttribute', 'user.full_name');
        $policy = FormValue::text($post, 'groupAccessPolicy');
        if (!in_array($policy, Alias::ACCESS_POLICIES, true)) {
            throw self::invalid('alias.access_policy');
        }
        $resource->groupAccessPolicy = $policy;
    }

    private static function required(array $post, string $name, string $label): string
    {
        $value = FormValue::text($post, $name);
        if ($value === '') {
            throw self::invalid($label);
        }

        return $value;
    }

    private static function matching(array $post, string $name, string $pattern, string $label): string
    {
        $value = self::required($post, $name, $label);
        if (preg_match($pattern, $value) !== 1) {
            throw self::invalid($label);
        }

        return $value;
    }

    private static function optionalAttribute(array $post, string $name, string $label): string
    {
        $value = FormValue::text($post, $name);
        if ($value !== '' && preg_match(self::ATTRIBUTE_PATTERN, $value) !== 1) {
            throw self::invalid($label);
        }

        return $value;
    }

    private static function number(array $post, string $name, int $min, int $max, string $label): int
    {
        $value = WholeNumber::parse($post[$name] ?? null);
        if ($value === null || $value < $min || $value > $max) {
            throw self::invalid($label);
        }

        return $value;
    }

    private static function dn(array $post, string $name, string $label): string
    {
        $value = self::required($post, $name, $label);
        if (!str_contains($value, '=')) {
            throw self::invalid($label);
        }

        return $value;
    }

    /**
     * A filter must be one parenthesized expression with balanced parentheses.
     */
    private static function filter(array $post, string $name, string $label): string
    {
        $value = self::required($post, $name, $label);
        $depth = 0;
        foreach (str_split($value) as $i => $char) {
            $depth += match ($char) { '(' => 1, ')' => -1, default => 0 };
            if ($depth < 0 || ($depth === 0 && $i < strlen($value) - 1)) {
                throw self::invalid($label);
            }
        }
        if ($depth !== 0 || $value[0] !== '(') {
            throw self::invalid($label);
        }

        return $value;
    }

    private static function invalid(string $label): InvalidInputException
    {
        return new InvalidInputException("Invalid {$label}", 'resource.msg_invalid_field', fieldKey: $label);
    }
}
