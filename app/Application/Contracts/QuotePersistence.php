<?php

declare(strict_types=1);

namespace App\Application\Contracts;

use App\Domain\Quote\CalculatedQuote;

interface QuotePersistence
{
    /** @param list<CalculatedQuote> $quotes @return list<int> */
    public function saveMany(array $quotes, ?int $importId = null): array;

    /** @template T @param callable(): T $callback @return T */
    public function transaction(callable $callback): mixed;
}
