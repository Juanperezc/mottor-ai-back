<?php

namespace App\Providers;

use App\Application\Contracts\QuotePersistence;
use App\Domain\Quote\QuoteCalculator;
use App\Domain\Rules\CalculationState;
use App\Domain\Rules\RuleEngine;
use App\Domain\Rules\RuleRegistry;
use App\Domain\Rules\Strategies\CategoryDiscountRule;
use App\Domain\Rules\Strategies\DiscountCapRule;
use App\Domain\Rules\Strategies\FixedDiscountRule;
use App\Domain\Rules\Strategies\PercentageDiscountRule;
use App\Domain\Rules\Strategies\QuantityPromoRule;
use App\Domain\Rules\Strategies\TaxRule;
use App\Infrastructure\Persistence\EloquentQuoteRepository;
use Brick\Math\RoundingMode;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(QuotePersistence::class, EloquentQuoteRepository::class);
        $this->app->singleton(RoundingMode::class, fn () => match (strtoupper((string) config('money.rounding_mode'))) {
            'UP' => RoundingMode::Up,
            'DOWN' => RoundingMode::Down,
            'CEILING' => RoundingMode::Ceiling,
            'FLOOR' => RoundingMode::Floor,
            'HALF_UP' => RoundingMode::HalfUp,
            'HALF_DOWN' => RoundingMode::HalfDown,
            'HALF_CEILING' => RoundingMode::HalfCeiling,
            'HALF_FLOOR' => RoundingMode::HalfFloor,
            'HALF_EVEN' => RoundingMode::HalfEven,
            'HALF_ODD' => RoundingMode::HalfOdd,
            default => throw new \RuntimeException('Unsupported money rounding mode.'),
        });
        $this->app->singleton(RuleRegistry::class, fn () => new RuleRegistry([
            'quantity_promo' => fn (array $config, CalculationState $state) => new QuantityPromoRule($config),
            'category_discount' => fn (array $config, CalculationState $state) => new CategoryDiscountRule($config),
            'percentage_discount' => fn (array $config, CalculationState $state) => new PercentageDiscountRule($config),
            'fixed_discount' => fn (array $config, CalculationState $state) => new FixedDiscountRule($config),
            'discount_cap' => fn (array $config, CalculationState $state) => new DiscountCapRule($config),
            'tax' => fn (array $config, CalculationState $state) => new TaxRule($config),
        ]));
        $this->app->singleton(RuleEngine::class);
        $this->app->singleton(QuoteCalculator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
