<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rules;

use App\Domain\Rules\Strategies\PercentageDiscountRule;
use PHPUnit\Framework\Attributes\Test;

final class PercentageDiscountRuleTest extends RuleTestCase
{
    #[Test]
    /** A06 */
    public function it_uses_the_current_total_and_rounds_half_up(): void
    {
        $effect = (new PercentageDiscountRule(['percent' => '12.5']))
            ->apply($this->state('7927225.00', '7416225.00'));

        self::assertSame('7416225.00', $effect->base->toString());
        self::assertSame('-927028.13', $effect->amount->toString());
        self::assertSame('927028.13', $effect->discountDelta->toString());
    }
}
