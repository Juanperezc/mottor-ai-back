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

final readonly class FixedDiscountRule implements CommercialRule
{
    private string $amount;

    private string $currency;

    /** @param array<string, mixed> $configuration */
    public function __construct(array $configuration)
    {
        $this->amount = RuleConfiguration::decimal($configuration, 'amount');
        $this->currency = RuleConfiguration::string($configuration, 'currency');

        if (BigDecimal::of($this->amount)->isNegative()) {
            throw new DomainValidationException(['rules.amount' => ['A fixed discount cannot be negative.']]);
        }
    }

    public function apply(CalculationState $state): RuleEffect
    {
        $factor = $state->rates->factor($this->currency, $state->currency);
        $discount = Money::fromDecimal(
            BigDecimal::of($this->amount)->multipliedBy($factor),
            $state->roundingMode,
        );

        return new RuleEffect(
            $state->total,
            $discount->negated(),
            $discount,
            $this->currency.' '.RuleLabelFormatter::number($this->amount),
        );
    }
}
