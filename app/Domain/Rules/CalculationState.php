<?php

declare(strict_types=1);

namespace App\Domain\Rules;

use App\Domain\Money\Money;
use App\Domain\Money\RateSet;
use App\Domain\Quote\NormalizedItem;
use Brick\Math\RoundingMode;

final readonly class CalculationState
{
    /** @param list<NormalizedItem> $items */
    public function __construct(
        public Money $listSubtotal,
        public Money $total,
        public Money $discounts,
        public array $items,
        public RateSet $rates,
        public string $currency,
        public RoundingMode $roundingMode,
    ) {}

    public function withEffect(RuleEffect $effect): self
    {
        return new self(
            $this->listSubtotal,
            $this->total->plus($effect->amount),
            $this->discounts->plus($effect->discountDelta),
            $this->items,
            $this->rates,
            $this->currency,
            $this->roundingMode,
        );
    }

    public function listValueForCategory(string $category): Money
    {
        $value = Money::zero($this->roundingMode);

        foreach ($this->items as $item) {
            if ($item->category === $category) {
                $value = $value->plus($item->lineTotal);
            }
        }

        return $value;
    }
}
