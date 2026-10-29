<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Json\ExactJsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class QuoteListResource extends ResourceCollection
{
    public function toArray($request): array
    {
        return ['data' => $this->collection->map(fn (QuoteSummaryResource $resource) => $resource->resolve($request))->all(), 'meta' => $this->additional['meta']];
    }

    public function toResponse($request): Response
    {
        return new ExactJsonResponse($this->resolve($request));
    }
}
