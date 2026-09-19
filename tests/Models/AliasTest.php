<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\Alias;
use PHPUnit\Framework\TestCase;

class AliasTest extends TestCase
{
    public function testAcceptsEveryPolicyThatTheFormsOffer(): void
    {
        foreach (Alias::ACCESS_POLICIES as $policy) {
            $this->assertSame($policy, Alias::validAccessPolicy($policy));
        }
    }

    public function testRejectsAPolicyThatIredapdDoesNotEnforce(): void
    {
        // An unknown value reaches the alias table and removes the posting restriction the admin chose.
        foreach (['bogus', '', 'membersonly', null, 1] as $policy) {
            try {
                Alias::validAccessPolicy($policy);
                $this->fail('accepted ' . var_export($policy, true));
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('membersOnly', $e->getMessage());
            }
        }
    }
}
