<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\QuoteFixtures;

final class FixtureIntegrityTest extends TestCase
{
    #[Test]
    public function copied_fixtures_match_the_approved_specs_commit(): void
    {
        self::assertSame('3ef1401a69e29d831d43ba67364c10858c6a2865', QuoteFixtures::SOURCE_COMMIT);

        foreach (QuoteFixtures::SHA256 as $name => $hash) {
            self::assertSame($hash, hash_file('sha256', QuoteFixtures::path($name)), $name);
        }
    }
}
