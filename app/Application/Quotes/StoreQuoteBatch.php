<?php

declare(strict_types=1);

namespace App\Application\Quotes;

use App\Application\Contracts\QuotePersistence;
use App\Domain\Exception\DomainValidationException;
use App\Domain\Quote\QuoteCalculator;

final readonly class StoreQuoteBatch
{
    public function __construct(
        private DecimalInputNormalizer $normalizer,
        private QuoteCalculator $calculator,
        private QuotePersistence $persistence,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $inputs
     * @return list<StoredQuote>
     */
    public function execute(array $inputs, ?int $importId = null): array
    {
        $calculated = [];
        foreach ($inputs as $index => $input) {
            try {
                $calculated[] = $this->calculator->calculate($this->normalizer->normalize($input));
            } catch (DomainValidationException $exception) {
                if (count($inputs) === 1) {
                    throw $exception;
                }

                $errors = [];
                foreach ($exception->errors as $path => $messages) {
                    $errors["quotes.{$index}.{$path}"] = $messages;
                }

                throw new DomainValidationException($errors, $exception->getMessage());
            }
        }

        return $this->persistence->transaction(function () use ($calculated, $importId): array {
            $ids = $this->persistence->saveMany($calculated, $importId);

            return array_map(
                fn (int $id, $quote): StoredQuote => new StoredQuote($id, $quote),
                $ids,
                $calculated,
            );
        });
    }
}
