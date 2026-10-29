<?php

declare(strict_types=1);

namespace App\Domain\Rules\Strategies;

use App\Domain\Rules\CalculationState;
use App\Domain\Rules\CommercialRule;
use App\Domain\Rules\RuleConfiguration;
use App\Domain\Rules\RuleEffect;
use App\Domain\Rules\RuleLabelFormatter;

final readonly class CategoryDiscountRule implements CommercialRule
{
    private string $category;

    private string $percent;

    /** @param array<string, mixed> $configuration */
    public function __construct(array $configuration)
    {
        $this->category = RuleConfiguration::string($configuration, 'category');
        $this->percent = RuleConfiguration::percent($configuration, 'percent');
    }

    public function apply(CalculationState $state): RuleEffect
    {
        $base = $state->listValueForCategory($this->category);
        $discount = $base->percentage($this->percent);

        return new RuleEffect(
            $base,
            $discount->negated(),
            $discount,
            RuleLabelFormatter::number($this->percent)."% en {$this->category}",
        );
    }
}
