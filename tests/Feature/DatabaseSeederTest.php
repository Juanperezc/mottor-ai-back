<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\QuoteItemModel;
use App\Infrastructure\Persistence\Eloquent\QuoteModel;
use App\Infrastructure\Persistence\Eloquent\QuoteRuleModel;
use App\Infrastructure\Persistence\Eloquent\QuoteStepModel;
use Database\Factories\QuoteFactory;
use Database\Factories\QuoteItemFactory;
use Database\Factories\QuoteRuleFactory;
use Database\Factories\QuoteStepFactory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_coherent_quotes_for_listing_scenarios(): void
    {
        self::assertInstanceOf(QuoteFactory::class, QuoteModel::factory());
        self::assertInstanceOf(QuoteItemFactory::class, QuoteItemModel::factory());
        self::assertInstanceOf(QuoteRuleFactory::class, QuoteRuleModel::factory());
        self::assertInstanceOf(QuoteStepFactory::class, QuoteStepModel::factory());

        config(['seeding.quotes' => 55]);

        $this->seed(DatabaseSeeder::class);

        $listing = $this->getJson('/quotes?currency=USD&per_page=50')
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 55)
            ->assertJsonPath('meta.last_page', 2);

        self::assertContains(null, array_column($listing->json('data'), 'total'));
        self::assertSame(range(1, 50), array_column($listing->json('data'), 'id'));

        $this->getJson('/quotes?client=entre%20rios&currency=ARS')
            ->assertOk()
            ->assertJsonPath('meta.total', 9)
            ->assertJsonPath('data.0.client', 'Entre Ríos Group');

        self::assertSame(['ARS', 'EUR', 'USD'], $this->sortedDistinctValues('quote_items', 'currency'));
        self::assertSame([
            'category_discount',
            'discount_cap',
            'fixed_discount',
            'percentage_discount',
            'quantity_promo',
            'tax',
        ], $this->sortedDistinctValues('quote_rules', 'type'));
        $this->assertDatabaseCount('quote_steps', 55 * 6);
    }

    /** @return list<string> */
    private function sortedDistinctValues(string $table, string $column): array
    {
        $values = DB::table($table)->distinct()->pluck($column)->all();
        sort($values);

        return $values;
    }
}
