<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Quotes\StoredQuote;
use App\Domain\Quote\CalculatedQuote;
use App\Domain\Ticket\TicketLineFormatter;
use App\Http\Json\DecimalNumber;
use App\Infrastructure\Persistence\Eloquent\QuoteModel;

/** @mixin QuoteModel|StoredQuote */
final class QuoteResource extends ExactJsonResource
{
    public function toArray($request): array
    {
        $lines = app(TicketLineFormatter::class);
        $resource = $this->resource;
        if ($resource instanceof StoredQuote) {
            return self::calculated($resource->id, $resource->quote, $lines);
        }

        return self::model($resource, $lines);
    }

    private static function calculated(int $id, CalculatedQuote $quote, TicketLineFormatter $lines): array
    {
        return [
            'id' => $id, 'budget_id' => $quote->budgetId, 'client' => $quote->client, 'event' => $quote->event,
            'event_lines' => $lines->format($quote->event), 'currency' => $quote->currency,
            'subtotal' => DecimalNumber::money($quote->subtotal->toString()), 'total' => DecimalNumber::money($quote->total->toString()),
            'exchange_rates' => self::rates($quote->exchangeRates),
            'items' => array_map(fn ($item): array => [
                'id' => $item->id, 'description' => $item->description, 'description_lines' => $lines->format($item->description),
                'category' => $item->category, 'quantity' => $item->quantity, 'unit_price' => DecimalNumber::money($item->unitPrice),
                'currency' => $item->currency, 'line_total' => DecimalNumber::money($item->lineTotal->toString()),
            ], $quote->items),
            'breakdown' => array_map(fn ($step): array => [
                'step' => $step->step, 'type' => $step->type, 'label' => $step->label,
                'base' => DecimalNumber::money($step->base->toString()), 'amount' => DecimalNumber::money($step->amount->toString()),
                'running_total' => DecimalNumber::money($step->runningTotal->toString()),
            ], $quote->steps),
        ];
    }

    private static function model(QuoteModel $quote, TicketLineFormatter $lines): array
    {
        return [
            'id' => (int) $quote->id, 'budget_id' => $quote->budget_id, 'client' => $quote->client, 'event' => $quote->event,
            'event_lines' => $lines->format($quote->event), 'currency' => $quote->currency,
            'subtotal' => DecimalNumber::money((string) $quote->subtotal), 'total' => DecimalNumber::money((string) $quote->total),
            'exchange_rates' => self::rates($quote->exchange_rates),
            'items' => $quote->items->map(fn ($item): array => [
                'id' => (int) $item->source_id, 'description' => $item->description, 'description_lines' => $lines->format($item->description),
                'category' => $item->category, 'quantity' => (int) $item->quantity, 'unit_price' => DecimalNumber::money((string) $item->unit_price),
                'currency' => $item->currency, 'line_total' => DecimalNumber::money((string) $item->line_total),
            ])->all(),
            'breakdown' => $quote->steps->map(fn ($step): array => [
                'step' => (int) $step->step, 'type' => $step->type, 'label' => $step->label,
                'base' => DecimalNumber::money((string) $step->base), 'amount' => DecimalNumber::money((string) $step->amount),
                'running_total' => DecimalNumber::money((string) $step->running_total),
            ])->all(),
        ];
    }

    /** @param array<string, string> $rates @return array<string, DecimalNumber> */
    private static function rates(array $rates): array
    {
        return array_map(fn (string $rate) => DecimalNumber::rate($rate), $rates);
    }
}
