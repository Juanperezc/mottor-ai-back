<?php

declare(strict_types=1);

namespace App\Domain\Rules\Strategies;

use App\Domain\Money\Money;
use App\Domain\Rules\CalculationState;
use App\Domain\Rules\CommercialRule;
use App\Domain\Rules\RuleConfiguration;
use App\Domain\Rules\RuleEffect;
use App\Domain\Rules\RuleLabelFormatter;

final readonly class DiscountCapRule implements CommercialRule
{
    private string $maxPercent;

    /** @param array<string, mixed> $configuration */
    public function __construct(array $configuration)
    {
        $this->maxPercent = RuleConfiguration::percent($configuration, 'max_percent');
    }

    public function apply(CalculationState $state): RuleEffect
    {
        $limit = $state->listSubtotal->percentage($this->maxPercent);
        $refund = $state->discounts->isGreaterThan($limit)
            ? $state->discounts->minus($limit)
            : Money::zero($state->roundingMode);

        return new RuleEffect(
            $state->listSubtotal,
            $refund,
            $refund->negated(),
            'Tope '.RuleLabelFormatter::number($this->maxPercent).'%',
        );
    }
}
