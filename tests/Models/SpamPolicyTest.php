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
