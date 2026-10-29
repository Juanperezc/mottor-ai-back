<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Json\DecimalNumber;

final class QuoteBatchResource extends ExactJsonResource
{
    public function toArray($request): array
    {
        $results = $this->resource;

        return [
            'imported' => count($results),
            'quotes' => array_map(fn ($result): array => [
                'id' => $result->id, 'budget_id' => $result->quote->budgetId,
                'currency' => $result->quote->currency, 'total' => DecimalNumber::money($result->quote->total->toString()),
            ], $results),
        ];
    }
}
