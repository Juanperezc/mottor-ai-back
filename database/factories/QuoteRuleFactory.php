<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\QuoteModel;
use App\Infrastructure\Persistence\Eloquent\QuoteRuleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuoteRuleModel> */
final class QuoteRuleFactory extends Factory
{
    protected $model = QuoteRuleModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $configuration = ['type' => 'percentage_discount', 'percent' => '5'];

        return [
            'quote_id' => QuoteModel::factory(),
            'position' => 1,
            'type' => $configuration['type'],
            'configuration' => $configuration,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function inputSet(string $currency): array
    {
        $fixedAmount = $currency === 'ARS' ? '1000' : '5';

        return [
            ['type' => 'quantity_promo', 'category' => 'mobiliario', 'buy' => 3, 'pay' => 2],
            ['type' => 'category_discount', 'category' => 'catering', 'percent' => '5'],
            ['type' => 'percentage_discount', 'percent' => '3'],
            ['type' => 'fixed_discount', 'amount' => $fixedAmount, 'currency' => $currency],
            ['type' => 'discount_cap', 'max_percent' => '25'],
            ['type' => 'tax', 'name' => 'IVA', 'percent' => '21'],
        ];
    }
}
