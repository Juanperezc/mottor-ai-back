<?php

declare(strict_types=1);

namespace App\Domain\Rules\Strategies;

use App\Domain\Exception\DomainValidationException;
use App\Domain\Money\Money;
use App\Domain\Rules\CalculationState;
use App\Domain\Rules\CommercialRule;
use App\Domain\Rules\RuleConfiguration;
use App\Domain\Rules\RuleEffect;
use App\Domain\Rules\RuleLabelFormatter;
use Brick\Math\BigDecimal;

final readonly class QuantityPromoRule implements CommercialRule
{
    private string $category;

    private int $buy;

    private int $pay;

    /** @param array<string, mixed> $configuration */
    public function __construct(array $configuration)
    {
        $this->category = RuleConfiguration::string($configuration, 'category');
        $this->buy = RuleConfiguration::integer($configuration, 'buy', 1);
        $this->pay = RuleConfiguration::integer($configuration, 'pay');

        if ($this->pay > $this->buy) {
            throw new DomainValidationException(['rules.pay' => ['The pay value cannot exceed buy.']]);
        }
    }

    public function apply(CalculationState $state): RuleEffect
    {
        $discount = BigDecimal::zero();

        foreach ($state->items as $item) {
            if ($item->category !== $this->category) {
                continue;
            }

            $freeUnits = intdiv($item->quantity, $this->buy) * ($this->buy - $this->pay);
            $discount = $discount->plus($item->outputUnitPrice->multipliedBy($freeUnits));
        }

        $discountMoney = Money::fromDecimal($discount, $state->roundingMode);

        return new RuleEffect(
            $state->listValueForCategory($this->category),
            $discountMoney->negated(),
            $discountMoney,
            RuleLabelFormatter::number($this->buy).'x'.RuleLabelFormatter::number($this->pay)." en {$this->category}",
        );
    }
}
