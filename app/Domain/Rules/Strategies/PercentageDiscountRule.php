<?php

declare(strict_types=1);

namespace App\Domain\Rules\Strategies;

use App\Domain\Rules\CalculationState;
use App\Domain\Rules\CommercialRule;
use App\Domain\Rules\RuleConfiguration;
use App\Domain\Rules\RuleEffect;
use App\Domain\Rules\RuleLabelFormatter;

final readonly class PercentageDiscountRule implements CommercialRule
{
    private string $percent;

    /** @param array<string, mixed> $configuration */
    public function __construct(array $configuration)
    {
        $this->percent = RuleConfiguration::percent($configuration, 'percent');
    }

    public function apply(CalculationState $state): RuleEffect
    {
        $discount = $state->total->percentage($this->percent);

        return new RuleEffect(
            $state->total,
            $discount->negated(),
            $discount,
            RuleLabelFormatter::number($this->percent).'% general',
        );
    }
}
