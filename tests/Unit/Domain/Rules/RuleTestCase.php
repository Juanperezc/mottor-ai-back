<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rules;

use App\Domain\Money\Money;
use App\Domain\Money\RateSet;
use App\Domain\Quote\NormalizedItem;
use App\Domain\Rules\CalculationState;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use PHPUnit\Framework\TestCase;

abstract class RuleTestCase extends TestCase
{
    /** @param list<NormalizedItem> $items @param array<string, string> $rates */
    final protected function state(
        string $subtotal,
        string $total,
        string $discounts = '0.00',
        array $items = [],
        array $rates = [],
        string $currency = 'ARS',
    ): CalculationState {
        return new CalculationState(
            Money::of($subtotal),
            Money::of($total),
            Money::of($discounts),
            $items,
            new RateSet($rates),
            $currency,
            RoundingMode::HalfUp,
        );
    }

    final protected function item(
        int $id,
        string $category,
        int $quantity,
        string $unitPrice,
        string $lineTotal,
    ): NormalizedItem {
        return new NormalizedItem(
            $id,
            'Test item',
            $category,
            $quantity,
            $unitPrice,
            'ARS',
            BigDecimal::of($unitPrice),
            Money::of($lineTotal),
        );
    }
}
