<?php

declare(strict_types=1);

namespace Tests\Support;

use stdClass;

/**
 * Contract checker for a v2 quote payload.
 *
 * App\Domain\Money\RateSet and App\Http\Requests\QuoteValidationRules are not
 * in this repository. Until they exist, dataset tests check this stand-in.
 * The rules follow the official challenge statement (dataset v2): ARS/USD/EUR,
 * direct or inverse pairs, no triangulation, JSON numbers for money, and the
 * six rule types. Assumptions that the statement leaves open are documented
 * in tests/Fixtures/datasets/README.md.
 *
 * @phpstan-type QuoteError array{path: string, code: string, detail: string}
 */
final class QuoteDatasetValidator
{
    public const CODE_INVALID_TYPE = 'invalid_type';

    public const CODE_REQUIRED = 'required';

    public const CODE_BLANK = 'blank';

    public const CODE_EMPTY_ITEMS = 'empty_items';

    public const CODE_UNSUPPORTED_CURRENCY = 'unsupported_currency';

    public const CODE_MALFORMED_PAIR_KEY = 'malformed_pair_key';

    public const CODE_INVALID_PAIR = 'invalid_pair';

    public const CODE_RATE_NOT_POSITIVE = 'rate_not_positive';

    public const CODE_MISSING_RATE = 'missing_rate';

    public const CODE_UNKNOWN_RULE_TYPE = 'unknown_rule_type';

    public const CODE_QUANTITY_NOT_NUMERIC = 'quantity_not_numeric';

    public const CODE_QUANTITY_NOT_INTEGER = 'quantity_not_integer';

    public const CODE_QUANTITY_NOT_POSITIVE = 'quantity_not_positive';

    public const CODE_PRICE_NOT_NUMERIC = 'price_not_numeric';

    public const CODE_PRICE_NEGATIVE = 'price_negative';

    public const CODE_PRICE_SCALE = 'price_scale';

    public const CODE_AMOUNT_NOT_NUMERIC = 'amount_not_numeric';

    public const CODE_AMOUNT_NEGATIVE = 'amount_negative';

    public const CODE_AMOUNT_SCALE = 'amount_scale';

    public const CODE_PERCENT_NOT_NUMERIC = 'percent_not_numeric';

    public const CODE_PERCENT_OUT_OF_RANGE = 'percent_out_of_range';

    public const CODE_PERCENT_SCALE = 'percent_scale';

    public const CODE_BUY_PAY_INVALID = 'buy_pay_invalid';

    public const CODE_INVALID_ITEM_ID = 'invalid_item_id';

    /** @var array<string, true> */
    private const CURRENCIES = ['ARS' => true, 'USD' => true, 'EUR' => true];

    /** @var array<string, true> */
    private const RULE_TYPES = [
        'quantity_promo' => true,
        'category_discount' => true,
        'percentage_discount' => true,
        'fixed_discount' => true,
        'discount_cap' => true,
        'tax' => true,
    ];

    /**
     * @return list<QuoteError>
     */
    public function validateQuote(mixed $quote): array
    {
        if (! $quote instanceof stdClass) {
            return [$this->error('quote', self::CODE_INVALID_TYPE)];
        }

        $errors = [];
        $ratesAreObject = false;
        $usableRates = [];

        if (! property_exists($quote, 'budget')) {
            $errors[] = $this->error('budget', self::CODE_REQUIRED);
        } elseif (! $quote->budget instanceof stdClass) {
            $errors[] = $this->error('budget', self::CODE_INVALID_TYPE);
        } else {
            $this->validateBudget($quote->budget, $errors);
        }

        if (! property_exists($quote, 'exchange_rates')) {
            $errors[] = $this->error('exchange_rates', self::CODE_REQUIRED);
        } elseif (! $quote->exchange_rates instanceof stdClass) {
            $errors[] = $this->error('exchange_rates', self::CODE_INVALID_TYPE);
        } else {
            $ratesAreObject = true;
            $usableRates = $this->validateRates($quote->exchange_rates, $errors);
        }

        $outputCurrency = null;
        if (! property_exists($quote, 'currency')) {
            $errors[] = $this->error('currency', self::CODE_REQUIRED);
        } else {
            $outputCurrency = $this->validateCurrency($quote->currency, 'currency', $errors);
        }

        if (! property_exists($quote, 'rules')) {
            $errors[] = $this->error('rules', self::CODE_REQUIRED);
        } elseif (! is_array($quote->rules)) {
            $errors[] = $this->error('rules', self::CODE_INVALID_TYPE);
        } else {
            $this->validateRules($quote->rules, $errors);
        }

        if ($ratesAreObject && is_string($outputCurrency)) {
            $this->validateRequiredRates($quote, $outputCurrency, $usableRates, $errors);
        }

        return $errors;
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function validateBudget(stdClass $budget, array &$errors): void
    {
        $this->requireText($budget, 'id', 'budget.id', $errors);
        $this->requireText($budget, 'client', 'budget.client', $errors);
        $this->requireText($budget, 'event', 'budget.event', $errors);

        if (! property_exists($budget, 'items')) {
            $errors[] = $this->error('budget.items', self::CODE_REQUIRED);

            return;
        }

        if (! is_array($budget->items)) {
            $errors[] = $this->error('budget.items', self::CODE_INVALID_TYPE);

            return;
        }

        if ($budget->items === []) {
            $errors[] = $this->error('budget.items', self::CODE_EMPTY_ITEMS);

            return;
        }

        foreach ($budget->items as $index => $item) {
            $path = 'budget.items.'.$index;
            if (! $item instanceof stdClass) {
                $errors[] = $this->error($path, self::CODE_INVALID_TYPE);

                continue;
            }
            $this->validateItem($item, $path, $errors);
        }
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function validateItem(stdClass $item, string $path, array &$errors): void
    {
        $this->validateItemId($item, $path.'.id', $errors);
        $this->requireText($item, 'description', $path.'.description', $errors);
        $this->requireText($item, 'category', $path.'.category', $errors);
        $this->validateQuantity($item, $path.'.quantity', $errors);
        $this->validatePrice($item, $path.'.unit_price', $errors);

        if (! property_exists($item, 'currency')) {
            $errors[] = $this->error($path.'.currency', self::CODE_REQUIRED);
        } else {
            $this->validateCurrency($item->currency, $path.'.currency', $errors);
        }
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function validateItemId(stdClass $item, string $path, array &$errors): void
    {
        if (! property_exists($item, 'id')) {
            $errors[] = $this->error($path, self::CODE_REQUIRED);

            return;
        }

        $value = $item->id;
        if (! is_int($value) && ! is_float($value)) {
            $errors[] = $this->error($path, self::CODE_INVALID_TYPE);

            return;
        }

        if (! $this->isWholeNumber($value) || $value < 1) {
            $errors[] = $this->error($path, self::CODE_INVALID_ITEM_ID);
        }
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function validateQuantity(stdClass $item, string $path, array &$errors): void
    {
        if (! property_exists($item, 'quantity')) {
            $errors[] = $this->error($path, self::CODE_REQUIRED);

            return;
        }

        $value = $item->quantity;
        if (! is_int($value) && ! is_float($value)) {
            $errors[] = $this->error($path, self::CODE_QUANTITY_NOT_NUMERIC);

            return;
        }

        if (! $this->isWholeNumber($value)) {
            $errors[] = $this->error($path, self::CODE_QUANTITY_NOT_INTEGER);

            return;
        }

        if ($value < 1) {
            $errors[] = $this->error($path, self::CODE_QUANTITY_NOT_POSITIVE);
        }
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function validatePrice(stdClass $item, string $path, array &$errors): void
    {
        if (! property_exists($item, 'unit_price')) {
            $errors[] = $this->error($path, self::CODE_REQUIRED);

            return;
        }

        $this->validateMoneyAmount(
            $item->unit_price,
            $path,
            $errors,
            self::CODE_PRICE_NOT_NUMERIC,
            self::CODE_PRICE_SCALE,
            self::CODE_PRICE_NEGATIVE,
        );
    }

    /**
     * @param list<QuoteError> $errors
     * @return array<string, true> pair keys whose value is a positive number
     */
    private function validateRates(stdClass $rates, array &$errors): array
    {
        $usable = [];

        foreach (get_object_vars($rates) as $key => $value) {
            $path = 'exchange_rates.'.$key;
            if (! is_string($key) || preg_match('/^[A-Z]{3}_[A-Z]{3}$/', $key) !== 1) {
                $errors[] = $this->error($path, self::CODE_MALFORMED_PAIR_KEY);

                continue;
            }

            [$base, $quote] = explode('_', $key, 2);
            if ($base === $quote) {
                $errors[] = $this->error($path, self::CODE_INVALID_PAIR, $key);

                continue;
            }

            $unsupported = [];
            if (! isset(self::CURRENCIES[$base])) {
                $unsupported[] = $base;
            }
            if (! isset(self::CURRENCIES[$quote])) {
                $unsupported[] = $quote;
            }
            if ($unsupported !== []) {
                $errors[] = $this->error($path, self::CODE_UNSUPPORTED_CURRENCY, implode(',', $unsupported));

                continue;
            }

            if (! is_int($value) && ! is_float($value)) {
                $errors[] = $this->error($path, self::CODE_INVALID_TYPE);

                continue;
            }

            if (! is_finite($value) || $value <= 0) {
                $errors[] = $this->error($path, self::CODE_RATE_NOT_POSITIVE);

                continue;
            }

            $usable[$key] = true;
        }

        return $usable;
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function validateRules(array $rules, array &$errors): void
    {
        foreach ($rules as $index => $rule) {
            $path = 'rules.'.$index;
            if (! $rule instanceof stdClass) {
                $errors[] = $this->error($path, self::CODE_INVALID_TYPE);

                continue;
            }

            if (! property_exists($rule, 'type')) {
                $errors[] = $this->error($path.'.type', self::CODE_REQUIRED);

                continue;
            }

            if (! is_string($rule->type)) {
                $errors[] = $this->error($path.'.type', self::CODE_INVALID_TYPE);

                continue;
            }

            if (! isset(self::RULE_TYPES[$rule->type])) {
                $errors[] = $this->error($path.'.type', self::CODE_UNKNOWN_RULE_TYPE, $rule->type);

                continue;
            }

            match ($rule->type) {
                'quantity_promo' => $this->validateQuantityPromo($rule, $path, $errors),
                'category_discount' => $this->validateCategoryDiscount($rule, $path, $errors),
                'percentage_discount' => $this->requirePercent($rule, 'percent', $path.'.percent', $errors),
                'fixed_discount' => $this->validateFixedDiscount($rule, $path, $errors),
                'discount_cap' => $this->requirePercent($rule, 'max_percent', $path.'.max_percent', $errors),
                'tax' => $this->validateTax($rule, $path, $errors),
            };
        }
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function validateQuantityPromo(stdClass $rule, string $path, array &$errors): void
    {
        $this->requireText($rule, 'category', $path.'.category', $errors);
        $buy = $this->wholeRuleNumber($rule, 'buy', $path.'.buy', $errors);
        $pay = $this->wholeRuleNumber($rule, 'pay', $path.'.pay', $errors);
        if ($buy === null || $pay === null) {
            return;
        }

        if ($buy < 1 || $pay < 0 || $pay >= $buy) {
            $errors[] = $this->error($path, self::CODE_BUY_PAY_INVALID, 'out_of_range');
        }
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function validateCategoryDiscount(stdClass $rule, string $path, array &$errors): void
    {
        $this->requireText($rule, 'category', $path.'.category', $errors);
        $this->requirePercent($rule, 'percent', $path.'.percent', $errors);
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function validateFixedDiscount(stdClass $rule, string $path, array &$errors): void
    {
        if (! property_exists($rule, 'amount')) {
            $errors[] = $this->error($path.'.amount', self::CODE_REQUIRED);
        } else {
            $this->validateMoneyAmount(
                $rule->amount,
                $path.'.amount',
                $errors,
                self::CODE_AMOUNT_NOT_NUMERIC,
                self::CODE_AMOUNT_SCALE,
                self::CODE_AMOUNT_NEGATIVE,
            );
        }

        if (! property_exists($rule, 'currency')) {
            $errors[] = $this->error($path.'.currency', self::CODE_REQUIRED);
        } else {
            $this->validateCurrency($rule->currency, $path.'.currency', $errors);
        }
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function validateTax(stdClass $rule, string $path, array &$errors): void
    {
        $this->requireText($rule, 'name', $path.'.name', $errors);
        $this->requirePercent($rule, 'percent', $path.'.percent', $errors);
    }

    /**
     * @param array<string, true> $usableRates
     * @param list<QuoteError> $errors
     */
    private function validateRequiredRates(stdClass $quote, string $output, array $usableRates, array &$errors): void
    {
        $needed = [];

        if (property_exists($quote, 'budget') && $quote->budget instanceof stdClass && is_array($quote->budget->items ?? null)) {
            foreach ($quote->budget->items as $item) {
                if (! $item instanceof stdClass || ! property_exists($item, 'currency') || ! is_string($item->currency)) {
                    continue;
                }
                if (! isset(self::CURRENCIES[$item->currency]) || $item->currency === $output) {
                    continue;
                }
                $needed[$item->currency.'_'.$output] = true;
            }
        }

        if (property_exists($quote, 'rules') && is_array($quote->rules)) {
            foreach ($quote->rules as $rule) {
                if (! $rule instanceof stdClass || ($rule->type ?? null) !== 'fixed_discount') {
                    continue;
                }
                if (! property_exists($rule, 'currency') || ! is_string($rule->currency)) {
                    continue;
                }
                if (! isset(self::CURRENCIES[$rule->currency]) || $rule->currency === $output) {
                    continue;
                }
                $needed[$rule->currency.'_'.$output] = true;
            }
        }

        foreach (array_keys($needed) as $direct) {
            $inverse = $this->inversePair($direct);
            if (! isset($usableRates[$direct]) && ! isset($usableRates[$inverse])) {
                $errors[] = $this->error('exchange_rates', self::CODE_MISSING_RATE, $direct);
            }
        }
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function requireText(object $source, string $field, string $path, array &$errors): void
    {
        if (! property_exists($source, $field)) {
            $errors[] = $this->error($path, self::CODE_REQUIRED);

            return;
        }

        $value = $source->{$field};
        if (! is_string($value)) {
            $errors[] = $this->error($path, self::CODE_INVALID_TYPE);

            return;
        }

        if (trim($value) === '') {
            $errors[] = $this->error($path, self::CODE_BLANK);
        }
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function validateCurrency(mixed $value, string $path, array &$errors): ?string
    {
        if (! is_string($value)) {
            $errors[] = $this->error($path, self::CODE_INVALID_TYPE);

            return null;
        }

        if (trim($value) === '') {
            $errors[] = $this->error($path, self::CODE_BLANK);

            return null;
        }

        if (! isset(self::CURRENCIES[$value])) {
            $errors[] = $this->error($path, self::CODE_UNSUPPORTED_CURRENCY, $value);

            return null;
        }

        return $value;
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function requirePercent(object $rule, string $field, string $path, array &$errors): void
    {
        if (! property_exists($rule, $field)) {
            $errors[] = $this->error($path, self::CODE_REQUIRED);

            return;
        }

        $value = $rule->{$field};
        if (! is_int($value) && ! is_float($value)) {
            $errors[] = $this->error($path, self::CODE_PERCENT_NOT_NUMERIC);

            return;
        }

        if ($this->exceedsScale($value, 2)) {
            $errors[] = $this->error($path, self::CODE_PERCENT_SCALE);

            return;
        }

        if ($value < 0 || $value > 100) {
            $errors[] = $this->error($path, self::CODE_PERCENT_OUT_OF_RANGE);
        }
    }

    /**
     * @param list<QuoteError> $errors
     * @return int|null whole number, or null when the field is already reported
     */
    private function wholeRuleNumber(object $rule, string $field, string $path, array &$errors): ?int
    {
        if (! property_exists($rule, $field)) {
            $errors[] = $this->error($path, self::CODE_REQUIRED);

            return null;
        }

        $value = $rule->{$field};
        if (! is_int($value) && ! is_float($value)) {
            $errors[] = $this->error($path, self::CODE_INVALID_TYPE);

            return null;
        }

        if (! $this->isWholeNumber($value)) {
            $errors[] = $this->error($path, self::CODE_BUY_PAY_INVALID, 'not_integer');

            return null;
        }

        return (int) $value;
    }

    /**
     * @param list<QuoteError> $errors
     */
    private function validateMoneyAmount(
        mixed $value,
        string $path,
        array &$errors,
        string $notNumeric,
        string $scale,
        string $negative,
    ): void {
        if (! is_int($value) && ! is_float($value)) {
            $errors[] = $this->error($path, $notNumeric);

            return;
        }

        if ($this->exceedsScale($value, 2)) {
            $errors[] = $this->error($path, $scale);

            return;
        }

        if ($value < 0) {
            $errors[] = $this->error($path, $negative);
        }
    }

    private function isWholeNumber(int|float $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        return is_finite($value) && abs($value) < 1e15 && floor($value) === $value;
    }

    private function exceedsScale(int|float $value, int $scale): bool
    {
        if (is_int($value)) {
            return false;
        }

        if (! is_finite($value)) {
            return true;
        }

        return abs($value - round($value, $scale)) > 1e-8;
    }

    private function inversePair(string $direct): string
    {
        [$base, $quote] = explode('_', $direct, 2);

        return $quote.'_'.$base;
    }

    /**
     * @return QuoteError
     */
    private function error(string $path, string $code, string $detail = ''): array
    {
        return ['path' => $path, 'code' => $code, 'detail' => $detail];
    }
}
