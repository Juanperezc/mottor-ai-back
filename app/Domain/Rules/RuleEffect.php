<?php

declare(strict_types=1);

namespace App\Domain\Rules;

use App\Domain\Money\Money;

final readonly class RuleEffect
{
    public function __construct(
        public Money $base,
        public Money $amount,
        public Money $discountDelta,
        public string $label,
    ) {}
}
