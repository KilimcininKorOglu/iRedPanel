<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\SpamPolicy;
use PHPUnit\Framework\TestCase;

class SpamPolicyTest extends TestCase
{
    public function testKeepsTheSpaceAfterASubjectTag(): void
    {
        // Amavisd prepends the tag as it is; without the space the subject reads "[SPAM]Hello".
        $policy = SpamPolicy::fromFormData(['spamSubjectTag' => '[SPAM?] ', 'spamSubjectTag2' => '  [SPAM] ']);

        $this->assertSame('[SPAM?] ', $policy->spamSubjectTag);
        $this->assertSame('[SPAM] ', $policy->spamSubjectTag2);
    }

    public function testTreatsABlankSubjectTagAsNoTag(): void
    {
        $this->assertSame('', SpamPolicy::fromFormData(['spamSubjectTag2' => '   '])->spamSubjectTag2);
    }

    public function testReadsLevelsFromFormTextAndJsonNumbers(): void
    {
        $policy = SpamPolicy::fromFormData(['spamTagLevel' => ' -2.5 ', 'spamTag2Level' => 6, 'spamKillLevel' => '']);

        $this->assertSame(-2.5, $policy->spamTagLevel);
        $this->assertSame(6.0, $policy->spamTag2Level);
        $this->assertNull($policy->spamKillLevel);
    }

    public function testReadsTheQuarantineChoiceOfAForm(): void
    {
        $policy = SpamPolicy::fromFormData([
            'spamQuarantine' => 'yes',
            'virusQuarantine' => 'no',
            'bannedQuarantine' => 'default',
        ]);

        $this->assertTrue($policy->spamQuarantine);
        $this->assertFalse($policy->virusQuarantine);
        $this->assertNull($policy->bannedQuarantine);
        $this->assertNull($policy->badHeaderQuarantine);
    }

    public function testWritesTheQuarantineColumns(): void
    {
        $columns = SpamPolicy::fromFormData([
            'spamQuarantine' => true,
            'virusQuarantine' => false,
            'bypassBannedChecks' => true,
        ])->columns();

        $this->assertSame('spam-quarantine', $columns['spam_quarantine_to']);
        $this->assertSame('', $columns['virus_quarantine_to']);
        $this->assertNull($columns['banned_quarantine_to']);
        $this->assertSame('Y', $columns['bypass_banned_checks']);
        $this->assertSame('N', $columns['bypass_header_checks']);
    }

    public function testReadsTheQuarantineChoiceOfARow(): void
    {
        $policy = SpamPolicy::fromRow([
            'spam_quarantine_to' => 'spam-quarantine',
            'virus_quarantine_to' => '',
            'bypass_header_checks' => 'Y',
        ]);

        $this->assertTrue($policy->spamQuarantine);
        $this->assertFalse($policy->virusQuarantine);
        $this->assertNull($policy->bannedQuarantine);
        $this->assertTrue($policy->bypassHeaderChecks);
    }

    public function testRejectsAnUnknownQuarantineChoice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SpamPolicy::fromFormData(['spamQuarantine' => 'maybe']);
    }

    public function testAlwaysInsertHeadersSetsAndReadsTheTagLevel(): void
    {
        $policy = SpamPolicy::fromFormData(['alwaysInsertXSpamHeaders' => true, 'spamTagLevel' => '2.0']);

        $this->assertSame(SpamPolicy::ALWAYS_INSERT_TAG_LEVEL, $policy->spamTagLevel);
        $this->assertTrue($policy->alwaysInsertXSpamHeaders());
        $this->assertFalse(SpamPolicy::fromFormData(['spamTagLevel' => '2.0'])->alwaysInsertXSpamHeaders());
    }

    public function testKeepAdminFieldsRestoresTheFieldsOfAStoredPolicy(): void
    {
        $stored = SpamPolicy::fromRow(['spam_quarantine_to' => 'spam-quarantine', 'bypass_banned_checks' => 'Y']);
        $posted = SpamPolicy::fromFormData(['spamTagLevel' => '3']);

        $policy = $posted->keepAdminFields($stored);

        $this->assertSame(3.0, $policy->spamTagLevel);
        $this->assertTrue($policy->spamQuarantine);
        $this->assertTrue($policy->bypassBannedChecks);
    }

    public function testRejectsALevelThatIsNotAFiniteNumber(): void
    {
        // A cast turns "abc" into 0, and a tag level of 0 marks almost every message as spam.
        foreach (['abc', '1e999', true, ['1']] as $value) {
            try {
                SpamPolicy::fromFormData(['spamKillLevel' => $value]);
                $this->fail('accepted ' . var_export($value, true));
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('spamKillLevel', $e->getMessage());
            }
        }
    }
}
