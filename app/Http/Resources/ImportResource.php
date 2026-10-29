<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\ImportModel;

/** @mixin ImportModel */
final class ImportResource extends ExactJsonResource
{
    public function toArray($request): array
    {
        /** @var ImportModel $import */
        $import = $this->resource;

        return [
            'id' => (int) $import->id, 'status' => $import->status, 'total_batches' => (int) $import->total_batches,
            'processed_batches' => (int) $import->processed_batches, 'imported' => (int) $import->imported,
            'failed_batches' => (int) $import->failed_batches,
            'duration_ms' => $import->duration_ms === null ? null : (int) $import->duration_ms,
        ];
    }
}
