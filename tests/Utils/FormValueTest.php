<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Exceptions\InvalidInputException;
use App\Utils\FormValue;
use PHPUnit\Framework\TestCase;

class FormValueTest extends TestCase
{
    public function testTrimsTextAndUsesTheDefault(): void
    {
        $this->assertSame('abc', FormValue::text(['k' => '  abc '], 'k'));
        $this->assertSame('dovecot', FormValue::text([], 'k', 'dovecot'));
        $this->assertSame('', FormValue::text(['k' => null], 'k'));
    }

    /**
     * A JSON number in a text field is accepted as its text, so validation can reject it with a message.
     */
    public function testAcceptsJsonNumbersAsText(): void
    {
        $this->assertSame('123', FormValue::text(['k' => 123], 'k'));
        $this->assertSame('1.5', FormValue::text(['k' => 1.5], 'k'));
    }

    /**
     * trim() on these values throws a TypeError, which the API reported as a server error.
     */
    public function testRejectsArraysAndBooleans(): void
    {
        foreach ([['a'], true, false] as $value) {
            try {
                FormValue::text(['name' => $value], 'name');
                $this->fail('A non-scalar text field must be rejected');
            } catch (InvalidInputException $e) {
                $this->assertSame('name must be a string', $e->getMessage());
                $this->assertSame('common.msg_invalid_input', $e->translationKey);
            }
        }
    }
}
