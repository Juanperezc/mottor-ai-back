<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\QuoteItemModel;
use App\Infrastructure\Persistence\Eloquent\QuoteModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuoteItemModel> */
final class QuoteItemFactory extends Factory
{
    protected $model = QuoteItemModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 12);
        $unitPrice = fake()->numberBetween(1_000, 50_000);

        return [
            'quote_id' => QuoteModel::factory(),
            'source_id' => 1,
            'position' => 1,
            'description' => fake()->randomElement(['Menú ejecutivo', 'Sillas de salón', 'Pantalla LED']),
            'category' => fake()->randomElement(['catering', 'mobiliario', 'tecnica']),
            'quantity' => $quantity,
            'unit_price' => number_format($unitPrice, 6, '.', ''),
            'currency' => 'ARS',
            'line_total' => number_format($quantity * $unitPrice, 2, '.', ''),
        ];
    }

    /** @return array<string, mixed> */
    public function input(
        int $id,
        string $description,
        string $category,
        int $quantity,
        string $unitPrice,
        string $currency,
    ): array {
        return [
            'id' => $id,
            'description' => $description,
            'category' => $category,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'currency' => $currency,
        ];
    }
}
