<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Utils\Ldif;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LdifTest extends TestCase
{
    public function testEntryWritesOneLinePerValue(): void
    {
        $ldif = Ldif::entry('mail=user@example.com,ou=Users,dc=example,dc=com', [
            'objectClass' => ['inetOrgPerson', 'mailUser'],
            'mail' => ['user@example.com'],
        ]);

        $this->assertSame(
            "dn: mail=user@example.com,ou=Users,dc=example,dc=com\n"
            . "objectClass: inetOrgPerson\nobjectClass: mailUser\nmail: user@example.com\n\n",
            $ldif,
        );
    }

    /**
     * A reader joins a continuation line by dropping its leading space, so the
     * folded line must give the value back unchanged.
     */
    public function testALongLineFoldsAndJoinsBackToTheValue(): void
    {
        $value = str_repeat('a', 200);
        $lines = explode("\n", rtrim(Ldif::line('description', $value)));

        $this->assertGreaterThan(1, count($lines));
        foreach ($lines as $index => $line) {
            $this->assertLessThanOrEqual(76, strlen($line));
            $this->assertSame($index === 0, !str_starts_with($line, ' '));
        }
        $this->assertSame("description: {$value}", implode('', array_map(
            static fn (string $line, int $index): string => $index === 0 ? $line : substr($line, 1),
            $lines,
            array_keys($lines),
        )));
    }

    #[DataProvider('unsafeValues')]
    public function testAValueThatLdifCannotHoldAsTextIsBase64Encoded(string $value): void
    {
        $this->assertFalse(Ldif::isSafe($value));
        $this->assertSame("cn:: " . base64_encode($value) . "\n", Ldif::line('cn', $value));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeValues(): array
    {
        return [
            'utf-8' => ['Käse'],
            'leading space' => [' value'],
            'leading colon' => [':value'],
            'leading less-than' => ['<value'],
            'trailing space' => ['value '],
        ];
    }

    public function testAPlainValueStaysReadable(): void
    {
        $this->assertTrue(Ldif::isSafe('active'));
        $this->assertSame("accountStatus: active\n", Ldif::line('accountStatus', 'active'));
        $this->assertSame("cn: \n", Ldif::line('cn', ''));
    }
}
