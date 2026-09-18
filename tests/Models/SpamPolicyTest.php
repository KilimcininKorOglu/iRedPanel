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
}
