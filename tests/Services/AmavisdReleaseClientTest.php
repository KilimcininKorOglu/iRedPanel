<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\AmavisdReleaseClient;
use PHPUnit\Framework\TestCase;

class AmavisdReleaseClientTest extends TestCase
{
    public function testBuildsAnAmPdpReleaseRequest(): void
    {
        $this->assertSame(
            "request=release\r\nmail_id=lJUJy9EyCqpy\r\nsecret_id=q2VOthYGUd5l\r\n"
                . "requested_by=postmaster%40testmail.local\r\nquar_type=Q\r\n\r\n",
            AmavisdReleaseClient::buildRequest('lJUJy9EyCqpy', 'q2VOthYGUd5l', 'postmaster@testmail.local')
        );
    }

    public function testRejectsAMailIdThatCouldInjectAnAttribute(): void
    {
        // A line break in mail_id would add attributes to the Amavisd request.
        $this->expectException(\InvalidArgumentException::class);
        AmavisdReleaseClient::buildRequest("abc\r\nrecipient=x@example.org", 'q2VOthYGUd5l', 'admin');
    }

    public function testAcceptsA2xxReply(): void
    {
        AmavisdReleaseClient::assertReleased("setreply=250 2.0.0 Ok,%20id=00276-01\r\n");
        $this->addToAssertionCount(1);
    }

    public function testRejectsAFailureReply(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('450 4.5.0 Failure');
        AmavisdReleaseClient::assertReleased("setreply=450 4.5.0 Failure\r\n");
    }

    public function testRejectsAReplyWithoutStatus(): void
    {
        $this->expectException(\RuntimeException::class);
        AmavisdReleaseClient::assertReleased('');
    }

    public function testReportsAnUnreachableSocket(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot connect to Amavisd');
        // Port 1 on loopback has no listener, so the connection is refused at once.
        AmavisdReleaseClient::release('127.0.0.1', 1, 'lJUJy9EyCqpy', 'q2VOthYGUd5l', 'admin');
    }
}
