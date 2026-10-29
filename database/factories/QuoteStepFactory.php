<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\QuoteModel;
use App\Infrastructure\Persistence\Eloquent\QuoteStepModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuoteStepModel> */
final class QuoteStepFactory extends Factory
{
    protected $model = QuoteStepModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'quote_id' => QuoteModel::factory(),
            'step' => 1,
            'type' => 'percentage_discount',
            'label' => '0% general',
            'base' => '0.00',
            'amount' => '0.00',
            'running_total' => '0.00',
        ];
    }
}
