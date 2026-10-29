<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use stdClass;
use Tests\TestCase;

final class QuoteDatasetV2Test extends TestCase
{
    use RefreshDatabase;

    private const string OFFICIAL_DATASET = '/inputs/dataset.json';

    private const string OFFICIAL_SHA256 = 'fb8f930b866cfe4731b80edb9b38fc84e0a02de138c8dcd958c97989ac7ccf82';

    /**
     * Cases labeled invalid by the generator's stand-in checker that the spec
     * allows, so the API accepts them (201).
     *
     * @var array<string, string>
     */
    private const array SPEC_ACCEPTED_CASES = [
        'budget-id-not-string' => 'ADR-08 bounds values, not JSON token types',
        'item-id-not-integer' => 'ADR-08 bounds values, not JSON token types',
        'quantity-string' => 'ADR-08 bounds values, not JSON token types',
        'price-string' => 'ADR-08 bounds values, not JSON token types',
        'rate-string' => 'ADR-08 bounds values, not JSON token types',
        'percent-string' => 'ADR-08 bounds values, not JSON token types',
        'max-percent-string' => 'ADR-08 bounds values, not JSON token types',
        'buy-string' => 'ADR-08 bounds values, not JSON token types',
        'fixed-amount-string' => 'ADR-08 bounds values, not JSON token types',
        'price-three-decimals' => 'CAL-12 rounds each amount; inputs have no scale limit',
        'percent-three-decimals' => 'CAL-12 rounds each amount; inputs have no scale limit',
        'fixed-amount-three-decimals' => 'CAL-12 rounds each amount; inputs have no scale limit',
        'exchange-rates-not-object' => 'An empty list and an empty object are the same empty rate set',
        'invalid-pair-same-currency' => 'FX-01 only reads the pairs it needs; extra pairs are ignored',
        'unsupported-pair-currency' => 'ADR-08 accepts any ISO 3-letter code; extra pairs are ignored',
        'buy-pay-equal' => 'ADR-08 allows pay <= buy',
    ];

    /**
     * Cases the API rejects (422) under a different error key than the label:
     * Laravel reports the nested field, an unsupported currency surfaces as a
     * missing rate (FX-03), and domain errors come after shape errors.
     *
     * @var list<string>
     */
    private const array DIFFERENT_ERROR_KEY_CASES = [
        'budget-not-object',
        'item-not-object',
        'item-currency-unsupported',
        'quote-currency-unsupported',
        'rules-not-array',
        'malformed-key-does-not-satisfy-rate',
        'zero-rate-does-not-satisfy',
        'buy-zero',
        'pay-negative',
        'fixed-currency-unsupported',
        'multi-error-quantity-price-and-unknown-rule',
        'quote-not-object',
    ];

    private const array SIX_PAIRS = ['USD_ARS', 'EUR_ARS', 'ARS_USD', 'ARS_EUR', 'USD_EUR', 'EUR_USD'];

    private const array RULE_TYPES = [
        'quantity_promo', 'category_discount', 'percentage_discount', 'fixed_discount', 'discount_cap', 'tax',
    ];

    #[Test]
    /** BT-26, A35 */
    public function every_valid_v2_quote_is_imported_in_one_batch(): void
    {
        $raw = $this->read('valid-v2.json');

        $this->call('POST', '/quotes/batch', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $raw)->assertCreated();

        $this->assertDatabaseCount('quotes', $this->dataset('valid-v2.json')->count);
    }

    #[Test]
    /** BT-26 */
    public function the_valid_v2_dataset_covers_the_required_variety(): void
    {
        $document = $this->dataset('valid-v2.json');
        self::assertSame('v2', $document->version);
        self::assertSame('2026-10-28', $document->generated_at);
        self::assertSame(count($document->quotes), $document->count);
        self::assertGreaterThan(40, $document->count);

        $flags = array_fill_keys([
            'empty rules', 'empty rates', 'zero price', 'percent 0', 'percent 100', 'cap 0', 'cap 100', 'pay 0',
            'absent category', 'remainder', 'long event', 'long word', 'duplicate budget id',
            'fixed discount in another currency', 'all six rule types on one quote', 'zero fixed amount',
        ], false);
        $pairs = $inverseOnly = $ruleTypes = $outputs = $itemCurrencies = $budgetIds = [];

        foreach ($document->quotes as $quote) {
            $outputs[$quote->currency] = true;
            $budgetIds[$quote->budget->id] = ($budgetIds[$quote->budget->id] ?? 0) + 1;
            $flags['empty rules'] = $flags['empty rules'] || $quote->rules === [];
            $flags['empty rates'] = $flags['empty rates'] || get_object_vars($quote->exchange_rates) === [];
            $flags['long event'] = $flags['long event'] || mb_strlen($quote->budget->event) > 112;

            $categories = [];
            foreach ($quote->budget->items as $item) {
                $itemCurrencies[$item->currency] = true;
                $categories[$item->category] = true;
                $flags['zero price'] = $flags['zero price'] || $item->unit_price == 0;
                foreach (preg_split('/\s+/u', trim($item->description)) ?: [] as $word) {
                    $flags['long word'] = $flags['long word'] || mb_strlen($word) > 28;
                }
            }

            foreach (array_keys(get_object_vars($quote->exchange_rates)) as $pair) {
                $pairs[$pair] = true;
            }

            $typesOnQuote = [];
            foreach ($quote->rules as $rule) {
                $ruleTypes[$rule->type] = true;
                $typesOnQuote[$rule->type] = true;
                if (in_array($rule->type, ['percentage_discount', 'category_discount', 'tax'], true)) {
                    $flags['percent 0'] = $flags['percent 0'] || $rule->percent == 0;
                    $flags['percent 100'] = $flags['percent 100'] || $rule->percent == 100;
                }
                if ($rule->type === 'discount_cap') {
                    $flags['cap 0'] = $flags['cap 0'] || $rule->max_percent == 0;
                    $flags['cap 100'] = $flags['cap 100'] || $rule->max_percent == 100;
                }
                if ($rule->type === 'quantity_promo') {
                    $flags['pay 0'] = $flags['pay 0'] || $rule->pay === 0;
                    foreach ($quote->budget->items as $item) {
                        if ($item->category === $rule->category && $item->quantity % $rule->buy !== 0) {
                            $flags['remainder'] = true;
                        }
                    }
                }
                if (in_array($rule->type, ['category_discount', 'quantity_promo'], true) && ! isset($categories[$rule->category])) {
                    $flags['absent category'] = true;
                }
                if ($rule->type === 'fixed_discount') {
                    $flags['zero fixed amount'] = $flags['zero fixed amount'] || $rule->amount == 0;
                    if ($rule->currency !== $quote->currency && $rule->amount > 0) {
                        $flags['fixed discount in another currency'] = true;
                    }
                }
            }
            $flags['all six rule types on one quote'] = $flags['all six rule types on one quote'] || count($typesOnQuote) === 6;

            $inverseOnly += $this->inverseOnlyPairs($quote);
        }

        $flags['duplicate budget id'] = max($budgetIds) >= 2;

        $missing = array_keys(array_filter($flags, static fn (bool $present): bool => ! $present));
        foreach (self::SIX_PAIRS as $pair) {
            if (! isset($pairs[$pair])) {
                $missing[] = 'pair '.$pair;
            }
            if (! isset($inverseOnly[$pair])) {
                $missing[] = 'inverse '.$pair;
            }
        }
        foreach (self::RULE_TYPES as $type) {
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

        self::assertSame([], $missing);
    }

    #[Test]
    /** BT-26, CAL-10, CAL-11, FX-03 */
    public function every_invalid_v2_case_gets_the_status_the_spec_requires(): void
    {
        $document = $this->dataset('invalid-v2.json');
        self::assertSame('v2-invalid', $document->version);
        self::assertSame(count($document->cases), $document->count);
        self::assertGreaterThan(60, $document->count);

        $ids = array_map(static fn (stdClass $case): string => $case->id, $document->cases);
        self::assertSame($ids, array_values(array_unique($ids)));
        foreach ([...array_keys(self::SPEC_ACCEPTED_CASES), ...self::DIFFERENT_ERROR_KEY_CASES] as $id) {
            self::assertContains($id, $ids);
        }

        $mismatches = [];
        foreach ($document->cases as $case) {
            $response = $this->call('POST', '/quotes', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ], json_encode($case->quote, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
            $status = $response->getStatusCode();

            if (isset(self::SPEC_ACCEPTED_CASES[$case->id])) {
                if ($status !== 201) {
                    $mismatches[] = $case->id.': expected 201, got '.$status;
                }

                continue;
            }

            if ($status !== 422) {
                $mismatches[] = $case->id.': expected 422, got '.$status;

                continue;
            }

            $keys = array_keys($response->json('errors') ?? []);
            if ($keys === []) {
                $mismatches[] = $case->id.': 422 without errors';
            }
            if (in_array($case->id, self::DIFFERENT_ERROR_KEY_CASES, true)) {
                continue;
            }
            foreach ($case->expected_errors as $error) {
                if (! in_array($error->path, $keys, true)) {
                    $mismatches[] = $case->id.': missing error key '.$error->path.' in ['.implode(', ', $keys).']';
                }
            }
        }

        self::assertSame([], $mismatches);
        $this->assertDatabaseCount('quotes', count(self::SPEC_ACCEPTED_CASES));
    }

    #[Test]
    /** BT-26 */
    public function the_generator_reproduces_both_datasets_byte_for_byte(): void
    {
        $previous = null;
        foreach (['a', 'b'] as $suffix) {
            $dir = sys_get_temp_dir().'/mottor-datasets-v2-'.$suffix.'-'.getmypid();
            if (! is_dir($dir) && ! mkdir($dir) && ! is_dir($dir)) {
                throw new RuntimeException('Cannot create '.$dir);
            }

            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, base_path('database/datasets/generate-v2.php'), $dir],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
            );
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), (string) $output);

            $current = [
                'valid' => (string) file_get_contents($dir.'/valid-v2.json'),
                'invalid' => (string) file_get_contents($dir.'/invalid-v2.json'),
            ];
            array_map('unlink', [$dir.'/valid-v2.json', $dir.'/invalid-v2.json']);
            rmdir($dir);

            self::assertSame($this->read('valid-v2.json'), $current['valid']);
            self::assertSame($this->read('invalid-v2.json'), $current['invalid']);
            if ($previous !== null) {
                self::assertSame($previous, $current);
            }
            $previous = $current;
        }
    }

    #[Test]
    /** BT-37, DEL-05 */
    public function the_official_dataset_keeps_its_shape_and_calculates_without_errors(): void
    {
        if (! is_file(self::OFFICIAL_DATASET)) {
            self::markTestSkipped('The official dataset is not mounted at '.self::OFFICIAL_DATASET.'.');
        }

        self::assertSame(self::OFFICIAL_SHA256, hash_file('sha256', self::OFFICIAL_DATASET));
        self::assertSame(10053823, filesize(self::OFFICIAL_DATASET));

        $document = json_decode((string) file_get_contents(self::OFFICIAL_DATASET), false, 512, JSON_THROW_ON_ERROR);
        self::assertSame('v2', $document->version);
        self::assertSame('2026-10-26', $document->generated_at);
        self::assertSame(10000, $document->count);
        self::assertCount(10000, $document->quotes);

        $pairs = [];
        $emptyRules = 0;
        $inverseQuotes = 0;
        foreach ($document->quotes as $quote) {
            foreach (array_keys(get_object_vars($quote->exchange_rates)) as $pair) {
                $pairs[$pair] = ($pairs[$pair] ?? 0) + 1;
            }
            $emptyRules += $quote->rules === [] ? 1 : 0;
            $inverseQuotes += $this->inverseOnlyPairs($quote) === [] ? 0 : 1;
        }
        unset($document);

        self::assertSame(['USD_ARS' => 9825, 'EUR_ARS' => 2084, 'ARS_USD' => 175], $pairs);
        self::assertSame(1401, $emptyRules);
        self::assertSame(1579, $inverseQuotes);

        self::assertSame(0, Artisan::call('quotes:validate-dataset', ['path' => self::OFFICIAL_DATASET]));
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(10000, $report['count']);
        self::assertSame(0, $report['negative_totals']);
        self::assertSame(0, $report['invalid_quotes']);
        $this->assertDatabaseCount('quotes', 0);
    }

    /** @return array<string, true> Needed pairs that only the inverse rate covers. */
    private function inverseOnlyPairs(stdClass $quote): array
    {
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

        $have = get_object_vars($quote->exchange_rates);
        $inverseOnly = [];
        foreach (array_keys($needed) as $direct) {
            [$from, $to] = explode('_', $direct);
            if (! array_key_exists($direct, $have) && array_key_exists($to.'_'.$from, $have)) {
                $inverseOnly[$direct] = true;
            }
        }

        return $inverseOnly;
    }

    private function read(string $filename): string
    {
        $raw = file_get_contents(base_path('database/datasets/'.$filename));
        self::assertIsString($raw);

        return $raw;
    }

    private function dataset(string $filename): stdClass
    {
        $decoded = json_decode($this->read($filename), false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $decoded);

        return $decoded;
    }
}
