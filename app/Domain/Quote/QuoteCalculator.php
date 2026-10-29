<?php

declare(strict_types=1);

namespace App\Domain\Quote;

use App\Domain\Money\Money;
use App\Domain\Money\RateSet;
use App\Domain\Rules\CalculationState;
use App\Domain\Rules\RuleEngine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class QuoteCalculator
{
    public function __construct(
        private RuleEngine $engine,
        private RoundingMode $roundingMode = RoundingMode::HalfUp,
    ) {}

    /** @param array<string, mixed> $input */
    public function calculate(array $input): CalculatedQuote
    {
        /** @var array<string, mixed> $budget */
        $budget = $input['budget'];
        /** @var array<string, string> $rateValues */
        $rateValues = $input['exchange_rates'] ?? [];
        $rates = new RateSet($rateValues, $this->roundingMode);
        $currency = (string) $input['currency'];
        $items = [];
        $subtotal = Money::zero($this->roundingMode);

        /** @var list<array<string, mixed>> $itemInputs */
        $itemInputs = $budget['items'];
        foreach ($itemInputs as $item) {
            $quantity = (int) $item['quantity'];
            $factor = $rates->factor((string) $item['currency'], $currency);
            $outputUnitPrice = BigDecimal::of((string) $item['unit_price'])->multipliedBy($factor);
            $lineTotal = Money::fromDecimal(
                $outputUnitPrice->multipliedBy($quantity),
                $this->roundingMode,
            );
            $items[] = new NormalizedItem(
                (int) $item['id'],
                (string) $item['description'],
                (string) $item['category'],
                $quantity,
                (string) $item['unit_price'],
                (string) $item['currency'],
                $outputUnitPrice,
                $lineTotal,
            );
            $subtotal = $subtotal->plus($lineTotal);
        }

        $initialState = new CalculationState(
            $subtotal,
            $subtotal,
            Money::zero($this->roundingMode),
            $items,
            $rates,
            $currency,
            $this->roundingMode,
        );

        /** @var list<array<string, mixed>> $rules */
        $rules = $input['rules'];
        $result = $this->engine->calculate($initialState, $rules);

        return new CalculatedQuote(
            (string) $budget['id'],
            (string) $budget['client'],
            (string) $budget['event'],
            $currency,
            $rates->all(),
            $items,
            $rules,
            $result['steps'],
            $subtotal,
            $result['state']->total,
        );
    }
}
