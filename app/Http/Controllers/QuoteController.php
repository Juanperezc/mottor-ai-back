<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Quotes\StoreQuoteBatch;
use App\Http\Requests\ListQuotesRequest;
use App\Http\Requests\StoreQuoteBatchRequest;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Resources\QuoteBatchResource;
use App\Http\Resources\QuoteListResource;
use App\Http\Resources\QuoteResource;
use App\Http\Resources\QuoteSummaryResource;
use App\Infrastructure\Persistence\Eloquent\QuoteModel;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response;

final class QuoteController extends Controller
{
    private const string DETAIL_RESPONSE = 'array{id: int, budget_id: string, client: string, event: string, event_lines: list<string>, currency: string, subtotal: float, total: float, exchange_rates: array<string, float>, items: list<array{id: int, description: string, description_lines: list<string>, category: string, quantity: int, unit_price: float, currency: string, line_total: float}>, breakdown: list<array{step: int, type: string, label: string, base: float, amount: float, running_total: float}>}';

    #[IgnoreResponse(200)]
    #[Response(status: 201, description: 'Quote calculated and persisted.', type: self::DETAIL_RESPONSE)]
    public function store(StoreQuoteRequest $request, StoreQuoteBatch $store): QuoteResource
    {
        $result = $store->execute([$request->validated()])[0];

        $resource = new QuoteResource($result);
        $resource->status = 201;

        return $resource;
    }

    #[IgnoreResponse(200)]
    #[Response(status: 201, description: 'Atomic batch persisted in input order.', type: 'array{imported: int, quotes: list<array{id: int, budget_id: string, currency: string, total: float}>}')]
    public function batch(StoreQuoteBatchRequest $request, StoreQuoteBatch $store): QuoteBatchResource
    {
        /** @var list<array<string, mixed>> $quotes */
        $quotes = $request->validated('quotes');
        $results = $store->execute($quotes);

        $resource = new QuoteBatchResource($results);
        $resource->status = 201;

        return $resource;
    }

    #[Response(type: 'array{data: list<array{id: int, budget_id: string, client: string, event: string, event_lines: list<string>, original_currency: string, original_total: float, currency: string, total: float|null}>, meta: array{page: int, per_page: int, total: int, last_page: int}}')]
    public function index(ListQuotesRequest $request): QuoteListResource
    {
        $validated = $request->validated();
        $page = (int) $validated['page'];
        $perPage = (int) $validated['per_page'];
        $query = QuoteModel::query()->select([
            'id', 'budget_id', 'client', 'event', 'currency', 'total', 'exchange_rates',
        ]);

        if (isset($validated['client']) && $validated['client'] !== '') {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $validated['client']);
            $query->whereRaw("client LIKE ? ESCAPE '!'", ["%{$escaped}%"]);
        }

        $total = (clone $query)->count();
        $quotes = $query->orderBy('id')->forPage($page, $perPage)->get();

        $resources = $quotes->map(fn (QuoteModel $quote) => new QuoteSummaryResource($quote, $validated['currency']));

        return (new QuoteListResource($resources))->additional(['meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ]]);
    }

    #[Response(type: self::DETAIL_RESPONSE)]
    #[Response(status: 404, type: 'array{message: string}')]
    public function show(int $id): QuoteResource
    {
        $quote = QuoteModel::query()->with(['items', 'steps'])->findOrFail($id);

        return new QuoteResource($quote);
    }
}
