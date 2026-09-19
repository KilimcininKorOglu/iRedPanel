<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Utils\WholeNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WholeNumberTest extends TestCase
{
    /**
     * @return array<string, array{mixed, int}>
     */
    public static function validValues(): array
    {
        return [
            'empty form field' => ['', 0],
            'missing JSON field' => [null, 0],
            'form string' => ['1024', 1024],
            'padded form string' => [' 7 ', 7],
            'JSON integer' => [2048, 2048],
            'zero' => [0, 0],
        ];
    }

    #[DataProvider('validValues')]
    public function testParsesWholeNumbers(mixed $value, int $expected): void
    {
        $this->assertSame($expected, WholeNumber::parse($value));
    }

    /**
     * A negative or partial number must not turn into 0, because 0 means unlimited.
     *
     * @return array<string, array{mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'negative string' => ['-5'],
            'negative int' => [-5],
            'text' => ['abc'],
            'decimal string' => ['1.5'],
            'float' => [1.5],
            'bool' => [true],
            'array' => [[1]],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsOtherValues(mixed $value): void
    {
        $this->assertNull(WholeNumber::parse($value));
    }
}
