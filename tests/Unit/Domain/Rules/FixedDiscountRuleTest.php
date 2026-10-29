<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rules;

use App\Domain\Rules\Strategies\FixedDiscountRule;
use PHPUnit\Framework\Attributes\Test;

final class FixedDiscountRuleTest extends RuleTestCase
{
    #[Test]
    /** A07 */
    public function it_converts_the_fixed_amount_to_the_quote_currency(): void
    {
        $state = $this->state(
            '7927225.00',
            '6489196.87',
            rates: ['USD_ARS' => '1437.35'],
        );
        $effect = (new FixedDiscountRule([
            'amount' => '100.00',
            'currency' => 'USD',
        ]))->apply($state);

        self::assertSame('6489196.87', $effect->base->toString());
        self::assertSame('-143735.00', $effect->amount->toString());
        self::assertSame('143735.00', $effect->discountDelta->toString());
        self::assertSame('USD 100', $effect->label);
    }
}
