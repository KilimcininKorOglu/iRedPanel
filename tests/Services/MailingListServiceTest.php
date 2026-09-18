<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\MailingListService;
use PHPUnit\Framework\TestCase;

class MailingListServiceTest extends TestCase
{
    public function testParseAddressesNormalizesOneAddressPerLine(): void
    {
        // mlmmj stores subscribers lowercased, so a pasted duplicate in
        // another case must not become a second entry.
        $addresses = MailingListService::parseAddresses(" A@Example.com\r\n\nb@example.com,a@example.com\n");

        $this->assertSame(['a@example.com', 'b@example.com'], $addresses);
    }

    public function testParseAddressesReturnsNothingForBlankInput(): void
    {
        $this->assertSame([], MailingListService::parseAddresses(" \n\r\n"));
    }

    public function testParseAddressesNamesTheFirstInvalidAddress(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not-an-address');

        MailingListService::parseAddresses("ok@example.com\nNot-An-Address");
    }
}
