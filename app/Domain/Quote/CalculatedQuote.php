<?php

declare(strict_types=1);

namespace App\Domain\Quote;

use App\Domain\Money\Money;
use App\Domain\Rules\BreakdownStep;

final readonly class CalculatedQuote
{
    /**
     * @param  list<NormalizedItem>  $items
     * @param  list<array<string, mixed>>  $rules
     * @param  list<BreakdownStep>  $steps
     * @param  array<string, string>  $exchangeRates
     */
    public function __construct(
        public string $budgetId,
        public string $client,
        public string $event,
        public string $currency,
        public array $exchangeRates,
        public array $items,
        public array $rules,
        public array $steps,
        public Money $subtotal,
        public Money $total,
    ) {}
}
