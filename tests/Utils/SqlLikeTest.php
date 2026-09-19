<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Utils\SqlLike;
use PHPUnit\Framework\TestCase;

class SqlLikeTest extends TestCase
{
    public function testEscapesTheWildcardsAndTheEscapeCharacter(): void
    {
        // A search for "john_doe" must not also match "john-doe", and "%" must not match everything.
        $this->assertSame('john!_doe', SqlLike::escape('john_doe'));
        $this->assertSame('!%', SqlLike::escape('%'));
        $this->assertSame('a!!b', SqlLike::escape('a!b'));
        $this->assertSame('back\\slash', SqlLike::escape('back\\slash'));
    }
}
