<?php

declare(strict_types=1);

namespace App\Models;

class SpamPolicy
{
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
        public readonly bool $virusLover = false,
        public readonly bool $spamLover = false,
        public readonly bool $bannedFilesLover = false,
        public readonly bool $badHeaderLover = false,
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
            virusLover: self::ynToBool($row['virus_lover'] ?? 'N'),
            spamLover: self::ynToBool($row['spam_lover'] ?? 'N'),
            bannedFilesLover: self::ynToBool($row['banned_files_lover'] ?? 'N'),
            badHeaderLover: self::ynToBool($row['bad_header_lover'] ?? 'N'),
        );
    }

    /**
     * Reads a form or JSON body.
     *
     * @throws \InvalidArgumentException with the name of the invalid level field as message
     */
    public static function fromFormData(array $post): self
    {
        return new self(
            policyName: trim($post['policyName'] ?? ''),
            spamTagLevel: self::level($post, 'spamTagLevel'),
            spamTag2Level: self::level($post, 'spamTag2Level'),
            spamKillLevel: self::level($post, 'spamKillLevel'),
            spamSubjectTag: self::subjectTag($post['spamSubjectTag'] ?? ''),
            spamSubjectTag2: self::subjectTag($post['spamSubjectTag2'] ?? ''),
            bypassVirusChecks: (bool) ($post['bypassVirusChecks'] ?? false),
            bypassSpamChecks: (bool) ($post['bypassSpamChecks'] ?? false),
            virusLover: (bool) ($post['virusLover'] ?? false),
            spamLover: (bool) ($post['spamLover'] ?? false),
            bannedFilesLover: (bool) ($post['bannedFilesLover'] ?? false),
            badHeaderLover: (bool) ($post['badHeaderLover'] ?? false),
        );
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

    private static function ynToBool(string $value): bool
    {
        return strtoupper($value) === 'Y';
    }

    public static function boolToYn(bool $value): string
    {
        return $value ? 'Y' : 'N';
    }
}
