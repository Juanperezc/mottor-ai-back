<?php

declare(strict_types=1);

namespace App\Domain\Rules;

use App\Domain\Exception\DomainValidationException;

final readonly class RuleEngine
{
    public function __construct(private RuleRegistry $registry) {}

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return array{state: CalculationState, steps: list<BreakdownStep>}
     */
    public function calculate(CalculationState $state, array $rules): array
    {
        $steps = [];

        foreach ($rules as $index => $configuration) {
            $effect = $this->registry->make($configuration, $state, $index)->apply($state);
            $state = $state->withEffect($effect);
            $steps[] = new BreakdownStep(
                $index + 1,
                (string) $configuration['type'],
                $effect->label,
                $effect->base,
                $effect->amount,
                $state->total,
            );
        }

        if ($state->total->isNegative()) {
            throw new DomainValidationException([
                'rules' => ['The rules produce a negative total.'],
            ]);
        }

        return ['state' => $state, 'steps' => $steps];
    }
}
