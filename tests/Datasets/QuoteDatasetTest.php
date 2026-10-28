<?php

declare(strict_types=1);

use Tests\Support\QuoteDatasetValidator;

function datasetsDirectory(): string
{
    return dirname(__DIR__).'/Fixtures/datasets';
}

function loadDataset(string $filename): stdClass
{
    static $cache = [];
    if (! isset($cache[$filename])) {
        $decoded = json_decode(
            (string) file_get_contents(datasetsDirectory().'/'.$filename),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );
        if (! $decoded instanceof stdClass) {
            throw new RuntimeException($filename.' is not a JSON object.');
        }
        $cache[$filename] = $decoded;
    }

    return $cache[$filename];
}

/**
 * @param list<array{path: string, code: string, detail: string}|stdClass> $errors
 * @return list<string>
 */
function errorSignature(array $errors): array
{
    $signature = [];
    foreach ($errors as $error) {
        if ($error instanceof stdClass) {
            $path = $error->path;
            $code = $error->code;
            $detail = $error->detail ?? '';
        } else {
            $path = $error['path'];
            $code = $error['code'];
            $detail = $error['detail'] ?? '';
        }
        $signature[] = $path.'|'.$code.'|'.$detail;
    }
    sort($signature);

    return $signature;
}

it('keeps the official dataset byte-for-byte', function () {
    $path = datasetsDirectory().'/official-v2.json';

    expect(hash_file('sha256', $path))->toBe('fb8f930b866cfe4731b80edb9b38fc84e0a02de138c8dcd958c97989ac7ccf82')
        ->and(filesize($path))->toBe(10053823);
});

it('counts the official exchange pairs', function () {
    $document = loadDataset('official-v2.json');
    $counts = [];
    foreach ($document->quotes as $quote) {
        foreach (array_keys(get_object_vars($quote->exchange_rates)) as $pair) {
            $counts[$pair] = ($counts[$pair] ?? 0) + 1;
        }
    }

    expect($document->version)->toBe('v2')
        ->and($document->generated_at)->toBe('2026-10-26')
        ->and($document->count)->toBe(10000)
        ->and($document->quotes)->toHaveCount(10000)
        ->and($counts)->toEqual([
            'USD_ARS' => 9825,
            'EUR_ARS' => 2084,
            'ARS_USD' => 175,
        ]);
});

it('accepts every official quote', function () {
    $validator = new QuoteDatasetValidator();
    $failures = [];
    $emptyRules = 0;
    $inverseQuotes = 0;

    foreach (loadDataset('official-v2.json')->quotes as $index => $quote) {
        $errors = $validator->validateQuote($quote);
        if ($errors !== []) {
            $failures[] = [
                'index' => $index,
                'id' => $quote->budget->id ?? null,
                'errors' => array_slice($errors, 0, 6),
            ];
            if (count($failures) >= 5) {
                break;
            }
        }

        if ($quote->rules === []) {
            $emptyRules++;
        }

        $have = array_fill_keys(array_keys(get_object_vars($quote->exchange_rates)), true);
        $needed = [];
        foreach ($quote->budget->items as $item) {
            if ($item->currency !== $quote->currency) {
                $needed[$item->currency.'_'.$quote->currency] = true;
            }
        }
        foreach ($quote->rules as $rule) {
            if ($rule->type === 'fixed_discount' && $rule->currency !== $quote->currency) {
                $needed[$rule->currency.'_'.$quote->currency] = true;
            }
        }
        foreach (array_keys($needed) as $direct) {
            [$base, $output] = explode('_', $direct);
            $inverse = $output.'_'.$base;
            if (! isset($have[$direct]) && isset($have[$inverse])) {
                $inverseQuotes++;
                break;
            }
        }
    }

    expect($failures)->toBe([])
        ->and($emptyRules)->toBe(1401)
        ->and($inverseQuotes)->toBe(1579);
});

it('accepts the generated valid dataset and covers the required variety', function () {
    $validator = new QuoteDatasetValidator();
    $document = loadDataset('valid-v2.json');
    $failures = [];
    $pairs = [];
    $inverseOnly = [];
    $ruleTypes = [];
    $outputs = [];
    $itemCurrencies = [];
    $flags = array_fill_keys([
        'empty rules',
        'empty rates',
        'zero price',
        'percent 0',
        'percent 100',
        'cap 0',
        'cap 100',
        'pay 0',
        'absent category',
        'remainder',
        'long event',
        'long word',
        'duplicate budget id',
        'fixed discount in another currency',
        'all six rule types on one quote',
        'zero fixed amount',
    ], false);
    $budgetIds = [];

    expect($document->version)->toBe('v2')
        ->and($document->generated_at)->toBe('2026-10-28')
        ->and($document->count)->toBe(count($document->quotes))
        ->and($document->count)->toBeGreaterThan(40);

    foreach ($document->quotes as $index => $quote) {
        $errors = $validator->validateQuote($quote);
        if ($errors !== []) {
            $failures[] = ['index' => $index, 'id' => $quote->budget->id, 'errors' => $errors];
        }

        $outputs[$quote->currency] = true;
        $budgetIds[$quote->budget->id] = ($budgetIds[$quote->budget->id] ?? 0) + 1;
        if ($quote->rules === []) {
            $flags['empty rules'] = true;
        }
        if (get_object_vars($quote->exchange_rates) === []) {
            $flags['empty rates'] = true;
        }
        if (mb_strlen($quote->budget->event) > 112) {
            $flags['long event'] = true;
        }

        $categories = [];
        foreach ($quote->budget->items as $item) {
            $itemCurrencies[$item->currency] = true;
            $categories[$item->category] = true;
            if ($item->unit_price == 0) {
                $flags['zero price'] = true;
            }
            foreach (preg_split('/\s+/u', trim($item->description)) ?: [] as $word) {
                if (mb_strlen($word) > 28) {
                    $flags['long word'] = true;
                }
            }
        }

        $typesOnQuote = [];
        foreach (array_keys(get_object_vars($quote->exchange_rates)) as $pair) {
            $pairs[$pair] = true;
        }
        foreach ($quote->rules as $rule) {
            $ruleTypes[$rule->type] = true;
            $typesOnQuote[$rule->type] = true;
            if (in_array($rule->type, ['percentage_discount', 'category_discount', 'tax'], true)) {
                if ($rule->percent == 0) {
                    $flags['percent 0'] = true;
                }
                if ($rule->percent == 100) {
                    $flags['percent 100'] = true;
                }
            }
            if ($rule->type === 'discount_cap' && $rule->max_percent == 0) {
                $flags['cap 0'] = true;
            }
            if ($rule->type === 'discount_cap' && $rule->max_percent == 100) {
                $flags['cap 100'] = true;
            }
            if ($rule->type === 'quantity_promo' && $rule->pay === 0) {
                $flags['pay 0'] = true;
            }
            if ($rule->type === 'quantity_promo' && $rule->buy > 0) {
                foreach ($quote->budget->items as $item) {
                    if ($item->category === $rule->category && $item->quantity % $rule->buy !== 0) {
                        $flags['remainder'] = true;
                    }
                }
            }
            if (in_array($rule->type, ['category_discount', 'quantity_promo'], true) && ! isset($categories[$rule->category])) {
                $flags['absent category'] = true;
            }
            if ($rule->type === 'fixed_discount' && $rule->amount == 0) {
                $flags['zero fixed amount'] = true;
            }
            if ($rule->type === 'fixed_discount' && $rule->currency !== $quote->currency && $rule->amount > 0) {
                $flags['fixed discount in another currency'] = true;
            }
        }
        if (count($typesOnQuote) === 6) {
            $flags['all six rule types on one quote'] = true;
        }

        $needed = [];
        foreach ($quote->budget->items as $item) {
            if ($item->currency !== $quote->currency) {
                $needed[$item->currency.'_'.$quote->currency] = true;
            }
        }
        foreach ($quote->rules as $rule) {
            if ($rule->type === 'fixed_discount' && $rule->currency !== $quote->currency) {
                $needed[$rule->currency.'_'.$quote->currency] = true;
            }
        }
        $have = array_fill_keys(array_keys(get_object_vars($quote->exchange_rates)), true);
        foreach (array_keys($needed) as $direct) {
            [$base, $output] = explode('_', $direct);
            $inverse = $output.'_'.$base;
            if (! isset($have[$direct]) && isset($have[$inverse])) {
                $inverseOnly[$direct] = true;
            }
        }
    }

    foreach ($budgetIds as $count) {
        if ($count >= 2) {
            $flags['duplicate budget id'] = true;
        }
    }

    $missing = array_keys(array_filter($flags, static fn (bool $present): bool => ! $present));
    foreach (['USD_ARS', 'EUR_ARS', 'ARS_USD', 'ARS_EUR', 'USD_EUR', 'EUR_USD'] as $pair) {
        if (! isset($pairs[$pair])) {
            $missing[] = 'pair '.$pair;
        }
    }
    foreach (['ARS', 'USD', 'EUR'] as $from) {
        foreach (['ARS', 'USD', 'EUR'] as $to) {
            if ($from !== $to && ! isset($inverseOnly[$from.'_'.$to])) {
                $missing[] = 'inverse '.$from.'_'.$to;
            }
        }
    }
    foreach (['quantity_promo', 'category_discount', 'percentage_discount', 'fixed_discount', 'discount_cap', 'tax'] as $type) {
        if (! isset($ruleTypes[$type])) {
            $missing[] = 'rule '.$type;
        }
    }
    foreach (['ARS', 'USD', 'EUR'] as $currency) {
        if (! isset($outputs[$currency])) {
            $missing[] = 'output '.$currency;
        }
        if (! isset($itemCurrencies[$currency])) {
            $missing[] = 'item currency '.$currency;
        }
    }

    expect($failures)->toBe([])
        ->and($missing)->toBe([]);
});

it('fails each invalid case with the labeled error', function () {
    $validator = new QuoteDatasetValidator();
    $document = loadDataset('invalid-v2.json');
    $mismatches = [];
    $codes = [];
    $ids = [];

    expect($document->version)->toBe('v2-invalid')
        ->and($document->count)->toBe(count($document->cases))
        ->and($document->count)->toBeGreaterThan(60);

    foreach ($document->cases as $case) {
        if (isset($ids[$case->id])) {
            $mismatches[] = ['id' => $case->id, 'problem' => 'duplicate id'];
        }
        $ids[$case->id] = true;

        $actual = errorSignature($validator->validateQuote($case->quote));
        $expected = errorSignature($case->expected_errors);
        foreach ($case->expected_errors as $error) {
            $codes[$error->code] = true;
        }
        if ($actual === [] || $actual !== $expected) {
            $mismatches[] = [
                'id' => $case->id,
                'actual' => $actual,
                'expected' => $expected,
            ];
        }
    }

    $requiredCodes = [
        'missing_rate',
        'rate_not_positive',
        'malformed_pair_key',
        'unsupported_currency',
        'unknown_rule_type',
        'required',
        'blank',
        'empty_items',
        'invalid_type',
        'quantity_not_numeric',
        'quantity_not_integer',
        'quantity_not_positive',
        'price_not_numeric',
        'price_negative',
        'price_scale',
        'percent_not_numeric',
        'percent_out_of_range',
        'percent_scale',
        'amount_not_numeric',
        'amount_negative',
        'amount_scale',
        'buy_pay_invalid',
        'invalid_pair',
        'invalid_item_id',
    ];
    $missingCodes = array_values(array_filter(
        $requiredCodes,
        static fn (string $code): bool => ! isset($codes[$code]),
    ));

    expect($mismatches)->toBe([])
        ->and($missingCodes)->toBe([]);
});

it('regenerates both datasets byte for byte from the fixed seed', function () {
    $script = datasetsDirectory().'/generate_datasets.php';
    $committedValid = (string) file_get_contents(datasetsDirectory().'/valid-v2.json');
    $committedInvalid = (string) file_get_contents(datasetsDirectory().'/invalid-v2.json');
    $previous = null;

    foreach (['a', 'b'] as $suffix) {
        $dir = sys_get_temp_dir().'/mottor-datasets-'.$suffix.'-'.getmypid();
        if (! is_dir($dir) && ! mkdir($dir) && ! is_dir($dir)) {
            throw new RuntimeException('Cannot create '.$dir);
        }

        $command = [PHP_BINARY, $script, $dir];
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        expect($exitCode)->toBe(0, $stderr.$stdout);

        $valid = (string) file_get_contents($dir.'/valid-v2.json');
        $invalid = (string) file_get_contents($dir.'/invalid-v2.json');
        if ($previous !== null) {
            expect($valid)->toBe($previous['valid'])
                ->and($invalid)->toBe($previous['invalid']);
        }
        $previous = ['valid' => $valid, 'invalid' => $invalid];

        expect($valid)->toBe($committedValid)
            ->and($invalid)->toBe($committedInvalid);
    }
});

it('delegates dataset checks to the production validators once they exist', function () {
    throw new RuntimeException(
        'RateSet and QuoteValidationRules are loadable. Point this test at them before treating the stand-in as optional.',
    );
})->skip(
    fn (): bool => ! class_exists(App\Domain\Money\RateSet::class)
        || ! class_exists(App\Http\Requests\QuoteValidationRules::class),
    'Finding: App\\Domain\\Money\\RateSet and App\\Http\\Requests\\QuoteValidationRules are not in the repository.',
);
