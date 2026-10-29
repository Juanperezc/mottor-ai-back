<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Contracts\QuotePersistence;
use App\Application\Quotes\StoreQuoteBatch;
use App\Domain\Quote\CalculatedQuote;
use App\Domain\Quote\QuoteCalculator;
use App\Domain\Rules\RuleRegistry;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Fixtures\QuoteFixtures;
use Tests\TestCase;

final class QuoteApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    /** A31 */
    public function health_is_available_at_the_contract_path(): void
    {
        self::assertSame('mysql', config('database.default'));
        self::assertSame('testing', config('database.connections.mysql.database'));
        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    #[Test]
    /** A01, A19, A30, A32, A34 */
    public function it_creates_and_reads_the_official_quote_with_numeric_amounts(): void
    {
        $response = $this->postJson('/quotes', QuoteFixtures::official())
            ->assertCreated()
            ->assertJsonPath('budget_id', 'PRE-2026-0418')
            ->assertJsonPath('total', 4722388.0);

        self::assertStringContainsString('"subtotal":4878500.00', $response->getContent());
        self::assertStringContainsString('"amount":-119500.00', $response->getContent());
        $expected = QuoteFixtures::officialExpected();
        $expected['id'] = $response->json('id');
        self::assertEquals($expected, $response->json());
        $this->assertDatabaseCount('quotes', 1);
        $this->assertDatabaseCount('quote_items', 6);
        $this->assertDatabaseCount('quote_steps', 6);

        $this->getJson('/quotes/'.$response->json('id'))
            ->assertOk()
            ->assertJsonPath('breakdown.5.running_total', 4722388.0);
        $this->getJson('/quotes/999')->assertNotFound()->assertJsonStructure(['message']);
    }

    #[Test]
    /** A02 */
    public function it_returns_the_exact_second_official_fixture(): void
    {
        $response = $this->postJson('/quotes', QuoteFixtures::second())->assertCreated();
        $expected = QuoteFixtures::secondExpected();
        $expected['id'] = $response->json('id');

        self::assertEquals($expected, $response->json());
        self::assertStringContainsString('"amount":-927028.13', $response->getContent());
        self::assertStringContainsString('"total":7832871.49', $response->getContent());
    }

    #[Test]
    /** BT-26 */
    public function it_calculates_a_large_decimal_from_the_raw_json_token(): void
    {
        $raw = <<<'JSON'
{"budget":{"id":"EXACT-1","client":"Exact","event":"Exact decimal","items":[{"id":1,"description":"Exact","category":"test","quantity":1,"unit_price":99999999999999.99,"currency":"ARS"}]},"exchange_rates":{},"currency":"ARS","rules":[]}
JSON;

        $response = $this->call('POST', '/quotes', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertCreated();

        self::assertStringContainsString('"unit_price":99999999999999.99', $response->getContent());
        self::assertStringContainsString('"subtotal":99999999999999.99', $response->getContent());
        $this->assertDatabaseHas('quote_items', ['unit_price' => '99999999999999.990000']);
    }

    #[Test]
    #[DataProvider('invalidRuleConfigurations')]
    /** A16, BT-11 */
    public function strategy_errors_use_the_exact_indexed_field_path(array $rule, string $path): void
    {
        $input = QuoteFixtures::official();
        $input['rules'] = [
            ['type' => 'percentage_discount', 'percent' => 0],
            $rule,
        ];

        $response = $this->postJson('/quotes', $input)->assertUnprocessable();

        self::assertSame([$path], array_keys($response->json('errors')));
        $this->assertDatabaseCount('quotes', 0);
    }

    public static function invalidRuleConfigurations(): array
    {
        return [
            'unknown type' => [['type' => 'future_rule'], 'rules.1.type'],
            'promo category' => [['type' => 'quantity_promo', 'buy' => 3, 'pay' => 2], 'rules.1.category'],
            'promo buy' => [['type' => 'quantity_promo', 'category' => 'test', 'pay' => 2], 'rules.1.buy'],
            'promo pay' => [['type' => 'quantity_promo', 'category' => 'test', 'buy' => 3], 'rules.1.pay'],
            'promo relation' => [['type' => 'quantity_promo', 'category' => 'test', 'buy' => 2, 'pay' => 3], 'rules.1.pay'],
            'category discount category' => [['type' => 'category_discount', 'percent' => 10], 'rules.1.category'],
            'category discount percent' => [['type' => 'category_discount', 'category' => 'test'], 'rules.1.percent'],
            'percentage percent' => [['type' => 'percentage_discount'], 'rules.1.percent'],
            'fixed amount' => [['type' => 'fixed_discount', 'currency' => 'ARS'], 'rules.1.amount'],
            'fixed currency' => [['type' => 'fixed_discount', 'amount' => 10], 'rules.1.currency'],
            'cap percent' => [['type' => 'discount_cap'], 'rules.1.max_percent'],
            'tax name' => [['type' => 'tax', 'percent' => 21], 'rules.1.name'],
            'tax percent' => [['type' => 'tax', 'name' => 'IVA'], 'rules.1.percent'],
        ];
    }

    #[Test]
    /** BT-11 */
    public function item_errors_use_exact_paths_in_single_and_batch_requests(): void
    {
        $single = QuoteFixtures::official();
        unset($single['budget']['items'][0]['unit_price']);
        $this->postJson('/quotes', $single)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['budget.items.0.unit_price']);

        $batch = QuoteFixtures::official();
        $batch['budget']['items'][] = $batch['budget']['items'][0];
        $batch['budget']['items'][1]['quantity'] = 0;
        $this->postJson('/quotes/batch', ['quotes' => [$batch]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quotes.0.budget.items.1.quantity']);

        $this->assertDatabaseCount('quotes', 0);
    }

    #[Test]
    /** A16, A35, A36 */
    public function a_batch_is_atomic_and_budget_ids_are_not_unique(): void
    {
        $valid = QuoteFixtures::official();
        $invalid = QuoteFixtures::official();
        $invalid['rules'][2]['type'] = 'unknown';

        $response = $this->postJson('/quotes/batch', ['quotes' => [$valid, $valid, $invalid]])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
        self::assertSame(
            "The rule type 'unknown' is not supported.",
            $response->json('errors')['quotes.2.rules.2.type'][0],
        );
        $this->assertDatabaseCount('quotes', 0);

        $inserts = [];
        DB::listen(function ($query) use (&$inserts): void {
            if (str_starts_with(strtolower($query->sql), 'insert into')) {
                $inserts[] = $query->sql;
            }
        });

        $this->postJson('/quotes/batch', ['quotes' => [$valid, QuoteFixtures::second(), $valid]])
            ->assertCreated()
            ->assertJsonPath('imported', 3)
            ->assertJsonPath('quotes.0.budget_id', 'PRE-2026-0418')
            ->assertJsonPath('quotes.1.budget_id', 'PRE-2026-0419')
            ->assertJsonPath('quotes.2.budget_id', 'PRE-2026-0418');
        $this->assertDatabaseCount('quotes', 3);
        foreach (['quotes', 'quote_items', 'quote_rules', 'quote_steps'] as $table) {
            self::assertCount(1, array_filter($inserts, fn (string $sql): bool => str_contains($sql, "`{$table}`")), $table);
        }
    }

    #[Test]
    /** A38, BT-28 */
    public function a_batch_of_501_quotes_is_rejected_before_any_write(): void
    {
        $quotes = array_fill(0, 501, QuoteFixtures::official());

        $this->postJson('/quotes/batch', ['quotes' => $quotes])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quotes']);

        foreach (['quotes', 'quote_items', 'quote_rules', 'quote_steps'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    #[Test]
    /** A37 */
    public function a_write_failure_rolls_back_the_whole_batch(): void
    {
        app()->instance(QuotePersistence::class, new class implements QuotePersistence
        {
            public function saveMany(array $quotes, ?int $importId = null): array
            {
                /** @var CalculatedQuote $quote */
                $quote = $quotes[0];
                DB::table('quotes')->insert([
                    'import_id' => $importId,
                    'budget_id' => $quote->budgetId,
                    'client' => $quote->client,
                    'event' => $quote->event,
                    'currency' => $quote->currency,
                    'subtotal' => $quote->subtotal->toString(),
                    'total' => $quote->total->toString(),
                    'exchange_rates' => json_encode($quote->exchangeRates, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $quoteId = (int) DB::connection()->getPdo()->lastInsertId();
                DB::table('quote_items')->insert([
                    'quote_id' => $quoteId,
                    'source_id' => 1,
                    'position' => 1,
                    'description' => 'Partial write',
                    'category' => 'test',
                    'quantity' => 1,
                    'unit_price' => '1.000000',
                    'currency' => 'ARS',
                    'line_total' => '1.00',
                ]);

                throw new RuntimeException('Simulated persistence failure.');
            }

            public function transaction(callable $callback): mixed
            {
                return DB::transaction($callback);
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->postJson('/quotes/batch', ['quotes' => [QuoteFixtures::official(), QuoteFixtures::second()]]);
            self::fail('The simulated write failure was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated persistence failure.', $exception->getMessage());
        }

        foreach (['quotes', 'quote_items', 'quote_rules', 'quote_steps'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    #[Test]
    /** A25, A40, A41 */
    public function listing_uses_select_plus_count_and_escapes_like_wildcards(): void
    {
        $percentage = QuoteFixtures::official();
        $percentage['budget']['client'] = '100%_ Events';
        $other = QuoteFixtures::official();
        $other['budget']['client'] = 'Agencia Norte';
        app(StoreQuoteBatch::class)->execute(QuoteFixtures::exact([$percentage, $other]));

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->getJson('/quotes?currency=USD&client=%25_')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client', '100%_ Events')
            ->assertJsonPath('data.0.total', 3256.82)
            ->assertJsonPath('meta.total', 1);

        self::assertCount(2, $queries);

        $this->getJson('/quotes?client=NORTE')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client', 'Agencia Norte');
    }

    #[Test]
    /** A25 */
    public function listing_matches_the_official_usd_fixture(): void
    {
        $stored = app(StoreQuoteBatch::class)->execute(QuoteFixtures::exact([QuoteFixtures::official()]))[0];
        $expected = QuoteFixtures::officialListUsdExpected();
        $expected['id'] = $stored->id;

        $response = $this->getJson('/quotes?currency=USD')->assertOk();

        self::assertEquals($expected, $response->json('data.0'));
        self::assertStringContainsString('"total":3256.82', $response->getContent());
    }

    #[Test]
    /** A26, A39, A41, BT-30 */
    public function listing_handles_extreme_pages_null_conversions_and_both_query_limits(): void
    {
        $input = QuoteFixtures::official();
        $input['budget']['items'] = [[
            'id' => 1,
            'description' => 'ARS only',
            'category' => 'test',
            'quantity' => 1,
            'unit_price' => 100,
            'currency' => 'ARS',
        ]];
        $input['exchange_rates'] = [];
        $input['rules'] = [];
        app(StoreQuoteBatch::class)->execute([$input]);

        foreach ([1, 200] as $perPage) {
            DB::enableQueryLog();
            DB::flushQueryLog();

            $response = $this->getJson("/quotes?currency=USD&per_page={$perPage}")
                ->assertOk()
                ->assertJsonPath('data.0.total', null)
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('meta.last_page', 1);

            self::assertCount(2, DB::getQueryLog(), "per_page={$perPage}");
            DB::disableQueryLog();
        }

        $this->getJson('/quotes?page=999999999&per_page=200')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.page', 999999999)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.last_page', 1);

        $this->getJson('/quotes?per_page=201')->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
    }

    #[Test]
    /** A33, BT-29 */
    public function detail_reads_the_stored_steps_after_the_engine_binding_changes(): void
    {
        $created = $this->postJson('/quotes', QuoteFixtures::official())->assertCreated();
        $expected = $created->getContent();

        app()->instance(RuleRegistry::class, new RuleRegistry([]));
        app()->forgetInstance(QuoteCalculator::class);

        self::assertSame(
            $expected,
            $this->get('/quotes/'.$created->json('id'))->assertOk()->getContent(),
        );
    }

    #[Test]
    /** A20 */
    public function listing_uses_the_configured_rounding_mode(): void
    {
        config(['money.rounding_mode' => 'HALF_DOWN']);
        app()->forgetInstance(RoundingMode::class);
        app()->forgetInstance(QuoteCalculator::class);
        $input = QuoteFixtures::official();
        $input['budget']['items'] = [[
            'id' => 1,
            'description' => 'Rounding tie',
            'category' => 'test',
            'quantity' => 1,
            'unit_price' => 1.00,
            'currency' => 'ARS',
        ]];
        $input['exchange_rates'] = ['USD_ARS' => 8.00];
        $input['rules'] = [['type' => 'percentage_discount', 'percent' => 0]];

        $this->postJson('/quotes', $input)->assertCreated();
        $this->getJson('/quotes?currency=USD')
            ->assertOk()
            ->assertJsonPath('data.0.total', 0.12);
    }

    #[Test]
    /** A42 */
    public function it_returns_validation_errors_and_cors_headers(): void
    {
        config([
            'app.debug' => false,
            'cors.allowed_origins' => ['http://localhost:5173'],
        ]);

        $response = $this->call('POST', '/quotes', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}')->assertUnprocessable()->assertJsonStructure([
            'message', 'errors' => ['budget', 'currency', 'rules'],
        ]);
        self::assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
        self::assertArrayNotHasKey('trace', $response->json());

        $this->options('/quotes', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ])->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Headers', 'Content-Type');

        $preflight = $this->options('/quotes', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
        ])->assertNoContent();
        self::assertStringContainsString('GET', (string) $preflight->headers->get('Access-Control-Allow-Methods'));
        self::assertStringContainsString('POST', (string) $preflight->headers->get('Access-Control-Allow-Methods'));
    }

    #[Test]
    /** A17, A23 */
    public function it_rejects_missing_rates_and_negative_totals(): void
    {
        $missingRate = QuoteFixtures::official();
        $missingRate['exchange_rates'] = [];
        $this->postJson('/quotes', $missingRate)
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['exchange_rates']]);

        $negative = QuoteFixtures::official();
        $negative['rules'] = [
            ['type' => 'fixed_discount', 'amount' => 99999999, 'currency' => 'ARS'],
        ];
        $this->postJson('/quotes', $negative)
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['rules']]);

        $this->assertDatabaseCount('quotes', 0);
    }
}
