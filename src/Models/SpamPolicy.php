<?php

declare(strict_types=1);

namespace App\Models;

use App\Utils\FormValue;

class SpamPolicy
{
    /**
     * The quarantine columns and the value that iRedMail configures for each of
     * them. A null property inherits the setting of Amavisd, true writes the
     * value below, and false writes an empty string, which turns the quarantine off.
     */
    public const QUARANTINE_COLUMNS = [
        'spamQuarantine' => ['spam_quarantine_to', 'spam-quarantine'],
        'virusQuarantine' => ['virus_quarantine_to', 'virus-quarantine'],
        'bannedQuarantine' => ['banned_quarantine_to', 'banned-quarantine'],
        'badHeaderQuarantine' => ['bad_header_quarantine_to', 'bad-header-quarantine'],
    ];

    /**
     * Amavisd has no column for "always add the X-Spam headers". It adds them
     * from the tag level on, so a level below every score has the same result.
     */
    public const ALWAYS_INSERT_TAG_LEVEL = -999.0;

    public function __construct(
        public readonly ?int $id = null,
        public readonly string $policyName = '',
        public readonly ?float $spamTagLevel = null,
        public readonly ?float $spamTag2Level = null,
        public readonly ?float $spamKillLevel = null,
        public readonly string $spamSubjectTag = '',
        public readonly string $spamSubjectTag2 = '',
        public readonly bool $bypassVirusChecks = false,
        public readonly bool $bypassSpamChecks = false,
        public readonly bool $bypassBannedChecks = false,
        public readonly bool $bypassHeaderChecks = false,
        public readonly bool $virusLover = false,
        public readonly bool $spamLover = false,
        public readonly bool $bannedFilesLover = false,
        public readonly bool $badHeaderLover = false,
        public readonly ?bool $spamQuarantine = null,
        public readonly ?bool $virusQuarantine = null,
        public readonly ?bool $bannedQuarantine = null,
        public readonly ?bool $badHeaderQuarantine = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            policyName: $row['policy_name'] ?? '',
            spamTagLevel: isset($row['spam_tag_level']) ? (float) $row['spam_tag_level'] : null,
            spamTag2Level: isset($row['spam_tag2_level']) ? (float) $row['spam_tag2_level'] : null,
            spamKillLevel: isset($row['spam_kill_level']) ? (float) $row['spam_kill_level'] : null,
            spamSubjectTag: $row['spam_subject_tag'] ?? '',
            spamSubjectTag2: $row['spam_subject_tag2'] ?? '',
            bypassVirusChecks: self::ynToBool($row['bypass_virus_checks'] ?? 'N'),
            bypassSpamChecks: self::ynToBool($row['bypass_spam_checks'] ?? 'N'),
            bypassBannedChecks: self::ynToBool($row['bypass_banned_checks'] ?? 'N'),
            bypassHeaderChecks: self::ynToBool($row['bypass_header_checks'] ?? 'N'),
            virusLover: self::ynToBool($row['virus_lover'] ?? 'N'),
            spamLover: self::ynToBool($row['spam_lover'] ?? 'N'),
            bannedFilesLover: self::ynToBool($row['banned_files_lover'] ?? 'N'),
            badHeaderLover: self::ynToBool($row['bad_header_lover'] ?? 'N'),
            spamQuarantine: self::quarantineOfRow($row, 'spamQuarantine'),
            virusQuarantine: self::quarantineOfRow($row, 'virusQuarantine'),
            bannedQuarantine: self::quarantineOfRow($row, 'bannedQuarantine'),
            badHeaderQuarantine: self::quarantineOfRow($row, 'badHeaderQuarantine'),
        );
    }

    /**
     * Reads a form or JSON body.
     *
     * @throws \InvalidArgumentException with the name of the invalid field as message
     */
    public static function fromFormData(array $post): self
    {
        return new self(
            policyName: FormValue::text($post, 'policyName'),
            spamTagLevel: self::tagLevel($post),
            spamTag2Level: self::level($post, 'spamTag2Level'),
            spamKillLevel: self::level($post, 'spamKillLevel'),
            spamSubjectTag: self::subjectTag($post['spamSubjectTag'] ?? ''),
            spamSubjectTag2: self::subjectTag($post['spamSubjectTag2'] ?? ''),
            bypassVirusChecks: (bool) ($post['bypassVirusChecks'] ?? false),
            bypassSpamChecks: (bool) ($post['bypassSpamChecks'] ?? false),
            bypassBannedChecks: (bool) ($post['bypassBannedChecks'] ?? false),
            bypassHeaderChecks: (bool) ($post['bypassHeaderChecks'] ?? false),
            virusLover: (bool) ($post['virusLover'] ?? false),
            spamLover: (bool) ($post['spamLover'] ?? false),
            bannedFilesLover: (bool) ($post['bannedFilesLover'] ?? false),
            badHeaderLover: (bool) ($post['badHeaderLover'] ?? false),
            spamQuarantine: self::quarantineChoice($post, 'spamQuarantine'),
            virusQuarantine: self::quarantineChoice($post, 'virusQuarantine'),
            bannedQuarantine: self::quarantineChoice($post, 'bannedQuarantine'),
            badHeaderQuarantine: self::quarantineChoice($post, 'badHeaderQuarantine'),
        );
    }

    /**
     * A copy that keeps the fields of the stored policy which only an admin sets:
     * the banned and header bypass, and the four quarantine choices. The self-service
     * form has no field for them, and an absent field would otherwise clear them.
     */
    public function keepAdminFields(?self $stored): self
    {
        if ($stored === null) {
            return $this;
        }

        return new self(
            id: $this->id,
            policyName: $this->policyName,
            spamTagLevel: $this->spamTagLevel,
            spamTag2Level: $this->spamTag2Level,
            spamKillLevel: $this->spamKillLevel,
            spamSubjectTag: $this->spamSubjectTag,
            spamSubjectTag2: $this->spamSubjectTag2,
            bypassVirusChecks: $this->bypassVirusChecks,
            bypassSpamChecks: $this->bypassSpamChecks,
            bypassBannedChecks: $stored->bypassBannedChecks,
            bypassHeaderChecks: $stored->bypassHeaderChecks,
            virusLover: $this->virusLover,
            spamLover: $this->spamLover,
            bannedFilesLover: $this->bannedFilesLover,
            badHeaderLover: $this->badHeaderLover,
            spamQuarantine: $stored->spamQuarantine,
            virusQuarantine: $stored->virusQuarantine,
            bannedQuarantine: $stored->bannedQuarantine,
            badHeaderQuarantine: $stored->badHeaderQuarantine,
        );
    }

    /**
     * True when Amavisd adds the X-Spam headers to every message of the account.
     */
    public function alwaysInsertXSpamHeaders(): bool
    {
        return $this->spamTagLevel !== null && $this->spamTagLevel <= self::ALWAYS_INSERT_TAG_LEVEL;
    }

    /**
     * The policy in the field names that a JSON body uses. `alwaysInsertXSpamHeaders`
     * stays out, because it only writes `spamTagLevel` and would otherwise win over
     * a level that the body sets.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $values = [
            'spamTagLevel' => $this->spamTagLevel,
            'spamTag2Level' => $this->spamTag2Level,
            'spamKillLevel' => $this->spamKillLevel,
            'spamSubjectTag' => $this->spamSubjectTag,
            'spamSubjectTag2' => $this->spamSubjectTag2,
            'bypassVirusChecks' => $this->bypassVirusChecks,
            'bypassSpamChecks' => $this->bypassSpamChecks,
            'bypassBannedChecks' => $this->bypassBannedChecks,
            'bypassHeaderChecks' => $this->bypassHeaderChecks,
            'virusLover' => $this->virusLover,
            'spamLover' => $this->spamLover,
            'bannedFilesLover' => $this->bannedFilesLover,
            'badHeaderLover' => $this->badHeaderLover,
        ];
        foreach (array_keys(self::QUARANTINE_COLUMNS) as $field) {
            $values[$field] = $this->$field;
        }

        return $values;
    }

    /**
     * The columns of the policy row and their values.
     *
     * @return array<string, string|float|null>
     */
    public function columns(): array
    {
        $columns = [
            'spam_tag_level' => $this->spamTagLevel,
            'spam_tag2_level' => $this->spamTag2Level,
            'spam_kill_level' => $this->spamKillLevel,
            'spam_subject_tag' => $this->spamSubjectTag,
            'spam_subject_tag2' => $this->spamSubjectTag2,
            'bypass_virus_checks' => self::boolToYn($this->bypassVirusChecks),
            'bypass_spam_checks' => self::boolToYn($this->bypassSpamChecks),
            'bypass_banned_checks' => self::boolToYn($this->bypassBannedChecks),
            'bypass_header_checks' => self::boolToYn($this->bypassHeaderChecks),
            'virus_lover' => self::boolToYn($this->virusLover),
            'spam_lover' => self::boolToYn($this->spamLover),
            'banned_files_lover' => self::boolToYn($this->bannedFilesLover),
            'bad_header_lover' => self::boolToYn($this->badHeaderLover),
        ];
        foreach (self::QUARANTINE_COLUMNS as $field => [$column, $value]) {
            $columns[$column] = match ($this->$field) {
                true => $value,
                false => '',
                default => null,
            };
        }

        return $columns;
    }

    /**
     * The spam tag level of the body. `alwaysInsertXSpamHeaders` writes the
     * level that adds the headers to every message.
     */
    private static function tagLevel(array $post): ?float
    {
        if ((bool) ($post['alwaysInsertXSpamHeaders'] ?? false)) {
            return self::ALWAYS_INSERT_TAG_LEVEL;
        }

        return self::level($post, 'spamTagLevel');
    }

    /**
     * An empty level inherits the default. Text must not become 0, because
     * amavisd then tags or blocks almost every message.
     */
    private static function level(array $input, string $field): ?float
    {
        $raw = $input[$field] ?? null;
        if (is_string($raw)) {
            $raw = trim($raw);
        }
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_numeric($raw) || !is_finite((float) $raw)) {
            throw new \InvalidArgumentException($field);
        }
        return (float) $raw;
    }

    /**
     * Amavisd puts the tag in front of the subject as it is, so a trailing
     * space is what separates "[SPAM] " from the subject and must stay.
     */
    private static function subjectTag(mixed $value): string
    {
        $tag = is_string($value) ? ltrim($value) : '';

        return trim($tag) === '' ? '' : $tag;
    }

    /**
     * Reads a quarantine choice: null (or an empty value) inherits the Amavisd
     * setting, true quarantines, false does not.
     *
     * @throws \InvalidArgumentException when the value is none of them
     */
    private static function quarantineChoice(array $post, string $field): ?bool
    {
        $value = $post[$field] ?? null;
        if (in_array($value, [null, '', 'default'], true)) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (in_array($value, ['yes', 'no'], true)) {
            return $value === 'yes';
        }

        throw new \InvalidArgumentException($field);
    }

    private static function quarantineOfRow(array $row, string $field): ?bool
    {
        $value = $row[self::QUARANTINE_COLUMNS[$field][0]] ?? null;

        return $value === null ? null : trim((string) $value) !== '';
    }

    private static function ynToBool(string $value): bool
    {
        return strtoupper($value) === 'Y';
    }

    public static function boolToYn(bool $value): string
    {
        return $value ? 'Y' : 'N';
    }
}
