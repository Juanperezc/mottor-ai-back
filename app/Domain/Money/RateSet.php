<?php

declare(strict_types=1);

namespace App\Domain\Money;

use App\Domain\Exception\DomainValidationException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class RateSet
{
    /** @var array<string, string> */
    private array $rates;

    /** @param array<string, string> $rates */
    public function __construct(
        array $rates,
        private RoundingMode $roundingMode = RoundingMode::HalfUp,
    ) {
        foreach ($rates as $pair => $rate) {
            if (! preg_match('/^[A-Z]{3}_[A-Z]{3}$/', $pair) || BigDecimal::of($rate)->isLessThanOrEqualTo(0)) {
                throw new DomainValidationException([
                    "exchange_rates.{$pair}" => ['Exchange rates must be positive decimal values.'],
                ]);
            }
        }

        $this->rates = $rates;
    }

    public function factor(string $from, string $to): BigDecimal
    {
        if ($from === $to) {
            return BigDecimal::one();
        }

        $direct = "{$from}_{$to}";
        if (isset($this->rates[$direct])) {
            return BigDecimal::of($this->rates[$direct]);
        }

        $inverse = "{$to}_{$from}";
        if (isset($this->rates[$inverse])) {
            return BigDecimal::one()->dividedBy($this->rates[$inverse], 12, $this->roundingMode);
        }

        throw new DomainValidationException([
            'exchange_rates' => ["A direct or inverse {$from}/{$to} exchange rate is required."],
        ]);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->rates;
    }
}
