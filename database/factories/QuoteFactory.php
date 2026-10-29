<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\QuoteModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuoteModel> */
final class QuoteFactory extends Factory
{
    private const CLIENTS = [
        'Agencia Norte',
        'Entre Ríos Group',
        'Eventos Córdoba',
        'Peña & Asociados',
        'Müller Producciones',
        'São Paulo Eventos',
    ];

    protected $model = QuoteModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'budget_id' => fake()->unique()->bothify('FACTORY-####-????'),
            'client' => fake()->randomElement(self::CLIENTS),
            'event' => fake()->randomElement(['Cena anual', 'Lanzamiento de producto', 'Conferencia regional']),
            'currency' => 'ARS',
            'subtotal' => '0.00',
            'total' => '0.00',
            'exchange_rates' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function input(int $sequence): array
    {
        $variant = $sequence % 4;
        [$currency, $exchangeRates, $itemCurrencies] = match ($variant) {
            0 => ['ARS', ['USD_ARS' => '1450', 'EUR_ARS' => '1580'], ['ARS', 'USD', 'EUR']],
            1 => ['USD', ['ARS_USD' => '0.00069', 'EUR_USD' => '1.09'], ['USD', 'ARS', 'EUR']],
            2 => ['EUR', ['ARS_EUR' => '0.00063', 'USD_EUR' => '0.92'], ['EUR', 'ARS', 'USD']],
            default => ['ARS', [], ['ARS', 'ARS', 'ARS']],
        };

        $prices = [
            'ARS' => ['18000', '9500', '240000'],
            'USD' => ['120', '65', '850'],
            'EUR' => ['110', '60', '780'],
        ];
        $items = QuoteItemFactory::new();

        return [
            'budget' => [
                'id' => sprintf('SEED-%06d', $sequence + 1),
                'client' => self::CLIENTS[$sequence % count(self::CLIENTS)],
                'event' => sprintf('Evento corporativo %d · %d personas', $sequence + 1, 80 + ($sequence % 121)),
                'items' => [
                    $items->input(1, 'Menú de tres pasos', 'catering', 12 + ($sequence % 10), $prices[$itemCurrencies[0]][0], $itemCurrencies[0]),
                    $items->input(2, 'Silla tiffany', 'mobiliario', 6 + ($sequence % 7), $prices[$itemCurrencies[1]][1], $itemCurrencies[1]),
                    $items->input(3, 'Pantalla LED con operador técnico', 'tecnica', 1, $prices[$itemCurrencies[2]][2], $itemCurrencies[2]),
                ],
            ],
            'exchange_rates' => $exchangeRates,
            'currency' => $currency,
            'rules' => QuoteRuleFactory::new()->inputSet($currency),
        ];
    }
}
