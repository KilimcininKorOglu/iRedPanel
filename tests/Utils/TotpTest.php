<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Exceptions\InvalidInputException;
use App\Utils\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** The RFC 6238 test secret "12345678901234567890" in base32. */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function rfcVectors(): array
    {
        return [
            // A plain year would become an integer key, so the name carries a prefix.
            'year 1970' => [59, '287082'],
            'year 2005' => [1111111109, '081804'],
            'year 2009' => [1234567890, '005924'],
            'year 2033' => [2000000000, '279037'],
        ];
    }

    #[DataProvider('rfcVectors')]
    public function testCodeMatchesTheRfcVectors(int $time, string $expected): void
    {
        $this->assertSame($expected, Totp::code(self::RFC_SECRET, $time));
    }

    public function testEveryCodeInTheSameStepIsEqual(): void
    {
        $this->assertSame(Totp::code(self::RFC_SECRET, 60), Totp::code(self::RFC_SECRET, 89));
        $this->assertNotSame(Totp::code(self::RFC_SECRET, 60), Totp::code(self::RFC_SECRET, 90));
    }

    public function testVerifyAcceptsTheNeighbouringSteps(): void
    {
        $now = 1_700_000_000;

        $this->assertTrue(Totp::verify(self::RFC_SECRET, Totp::code(self::RFC_SECRET, $now), $now));
        $this->assertTrue(Totp::verify(self::RFC_SECRET, Totp::code(self::RFC_SECRET, $now - Totp::PERIOD), $now));
        $this->assertTrue(Totp::verify(self::RFC_SECRET, Totp::code(self::RFC_SECRET, $now + Totp::PERIOD), $now));
        $this->assertFalse(Totp::verify(self::RFC_SECRET, Totp::code(self::RFC_SECRET, $now + 2 * Totp::PERIOD), $now));
    }

    public function testVerifyIgnoresSpacesAndRefusesAWrongLength(): void
    {
        $now = 1_700_000_000;
        $code = Totp::code(self::RFC_SECRET, $now);

        $this->assertTrue(Totp::verify(self::RFC_SECRET, substr($code, 0, 3) . ' ' . substr($code, 3), $now));
        $this->assertFalse(Totp::verify(self::RFC_SECRET, substr($code, 0, 5), $now));
        $this->assertFalse(Totp::verify(self::RFC_SECRET, '', $now));
    }

    public function testBase32RoundTrip(): void
    {
        $this->assertSame(self::RFC_SECRET, Totp::encodeBase32('12345678901234567890'));
        $this->assertSame('12345678901234567890', Totp::decodeBase32(self::RFC_SECRET));
    }

    public function testDecodeAcceptsLowercaseSpacesAndPadding(): void
    {
        $this->assertSame(
            Totp::decodeBase32(self::RFC_SECRET),
            Totp::decodeBase32(strtolower(Totp::readable(self::RFC_SECRET)) . '='),
        );
    }

    public function testDecodeRefusesACharacterOutsideTheAlphabet(): void
    {
        $this->expectException(InvalidInputException::class);
        Totp::decodeBase32('GEZDGNBV1');
    }

    public function testDecodeRefusesAnEmptySecret(): void
    {
        $this->expectException(InvalidInputException::class);
        Totp::decodeBase32('  ');
    }

    public function testGenerateSecretIsUsableAndRandom(): void
    {
        $secret = Totp::generateSecret();

        $this->assertSame(32, strlen($secret));
        $this->assertSame(20, strlen(Totp::decodeBase32($secret)));
        $this->assertNotSame($secret, Totp::generateSecret());
        $this->assertTrue(Totp::verify($secret, Totp::code($secret, 1_700_000_000), 1_700_000_000));
    }

    public function testUriCarriesTheAccountAndTheParameters(): void
    {
        $uri = Totp::uri('ABCD', 'admin@example.com', 'iRedPanel');

        $this->assertSame(
            'otpauth://totp/iRedPanel:admin%40example.com?secret=ABCD&issuer=iRedPanel&algorithm=SHA1&digits=6&period=30',
            $uri,
        );
    }

    public function testReadableGroupsTheSecret(): void
    {
        $this->assertSame('ABCD EFGH IJ', Totp::readable('ABCDEFGHIJ'));
    }
}
