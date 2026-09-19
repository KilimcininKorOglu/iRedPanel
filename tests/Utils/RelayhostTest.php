<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Utils\Relayhost;
use PHPUnit\Framework\TestCase;

class RelayhostTest extends TestCase
{
    public function testAcceptsPostfixNextHopForms(): void
    {
        foreach (['relay.example.com', 'relay.example.com:587', '[relay.example.com]', '[relay.example.com]:25', '192.0.2.10:2525', '[192.0.2.10]', '[2001:db8::1]:25'] as $relayhost) {
            $this->assertTrue(Relayhost::isValid($relayhost), $relayhost);
        }
    }

    public function testRejectsValuesThatPostfixCannotRoute(): void
    {
        // Postfix defers every message of the account when the next hop does not parse.
        foreach (['', 'bad host;rm', 'smtp:[relay.example.com]:25', 'relay.example.com:0', 'relay.example.com:70000', '2001:db8::1', '[relay]:x', '-bad.example.com'] as $relayhost) {
            $this->assertFalse(Relayhost::isValid($relayhost), $relayhost);
        }
    }
}
