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

    public static function fromFormData(array $post): self
    {
        return new self(
            policyName: trim($post['policyName'] ?? ''),
            spamTagLevel: ($post['spamTagLevel'] ?? '') !== '' ? (float) $post['spamTagLevel'] : null,
            spamTag2Level: ($post['spamTag2Level'] ?? '') !== '' ? (float) $post['spamTag2Level'] : null,
            spamKillLevel: ($post['spamKillLevel'] ?? '') !== '' ? (float) $post['spamKillLevel'] : null,
            spamSubjectTag: trim($post['spamSubjectTag'] ?? ''),
            spamSubjectTag2: trim($post['spamSubjectTag2'] ?? ''),
            bypassVirusChecks: (bool) ($post['bypassVirusChecks'] ?? false),
            bypassSpamChecks: (bool) ($post['bypassSpamChecks'] ?? false),
            virusLover: (bool) ($post['virusLover'] ?? false),
            spamLover: (bool) ($post['spamLover'] ?? false),
            bannedFilesLover: (bool) ($post['bannedFilesLover'] ?? false),
            badHeaderLover: (bool) ($post['badHeaderLover'] ?? false),
        );
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
