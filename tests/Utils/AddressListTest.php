<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Utils\AddressList;
use PHPUnit\Framework\TestCase;

class AddressListTest extends TestCase
{
    public function testParseNormalizesOneAddressPerLine(): void
    {
        // mlmmj stores subscribers lowercased, so a pasted duplicate in
        // another case must not become a second entry.
        $addresses = AddressList::parse(" A@Example.com\r\n\nb@example.com,a@example.com\n");

        $this->assertSame(['a@example.com', 'b@example.com'], $addresses);
    }

    public function testParseReturnsNothingForBlankInput(): void
    {
        $this->assertSame([], AddressList::parse(" \n\r\n"));
    }

    public function testParseNamesTheFirstInvalidAddress(): void
    {
        // Postfix and mlmmj cannot deliver to the text, so it must not be dropped silently either.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not-an-address');

        AddressList::parse("ok@example.com\nNot-An-Address");
    }
}
