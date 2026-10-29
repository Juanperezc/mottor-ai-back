<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rules;

use App\Domain\Rules\Strategies\CategoryDiscountRule;
use PHPUnit\Framework\Attributes\Test;

final class CategoryDiscountRuleTest extends RuleTestCase
{
    #[Test]
    /** A05 */
    public function it_uses_category_list_value_after_an_earlier_discount(): void
    {
        $state = $this->state('3000.00', '2800.00', '200.00', [
            $this->item(1, 'catering', 1, '1000.00', '1000.00'),
            $this->item(2, 'catering', 1, '1610.00', '1610.00'),
            $this->item(3, 'other', 1, '390.00', '390.00'),
        ]);

        $effect = (new CategoryDiscountRule([
            'category' => 'catering',
            'percent' => '15',
        ]))->apply($state);

        self::assertSame('2610.00', $effect->base->toString());
        self::assertSame('-391.50', $effect->amount->toString());
        self::assertSame('391.50', $effect->discountDelta->toString());
    }

    #[Test]
    /** A14, BT-21 */
    public function it_returns_zero_when_no_category_matches(): void
    {
        $effect = (new CategoryDiscountRule([
            'category' => 'missing',
            'percent' => '15',
        ]))->apply($this->state('100.00', '100.00'));

        self::assertSame('0.00', $effect->base->toString());
        self::assertSame('0.00', $effect->amount->toString());
        self::assertSame('0.00', $effect->discountDelta->toString());
    }
}
