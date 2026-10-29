<?php

declare(strict_types=1);

namespace App\Domain\Quote;

use App\Domain\Money\Money;
use Brick\Math\BigDecimal;

final readonly class NormalizedItem
{
    public function __construct(
        public int $id,
        public string $description,
        public string $category,
        public int $quantity,
        public string $unitPrice,
        public string $currency,
        public BigDecimal $outputUnitPrice,
        public Money $lineTotal,
    ) {}
}
