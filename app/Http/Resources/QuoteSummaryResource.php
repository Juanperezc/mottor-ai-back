<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Exception\DomainValidationException;
use App\Domain\Money\Money;
use App\Domain\Money\RateSet;
use App\Domain\Ticket\TicketLineFormatter;
use App\Http\Json\DecimalNumber;
use App\Infrastructure\Persistence\Eloquent\QuoteModel;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class QuoteSummaryResource extends ExactJsonResource
{
    public function __construct(QuoteModel $resource, private readonly string $currency)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        /** @var QuoteModel $quote */
        $quote = $this->resource;
        $converted = null;
        try {
            $factor = (new RateSet($quote->exchange_rates, app(RoundingMode::class)))->factor($quote->currency, $this->currency);
            $converted = Money::fromDecimal(BigDecimal::of((string) $quote->total)->multipliedBy($factor), app(RoundingMode::class));
        } catch (DomainValidationException) {
        }

        return [
            'id' => (int) $quote->id, 'budget_id' => $quote->budget_id, 'client' => $quote->client, 'event' => $quote->event,
            'event_lines' => app(TicketLineFormatter::class)->format($quote->event), 'original_currency' => $quote->currency,
            'original_total' => DecimalNumber::money((string) $quote->total), 'currency' => $this->currency,
            'total' => $converted === null ? null : DecimalNumber::money($converted->toString()),
        ];
    }
}
