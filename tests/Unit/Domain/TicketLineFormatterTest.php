<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Ticket\TicketLineFormatter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\QuoteFixtures;

final class TicketLineFormatterTest extends TestCase
{
    #[Test]
    /** A27, A29 */
    public function it_formats_every_official_ticket_fixture_by_unicode_code_point(): void
    {
        $formatter = new TicketLineFormatter;
        $fixture = QuoteFixtures::ticketCases();

        foreach ($fixture['cases'] as $case) {
            $lines = $formatter->format($case['text']);

            self::assertSame($case['lines'], $lines, $case['id']);
            self::assertLessThanOrEqual($fixture['max_lines'], count($lines), $case['id']);
            foreach ($lines as $line) {
                self::assertLessThanOrEqual($fixture['width'], mb_strlen($line), $case['id']);
            }
        }
    }
}
