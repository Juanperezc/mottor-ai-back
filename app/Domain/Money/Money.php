<?php

declare(strict_types=1);

namespace App\Domain\Money;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Money
{
    private function __construct(
        private BigDecimal $amount,
        private RoundingMode $roundingMode,
    ) {}

    public static function of(string|int $amount, RoundingMode $roundingMode = RoundingMode::HalfUp): self
    {
        return self::fromDecimal(BigDecimal::of($amount), $roundingMode);
    }

    public static function fromDecimal(BigDecimal $amount, RoundingMode $roundingMode = RoundingMode::HalfUp): self
    {
        return new self($amount->toScale(2, $roundingMode), $roundingMode);
    }

    public static function zero(RoundingMode $roundingMode = RoundingMode::HalfUp): self
    {
        return self::of(0, $roundingMode);
    }

    public function plus(self $other): self
    {
        return self::fromDecimal($this->amount->plus($other->amount), $this->roundingMode);
    }

    public function minus(self $other): self
    {
        return self::fromDecimal($this->amount->minus($other->amount), $this->roundingMode);
    }

    public function multipliedBy(string|int $factor): self
    {
        return self::fromDecimal($this->amount->multipliedBy($factor), $this->roundingMode);
    }

    public function percentage(string|int $percent): self
    {
        return self::fromDecimal(
            $this->amount->multipliedBy($percent)->dividedBy(100, 12, $this->roundingMode),
            $this->roundingMode,
        );
    }

    public function negated(): self
    {
        return self::fromDecimal($this->amount->negated(), $this->roundingMode);
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount->isGreaterThan($other->amount);
    }

    public function decimal(): BigDecimal
    {
        return $this->amount;
    }

    public function toString(): string
    {
        return (string) $this->amount;
    }
}
