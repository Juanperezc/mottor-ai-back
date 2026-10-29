<?php

declare(strict_types=1);

namespace App\Application\Quotes;

use App\Domain\Exception\DomainValidationException;

final class DecimalInputNormalizer
{
    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function normalize(array $input): array
    {
        /** @var array<string, mixed> $budget */
        $budget = $input['budget'];
        /** @var list<array<string, mixed>> $items */
        $items = $budget['items'];

        foreach ($items as $index => $item) {
            $items[$index]['id'] = $this->integer($item['id'] ?? null, "budget.items.{$index}.id");
            $items[$index]['quantity'] = $this->integer($item['quantity'] ?? null, "budget.items.{$index}.quantity");
            $items[$index]['unit_price'] = $this->decimal($item['unit_price'] ?? null, "budget.items.{$index}.unit_price");
        }
        $budget['items'] = $items;
        $input['budget'] = $budget;

        /** @var array<string, mixed> $rates */
        $rates = $input['exchange_rates'] ?? [];
        foreach ($rates as $pair => $rate) {
            $rates[$pair] = $this->decimal($rate, "exchange_rates.{$pair}");
        }
        $input['exchange_rates'] = $rates;

        /** @var list<array<string, mixed>> $rules */
        $rules = $input['rules'];
        foreach ($rules as $index => $rule) {
            foreach (['buy', 'pay'] as $field) {
                if (array_key_exists($field, $rule)) {
                    $rules[$index][$field] = $this->integer($rule[$field], "rules.{$index}.{$field}");
                }
            }

            foreach (['percent', 'amount', 'max_percent'] as $field) {
                if (array_key_exists($field, $rule)) {
                    $rules[$index][$field] = $this->decimal($rule[$field], "rules.{$index}.{$field}");
                }
            }
        }
        $input['rules'] = $rules;

        return $input;
    }

    private function integer(mixed $value, string $path): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || ! preg_match('/^-?\d+$/', $value)) {
            throw new DomainValidationException([$path => ['The value must be an integer.']]);
        }

        return (int) $value;
    }

    private function decimal(mixed $value, string $path): string
    {
        $decimal = match (true) {
            is_int($value) => (string) $value,
            is_string($value) => $value,
            default => '',
        };

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $decimal)) {
            throw new DomainValidationException([$path => ['The value must be a decimal number.']]);
        }

        return $decimal;
    }
}
