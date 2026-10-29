<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Application\Quotes\DecimalInputNormalizer;
use App\Domain\Exception\DomainValidationException;
use App\Domain\Quote\CalculatedQuote;
use App\Domain\Quote\QuoteCalculator;
use Brick\Math\RoundingMode;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\QuoteFixtures;
use Tests\TestCase;

final class QuoteCalculatorTest extends TestCase
{
    #[Test]
    /** A01, A05, A15, A19 */
    public function it_calculates_the_official_case_step_by_step(): void
    {
        $quote = $this->calculate(QuoteFixtures::official());

        self::assertSame('4878500.00', $quote->subtotal->toString());
        self::assertSame('4722388.00', $quote->total->toString());
        self::assertSame(
            ['-119500.00', '-391500.00', '-436750.00', '-50000.00', '22050.00', '819588.00'],
            array_map(fn ($step) => $step->amount->toString(), $quote->steps),
        );
        self::assertSame(
            ['3x2 en mobiliario', '15% en catering', '10% general', 'ARS 50.000', 'Tope 20%', 'IVA 21%'],
            array_column(QuoteFixtures::officialExpected()['breakdown'], 'label'),
        );
        self::assertSame(
            array_column(QuoteFixtures::officialExpected()['breakdown'], 'label'),
            array_map(fn ($step) => $step->label, $quote->steps),
        );

        $this->assertMatchesExpectedFixture($quote, QuoteFixtures::officialExpected());
    }

    #[Test]
    /** A02, A03, A04, A06, A07, A08, A10, A15 */
    public function it_calculates_tax_before_the_cap_without_counting_tax_as_a_discount(): void
    {
        $quote = $this->calculate(QuoteFixtures::second());

        self::assertSame('7927225.00', $quote->subtotal->toString());
        self::assertSame('7832871.49', $quote->total->toString());
        self::assertSame('154862.63', $quote->steps[5]->amount->toString());
        $this->assertMatchesExpectedFixture($quote, QuoteFixtures::secondExpected());
    }

    #[Test]
    /** A20 */
    public function configured_rounding_mode_changes_a_rule_result(): void
    {
        $halfUp = $this->calculate(QuoteFixtures::second());

        config(['money.rounding_mode' => 'HALF_DOWN']);
        app()->forgetInstance(RoundingMode::class);
        app()->forgetInstance(QuoteCalculator::class);

        $halfDown = $this->calculate(QuoteFixtures::second());

        self::assertSame('-927028.13', $halfUp->steps[2]->amount->toString());
        self::assertSame('-927028.12', $halfDown->steps[2]->amount->toString());
    }

    #[Test]
    /** A16 */
    public function it_rejects_an_unknown_rule_type(): void
    {
        $input = QuoteFixtures::official();
        $input['rules'][0]['type'] = 'future_rule';

        $this->expectException(DomainValidationException::class);
        $this->calculate($input);
    }

    private function calculate(array $input): CalculatedQuote
    {
        return app(QuoteCalculator::class)->calculate(
            app(DecimalInputNormalizer::class)->normalize(QuoteFixtures::exact($input)),
        );
    }

    /** @param array<string, mixed> $expected */
    private function assertMatchesExpectedFixture(CalculatedQuote $quote, array $expected): void
    {
        self::assertSame($this->decimal($expected['subtotal']), $quote->subtotal->toString());
        self::assertSame($this->decimal($expected['total']), $quote->total->toString());
        self::assertSame(
            array_map(fn (array $item): string => $this->decimal($item['line_total']), $expected['items']),
            array_map(fn ($item): string => $item->lineTotal->toString(), $quote->items),
        );
        self::assertSame(
            array_map(fn (array $step): array => [
                $step['type'],
                $step['label'],
                $this->decimal($step['base']),
                $this->decimal($step['amount']),
                $this->decimal($step['running_total']),
            ], $expected['breakdown']),
            array_map(fn ($step): array => [
                $step->type,
                $step->label,
                $step->base->toString(),
                $step->amount->toString(),
                $step->runningTotal->toString(),
            ], $quote->steps),
        );
    }

    private function decimal(int|float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
