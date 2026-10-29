<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Contracts\QuotePersistence;
use App\Domain\Quote\CalculatedQuote;
use Illuminate\Support\Facades\DB;

final class EloquentQuoteRepository implements QuotePersistence
{
    private const int INSERT_BATCH_SIZE = 500;

    public function saveMany(array $quotes, ?int $importId = null): array
    {
        if ($quotes === []) {
            return [];
        }

        $timestamp = now();
        $quoteRows = array_map(fn (CalculatedQuote $quote): array => [
            'import_id' => $importId,
            'budget_id' => $quote->budgetId,
            'client' => $quote->client,
            'event' => $quote->event,
            'currency' => $quote->currency,
            'subtotal' => $quote->subtotal->toString(),
            'total' => $quote->total->toString(),
            'exchange_rates' => json_encode($quote->exchangeRates, JSON_THROW_ON_ERROR),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $quotes);

        $quoteIds = [];
        foreach (array_chunk($quoteRows, self::INSERT_BATCH_SIZE) as $rows) {
            DB::table('quotes')->insert($rows);
            $firstId = (int) DB::connection()->getPdo()->lastInsertId();
            array_push($quoteIds, ...range($firstId, $firstId + count($rows) - 1));
        }

        $itemRows = [];
        $ruleRows = [];
        $stepRows = [];

        foreach ($quotes as $quoteIndex => $quote) {
            $quoteId = $quoteIds[$quoteIndex];

            foreach ($quote->items as $position => $item) {
                $itemRows[] = [
                    'quote_id' => $quoteId,
                    'source_id' => $item->id,
                    'position' => $position + 1,
                    'description' => $item->description,
                    'category' => $item->category,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unitPrice,
                    'currency' => $item->currency,
                    'line_total' => $item->lineTotal->toString(),
                ];
            }

            foreach ($quote->rules as $position => $rule) {
                $ruleRows[] = [
                    'quote_id' => $quoteId,
                    'position' => $position + 1,
                    'type' => $rule['type'],
                    'configuration' => json_encode($rule, JSON_THROW_ON_ERROR),
                ];
            }

            foreach ($quote->steps as $step) {
                $stepRows[] = [
                    'quote_id' => $quoteId,
                    'step' => $step->step,
                    'type' => $step->type,
                    'label' => $step->label,
                    'base' => $step->base->toString(),
                    'amount' => $step->amount->toString(),
                    'running_total' => $step->runningTotal->toString(),
                ];
            }
        }

        $this->insertInBatches('quote_items', $itemRows);
        $this->insertInBatches('quote_rules', $ruleRows);
        $this->insertInBatches('quote_steps', $stepRows);

        return $quoteIds;
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    /** @param list<array<string, mixed>> $rows */
    private function insertInBatches(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::INSERT_BATCH_SIZE) as $batch) {
            DB::table($table)->insert($batch);
        }
    }
}
