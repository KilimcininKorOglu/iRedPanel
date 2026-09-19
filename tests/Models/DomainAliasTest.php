<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\DomainAlias;
use PHPUnit\Framework\TestCase;

class DomainAliasTest extends TestCase
{
    public function testFromFormDataNormalizesDomains(): void
    {
        $alias = DomainAlias::fromFormData(['aliasDomain' => ' Alias.Example ', 'targetDomain' => 'Example.COM', 'active' => 'on']);

        $this->assertSame('alias.example', $alias->aliasDomain);
        $this->assertSame('example.com', $alias->targetDomain);
        $this->assertTrue($alias->active);
    }

    /**
     * A present but false value must stay false; isset() read it as true.
     */
    public function testFalseStatusStaysInactive(): void
    {
        $this->assertFalse(DomainAlias::fromFormData(['aliasDomain' => 'a.example', 'targetDomain' => 'b.example', 'active' => false])->active);
        $this->assertFalse(DomainAlias::fromFormData(['aliasDomain' => 'a.example', 'targetDomain' => 'b.example'])->active);
    }
}
