<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Utils\IredapdList;
use PHPUnit\Framework\TestCase;

class IredapdListTest extends TestCase
{
    public function testRdnsNamesAcceptsHostsAndDomainSuffixes(): void
    {
        // wblist_rdns has a unique rdns column, so a repeated line must
        // collapse into one entry instead of failing the save.
        $names = IredapdList::rdnsNames([" .Dynamic.163data.com.cn\r", 'mail.example.com', '', '.dynamic.163data.com.cn']);

        $this->assertSame(['.dynamic.163data.com.cn', 'mail.example.com'], $names);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRdnsNames(): array
    {
        return [
            'space' => ['not a host'],
            'single label' => ['localhost'],
            'leading hyphen' => ['-bad.example.com'],
            'empty label' => ['bad..example.com'],
            'address' => ['user@example.com'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidRdnsNames')]
    public function testRdnsNamesRejectsAnEntryIredapdCannotMatch(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($name);
        IredapdList::rdnsNames(['.ok.example.com', $name]);
    }
}
