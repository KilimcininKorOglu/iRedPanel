<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\MailList;
use PHPUnit\Framework\TestCase;

class MailListTest extends TestCase
{
    public function testFromFormDataReadsATextareaPost(): void
    {
        $list = MailList::fromFormData('E2E@Example.COM', [
            'name' => '  Sales  ',
            'accessPolicy' => 'membersOnly',
            'active' => '1',
            'members' => "one@example.com\ntwo@example.com",
            'moderators' => 'boss@example.com',
            'maxMessageSize' => '2048',
        ]);

        $this->assertSame('e2e@example.com', $list->address);
        $this->assertSame('example.com', $list->domain);
        $this->assertSame('Sales', $list->name);
        $this->assertSame('membersOnly', $list->accessPolicy);
        $this->assertTrue($list->active);
        $this->assertSame(['one@example.com', 'two@example.com'], $list->members);
        $this->assertSame(['boss@example.com'], $list->moderators);
        $this->assertSame([], $list->allowedSenders);
        $this->assertSame(2048, $list->maxMessageSize);
    }

    public function testFromFormDataReadsAJsonBody(): void
    {
        $list = MailList::fromFormData('list@example.com', [
            'members' => ['one@example.com', 'two@example.com'],
            'active' => false,
        ]);

        $this->assertSame(['one@example.com', 'two@example.com'], $list->members);
        $this->assertFalse($list->active);
        $this->assertSame('public', $list->accessPolicy);
        $this->assertSame(0, $list->maxMessageSize);
    }

    public function testAnUnknownAccessPolicyIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MailList::validAccessPolicy('everyone');
    }

    public function testMaxMessageSizeTakesWholeNumbersOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MailList::fromFormData('list@example.com', ['maxMessageSize' => '2 MB']);
    }

    public function testToArrayAnswersEveryProfileField(): void
    {
        $list = new MailList('list@example.com', 'example.com', 'Sales');

        $this->assertSame([
            'address' => 'list@example.com',
            'domain' => 'example.com',
            'name' => 'Sales',
            'accessPolicy' => 'public',
            'active' => true,
            'members' => [],
            'moderators' => [],
            'allowedSenders' => [],
            'maxMessageSize' => 0,
        ], $list->toArray());
    }
}
