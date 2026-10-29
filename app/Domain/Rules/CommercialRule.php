<?php

declare(strict_types=1);

namespace App\Domain\Rules;

interface CommercialRule
{
    public function apply(CalculationState $state): RuleEffect;
}
