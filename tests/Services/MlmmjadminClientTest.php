<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\MlmmjadminClient;
use PHPUnit\Framework\TestCase;

class MlmmjadminClientTest extends TestCase
{
    public function testMembersOnlyPolicyLetsOnlySubscribersPost(): void
    {
        // mlmmj enforces the posting rule itself, so the SQL access policy
        // must reach the list profile or a members-only list stays open.
        $params = MlmmjadminClient::listParams('Team', 'membersOnly', 1024, ['a@example.com', 'b@example.com']);

        $this->assertSame([
            'name' => 'Team',
            'max_message_size' => '1024',
            'owner' => 'a@example.com,b@example.com',
            'only_subscriber_can_post' => 'yes',
            'only_moderator_can_post' => 'no',
        ], $params);
    }

    public function testModeratorsOnlyPolicyLetsOnlyModeratorsPost(): void
    {
        $params = MlmmjadminClient::listParams('', 'moderatorsOnly', 0, ['o@example.com']);

        $this->assertSame('no', $params['only_subscriber_can_post']);
        $this->assertSame('yes', $params['only_moderator_can_post']);
    }

    public function testPublicPolicyOverridesTheSubscriberOnlyDefault(): void
    {
        // mlmmjadmin defaults to only_subscriber_can_post=yes, so a public
        // list must send "no" explicitly.
        $params = MlmmjadminClient::listParams('', 'public', -5, ['o@example.com']);

        $this->assertSame('no', $params['only_subscriber_can_post']);
        $this->assertSame('no', $params['only_moderator_can_post']);
        $this->assertSame('0', $params['max_message_size']);
    }

    public function testParseResponseReturnsData(): void
    {
        $this->assertSame(['a@example.com'], MlmmjadminClient::parseResponse('{"_success": true, "_data": ["a@example.com"]}'));
        $this->assertNull(MlmmjadminClient::parseResponse('{"_success": true}'));
    }

    public function testParseResponseThrowsTheApiMessageOnFailure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('INVALID_MLMMJADMIN_API_AUTH_TOKEN');

        MlmmjadminClient::parseResponse('{"_success": false, "_msg": "INVALID_MLMMJADMIN_API_AUTH_TOKEN"}');
    }

    public function testParseResponseRejectsNonJson(): void
    {
        $this->expectException(\RuntimeException::class);

        MlmmjadminClient::parseResponse('<html>502 Bad Gateway</html>');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function addressWriters(): array
    {
        return ['subscribers' => ['addSubscribers'], 'moderators' => ['setModerators']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('addressWriters')]
    public function testInvalidAddressIsRejectedBeforeAnyRequest(string $method): void
    {
        $client = new MlmmjadminClient('http://127.0.0.1:1/api', 'token');

        $this->expectException(\InvalidArgumentException::class);

        $client->$method('list@example.com', ['valid@example.com', 'not an address']);
    }
}
