<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class QuoteValidationRules
{
    /** @return array<string, list<string>> */
    public static function make(string $prefix = ''): array
    {
        $p = $prefix === '' ? '' : $prefix.'.';

        return [
            $p.'budget' => ['required', 'array'],
            $p.'budget.id' => ['required', 'string', 'max:64'],
            $p.'budget.client' => ['required', 'string', 'max:255'],
            $p.'budget.event' => ['required', 'string', 'max:5000'],
            $p.'budget.items' => ['required', 'array', 'min:1'],
            $p.'budget.items.*.id' => ['required', 'integer', 'min:1'],
            $p.'budget.items.*.description' => ['required', 'string', 'max:255'],
            $p.'budget.items.*.category' => ['required', 'string', 'max:64'],
            $p.'budget.items.*.quantity' => ['required', 'integer', 'min:1'],
            $p.'budget.items.*.unit_price' => ['required', 'numeric', 'min:0'],
            $p.'budget.items.*.currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            $p.'exchange_rates' => ['present', 'array'],
            $p.'exchange_rates.*' => ['required', 'numeric', 'gt:0'],
            $p.'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            $p.'rules' => ['present', 'array'],
            $p.'rules.*' => ['required', 'array'],
            $p.'rules.*.type' => ['required', 'string'],
            $p.'rules.*.category' => ['sometimes', 'string', 'max:64'],
            $p.'rules.*.buy' => ['sometimes', 'integer', 'min:1'],
            $p.'rules.*.pay' => ['sometimes', 'integer', 'min:0'],
            $p.'rules.*.percent' => ['sometimes', 'numeric', 'between:0,100'],
            $p.'rules.*.amount' => ['sometimes', 'numeric', 'min:0'],
            $p.'rules.*.currency' => ['sometimes', 'regex:/^[A-Z]{3}$/'],
            $p.'rules.*.max_percent' => ['sometimes', 'numeric', 'between:0,100'],
            $p.'rules.*.name' => ['sometimes', 'string', 'max:64'],
        ];
    }
}
