<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rules;

use App\Domain\Rules\Strategies\DiscountCapRule;
use PHPUnit\Framework\Attributes\Test;

final class DiscountCapRuleTest extends RuleTestCase
{
    #[Test]
    /** A09 */
    public function it_refunds_only_the_discount_above_the_cap_after_tax(): void
    {
        $state = $this->state('100.00', '84.70', '30.00');
        $effect = (new DiscountCapRule(['max_percent' => '20']))->apply($state);

        self::assertSame('100.00', $effect->base->toString());
        self::assertSame('10.00', $effect->amount->toString());
        self::assertSame('-10.00', $effect->discountDelta->toString());
        self::assertSame('94.70', $state->withEffect($effect)->total->toString());
    }

    #[Test]
    /** A11 */
    public function it_returns_zero_below_the_limit(): void
    {
        $effect = (new DiscountCapRule(['max_percent' => '20']))
            ->apply($this->state('100.00', '90.00', '10.00'));

        self::assertSame('0.00', $effect->amount->toString());
        self::assertSame('0.00', $effect->discountDelta->toString());
    }

    #[Test]
    /** A12 */
    public function a_second_cap_does_not_refund_the_same_discount_twice(): void
    {
        $rule = new DiscountCapRule(['max_percent' => '20']);
        $state = $this->state('100.00', '70.00', '30.00');
        $state = $state->withEffect($rule->apply($state));
        $second = $rule->apply($state);

        self::assertSame('80.00', $state->total->toString());
        self::assertSame('20.00', $state->discounts->toString());
        self::assertSame('0.00', $second->amount->toString());
    }
}
