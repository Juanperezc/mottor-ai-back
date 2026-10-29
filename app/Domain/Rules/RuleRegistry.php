<?php

declare(strict_types=1);

namespace App\Domain\Rules;

use App\Domain\Exception\DomainValidationException;
use Closure;

final readonly class RuleRegistry
{
    /** @param array<string, Closure(array<string, mixed>, CalculationState): CommercialRule> $factories */
    public function __construct(private array $factories) {}

    /** @param array<string, mixed> $configuration */
    public function make(array $configuration, CalculationState $state, int $index): CommercialRule
    {
        $type = (string) ($configuration['type'] ?? '');
        $factory = $this->factories[$type] ?? null;

        if ($factory === null) {
            throw new DomainValidationException([
                "rules.{$index}.type" => ["The rule type '{$type}' is not supported."],
            ]);
        }

        try {
            return $factory($configuration, $state);
        } catch (DomainValidationException $exception) {
            $errors = [];

            foreach ($exception->errors as $path => $messages) {
                $errors[str_starts_with($path, 'rules.')
                    ? "rules.{$index}.".substr($path, 6)
                    : $path] = $messages;
            }

            throw new DomainValidationException($errors, $exception->getMessage());
        }
    }
}
