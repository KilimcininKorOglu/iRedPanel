<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\MlmmjOptions;
use PHPUnit\Framework\TestCase;

class MlmmjOptionsTest extends TestCase
{
    public function testEmptyOptionsAreOffAndBlank(): void
    {
        $options = MlmmjOptions::empty()->toArray();

        $this->assertFalse($options['moderated']);
        $this->assertSame('', $options['subjectPrefix']);
        $this->assertSame([], $options['customHeaders']);
    }

    public function testFromProfileReadsYesNoAndLists(): void
    {
        $options = MlmmjOptions::fromProfile('list@example.com', [
            'moderated' => 'yes',
            'close_list' => 'no',
            'subject_prefix' => '[list]',
            'remove_headers' => ['Received:', 'Message-ID:'],
        ])->toArray();

        $this->assertTrue($options['moderated']);
        $this->assertFalse($options['closeList']);
        $this->assertSame('[list]', $options['subjectPrefix']);
        $this->assertSame(['Received:', 'Message-ID:'], $options['removeHeaders']);
    }

    public function testFromProfileHidesTheValuesThatMlmmjadminManages(): void
    {
        $options = MlmmjOptions::fromProfile('list@example.com', [
            'custom_headers' => ['Reply-To: list@example.com', 'X-Team: support'],
            'extra_addresses' => ['list@example.com', 'team@example.com'],
        ])->toArray();

        $this->assertSame(['X-Team: support'], $options['customHeaders']);
        $this->assertSame(['team@example.com'], $options['extraAddresses']);
    }

    public function testFormInputReadsCheckboxesAndLines(): void
    {
        $current = MlmmjOptions::fromProfile('list@example.com', ['moderated' => 'yes', 'close_list' => 'yes']);

        $options = $current->with([
            'closeList' => 'on',
            'customHeaders' => "X-Team: support\n\nX-Site: example\n",
            'subjectPrefix' => ' [list] ',
        ], true)->toArray();

        $this->assertTrue($options['closeList']);
        // A checkbox that the browser does not send means off.
        $this->assertFalse($options['moderated']);
        $this->assertSame(['X-Team: support', 'X-Site: example'], $options['customHeaders']);
        $this->assertSame('[list]', $options['subjectPrefix']);
    }

    public function testJsonInputKeepsTheFieldsThatTheBodyDoesNotCarry(): void
    {
        $current = MlmmjOptions::fromProfile('list@example.com', ['moderated' => 'yes', 'subject_prefix' => '[list]']);

        $options = $current->with(['closeList' => true], false)->toArray();

        $this->assertTrue($options['closeList']);
        $this->assertTrue($options['moderated']);
        $this->assertSame('[list]', $options['subjectPrefix']);
    }

    public function testJsonInputRefusesAWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MlmmjOptions::empty()->with(['moderated' => 'yes'], false);
    }

    public function testInputRefusesAnInvalidAddress(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MlmmjOptions::empty()->with(['extraAddresses' => ['not-an-address']], false);
    }

    public function testParamsJoinTheListsForMlmmjadmin(): void
    {
        $params = MlmmjOptions::empty()->with([
            'moderated' => true,
            'customHeaders' => ['X-Team: support', 'X-Site: example'],
            'removeHeaders' => ['Received:', 'Message-ID:'],
        ], false)->params();

        $this->assertSame('yes', $params['moderated']);
        $this->assertSame('no', $params['close_list']);
        $this->assertSame("X-Team: support\nX-Site: example", $params['custom_headers']);
        $this->assertSame('Received:,Message-ID:', $params['remove_headers']);
    }

    public function testValidSubscriptionRefusesAnUnknownVersion(): void
    {
        $this->assertSame('digest', MlmmjOptions::validSubscription('digest'));

        $this->expectException(\InvalidArgumentException::class);
        MlmmjOptions::validSubscription('weekly');
    }
}
