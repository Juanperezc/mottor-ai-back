<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rules;

use App\Domain\Rules\Strategies\TaxRule;
use PHPUnit\Framework\Attributes\Test;

final class TaxRuleTest extends RuleTestCase
{
    #[Test]
    /** A08 */
    public function it_taxes_the_current_total_without_increasing_discounts(): void
    {
        $effect = (new TaxRule(['name' => 'IVA', 'percent' => '21']))
            ->apply($this->state('7927225.00', '6345461.87', '1581763.13'));

        self::assertSame('6345461.87', $effect->base->toString());
        self::assertSame('1332546.99', $effect->amount->toString());
        self::assertSame('0.00', $effect->discountDelta->toString());
        self::assertSame('IVA 21%', $effect->label);
    }
}
