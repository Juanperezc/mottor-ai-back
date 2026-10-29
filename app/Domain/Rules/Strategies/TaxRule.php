<?php

declare(strict_types=1);

namespace App\Domain\Rules\Strategies;

use App\Domain\Money\Money;
use App\Domain\Rules\CalculationState;
use App\Domain\Rules\CommercialRule;
use App\Domain\Rules\RuleConfiguration;
use App\Domain\Rules\RuleEffect;
use App\Domain\Rules\RuleLabelFormatter;

final readonly class TaxRule implements CommercialRule
{
    private string $name;

    private string $percent;

    /** @param array<string, mixed> $configuration */
    public function __construct(array $configuration)
    {
        $this->name = RuleConfiguration::string($configuration, 'name');
        $this->percent = RuleConfiguration::percent($configuration, 'percent');
    }

    public function apply(CalculationState $state): RuleEffect
    {
        $tax = $state->total->percentage($this->percent);

        return new RuleEffect(
            $state->total,
            $tax,
            Money::zero($state->roundingMode),
            $this->name.' '.RuleLabelFormatter::number($this->percent).'%',
        );
    }
}
