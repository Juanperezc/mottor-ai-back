<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Quotes\StoreQuoteBatch;
use App\Domain\Exception\DomainValidationException;
use App\Events\ImportBatchProcessed;
use App\Events\ImportCompleted;
use App\Http\Requests\QuoteValidationRules;
use App\Infrastructure\Persistence\Eloquent\ImportModel;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ProcessImportBatch implements ShouldQueue
{
    use Batchable, Queueable;

    /** @param list<array<string, mixed>> $quotes */
    public function __construct(
        public readonly int $importId,
        public readonly int $batchNumber,
        public readonly array $quotes,
    ) {}

    public function handle(StoreQuoteBatch $store): void
    {
        $imported = 0;
        $errors = [];
        $status = 'processed';

        try {
            Validator::make(
                ['quotes' => $this->quotes],
                [
                    'quotes' => ['required', 'array', 'min:1', 'max:500'],
                    'quotes.*' => ['required', 'array'],
                    ...QuoteValidationRules::make('quotes.*'),
                ],
            )->validate();
            $imported = count($store->execute($this->quotes, $this->importId));
        } catch (ValidationException $exception) {
            $status = 'failed';
            $errors = $exception->errors();
        } catch (DomainValidationException $exception) {
            $status = 'failed';
            $errors = $exception->errors;
        } catch (Throwable $exception) {
            report($exception);
            $status = 'failed';
            $errors = ['batch' => ['The batch could not be persisted.']];
        }

        $completed = DB::transaction(function () use ($imported, $status): ?ImportModel {
            $import = ImportModel::query()->lockForUpdate()->findOrFail($this->importId);
            $import->processed_batches++;
            $import->imported += $imported;
            if ($status === 'failed') {
                $import->failed_batches++;
            }

            if ($import->processed_batches === $import->total_batches) {
                $import->status = $import->failed_batches > 0 ? 'completed_with_errors' : 'completed';
                $import->finished_at = now();
                $import->duration_ms = (int) $import->started_at->diffInMilliseconds($import->finished_at);
            } else {
                $import->status = 'processing';
            }

            $import->save();

            return $import->finished_at === null ? null : $import;
        });

        ImportBatchProcessed::dispatch(
            $this->importId,
            $this->batchNumber,
            $status,
            $imported,
            $errors,
        );

        if ($completed !== null) {
            ImportCompleted::dispatch(
                $this->importId,
                (int) $completed->imported,
                (int) $completed->failed_batches,
                (int) $completed->duration_ms,
            );
        }
    }
}
