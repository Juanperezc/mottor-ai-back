<?php

declare(strict_types=1);

namespace App\Application\Quotes;

use App\Domain\Quote\CalculatedQuote;

final readonly class StoredQuote
{
    public function __construct(
        public int $id,
        public CalculatedQuote $quote,
    ) {}
}
