<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rules;

use App\Domain\Rules\Strategies\QuantityPromoRule;
use PHPUnit\Framework\Attributes\Test;

final class QuantityPromoRuleTest extends RuleTestCase
{
    #[Test]
    /** A03, A13 */
    public function it_counts_free_units_per_matching_item_as_a_discount(): void
    {
        $state = $this->state('369800.00', '369800.00', items: [
            $this->item(1, 'mobiliario', 16, '9500.00', '152000.00'),
            $this->item(2, 'mobiliario', 121, '1800.00', '217800.00'),
        ]);

        $effect = (new QuantityPromoRule([
            'category' => 'mobiliario',
            'buy' => 3,
            'pay' => 2,
        ]))->apply($state);

        self::assertSame('369800.00', $effect->base->toString());
        self::assertSame('-119500.00', $effect->amount->toString());
        self::assertSame('119500.00', $effect->discountDelta->toString());
        self::assertSame('3x2 en mobiliario', $effect->label);
    }

    #[Test]
    /** A14, BT-21 */
    public function it_returns_zero_when_no_category_matches(): void
    {
        $effect = (new QuantityPromoRule([
            'category' => 'missing',
            'buy' => 3,
            'pay' => 2,
        ]))->apply($this->state('100.00', '100.00', items: [
            $this->item(1, 'other', 3, '10.00', '30.00'),
        ]));

        self::assertSame('0.00', $effect->base->toString());
        self::assertSame('0.00', $effect->amount->toString());
        self::assertSame('0.00', $effect->discountDelta->toString());
    }
}
