<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Quotes\ExactJsonDecoder;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportResource;
use App\Infrastructure\Persistence\Eloquent\ImportModel;
use App\Jobs\ProcessImportBatch;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use JsonException;

final class ImportController extends Controller
{
    private const string STATUS_RESPONSE = 'array{id: int, status: string, total_batches: int, processed_batches: int, imported: int, failed_batches: int, duration_ms: int|null}';

    #[IgnoreResponse(200)]
    #[Response(status: 202, description: 'Import accepted and queued on Horizon.', type: self::STATUS_RESPONSE)]
    public function store(StoreImportRequest $request, ExactJsonDecoder $decoder): ImportResource
    {
        $quotes = $request->hasFile('file')
            ? $this->quotesFromFile($request->file('file')->getRealPath(), $decoder)
            : $request->validated('quotes');

        if (! is_array($quotes) || $quotes === [] || count($quotes) > 10_000) {
            throw ValidationException::withMessages([
                'quotes' => ['The import must contain between 1 and 10000 quotes.'],
            ]);
        }

        $chunks = array_chunk($quotes, 500);
        $import = ImportModel::query()->create([
            'status' => 'queued',
            'total_batches' => count($chunks),
            'started_at' => now(),
        ]);
        $jobs = array_map(
            fn (array $chunk, int $index) => new ProcessImportBatch((int) $import->id, $index + 1, $chunk),
            $chunks,
            array_keys($chunks),
        );
        $batch = Bus::batch($jobs)->name("import-{$import->id}")->dispatch();
        $import->update(['batch_id' => $batch->id]);

        $resource = new ImportResource($import->fresh());
        $resource->status = 202;

        return $resource;
    }

    #[Response(type: self::STATUS_RESPONSE)]
    #[Response(status: 404, type: 'array{message: string}')]
    public function show(int $id): ImportResource
    {
        return new ImportResource(ImportModel::query()->findOrFail($id));
    }

    /** @return list<array<string, mixed>> */
    private function quotesFromFile(string $path, ExactJsonDecoder $decoder): array
    {
        try {
            $decoded = $decoder->decode(file_get_contents($path));
        } catch (JsonException) {
            throw ValidationException::withMessages(['file' => ['The file must contain valid JSON.']]);
        }

        if (! is_array($decoded) || ! isset($decoded['quotes']) || ! is_array($decoded['quotes'])) {
            throw ValidationException::withMessages(['file' => ['The JSON must contain a quotes array.']]);
        }

        return $decoded['quotes'];
    }
}
