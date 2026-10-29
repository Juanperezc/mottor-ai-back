<?php

declare(strict_types=1);

namespace App\Domain\Rules;

use App\Domain\Money\Money;

final readonly class BreakdownStep
{
    public function __construct(
        public int $step,
        public string $type,
        public string $label,
        public Money $base,
        public Money $amount,
        public Money $runningTotal,
    ) {}
}
