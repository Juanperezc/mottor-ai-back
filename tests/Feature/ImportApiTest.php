<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ImportBatchProcessed;
use App\Events\ImportCompleted;
use App\Infrastructure\Persistence\Eloquent\ImportModel;
use App\Jobs\ProcessImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\QuoteFixtures;
use Tests\TestCase;

final class ImportApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    /** A43 */
    public function it_enqueues_horizon_batches_and_exposes_status(): void
    {
        Bus::fake();

        $this->postJson('/imports', ['quotes' => [QuoteFixtures::official()]])
            ->assertAccepted()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('total_batches', 1);

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
        $this->getJson('/imports/1')->assertOk()->assertJsonPath('processed_batches', 0);
    }

    #[Test]
    /** BT-32 */
    public function it_accepts_a_multipart_dataset_and_returns_json_404(): void
    {
        Bus::fake();
        $file = UploadedFile::fake()->createWithContent(
            'dataset.json',
            json_encode(['quotes' => [QuoteFixtures::official()]], JSON_THROW_ON_ERROR),
        );

        $this->post('/imports', ['file' => $file], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('total_batches', 1);
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);

        $this->getJson('/imports/999999')->assertNotFound()->assertJsonStructure(['message']);
    }

    #[Test]
    /** BT-32 */
    public function an_import_cannot_exceed_ten_thousand_quotes(): void
    {
        $this->postJson('/imports', ['quotes' => array_fill(0, 10_001, [])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quotes']);

        $this->assertDatabaseCount('imports', 0);
        $this->assertDatabaseCount('quotes', 0);
    }

    #[Test]
    /** A43, BT-33, BT-34 */
    public function a_sync_import_processes_1200_quotes_in_three_batches_and_emits_exact_events(): void
    {
        Event::fake([ImportBatchProcessed::class, ImportCompleted::class]);
        $quotes = array_map(fn (int $index): array => $this->minimalQuote($index), range(1, 1200));

        $response = $this->postJson('/imports', ['quotes' => $quotes])
            ->assertAccepted()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('total_batches', 3)
            ->assertJsonPath('processed_batches', 3)
            ->assertJsonPath('imported', 1200)
            ->assertJsonPath('failed_batches', 0);

        $this->assertDatabaseCount('quotes', 1200);
        Event::assertDispatchedTimes(ImportBatchProcessed::class, 3);
        Event::assertDispatchedTimes(ImportCompleted::class, 1);
        Event::assertDispatched(ImportCompleted::class, function (ImportCompleted $event) use ($response): bool {
            self::assertSame('imports.'.$response->json('id'), $event->broadcastOn()[0]->name);
            self::assertSame('ImportCompleted', $event->broadcastAs());
            self::assertSame([
                'imported' => 1200,
                'failed_batches' => 0,
                'duration_ms' => $event->durationMs,
            ], $event->broadcastWith());

            return true;
        });
    }

    #[Test]
    /** A43, BT-33, BT-34 */
    public function one_failed_batch_is_atomic_while_the_other_batches_complete(): void
    {
        Event::fake([ImportBatchProcessed::class, ImportCompleted::class]);
        $import = ImportModel::query()->create([
            'status' => 'queued',
            'total_batches' => 3,
            'started_at' => now(),
        ]);
        $quotes = array_map(fn (int $index): array => $this->minimalQuote($index), range(1, 1200));
        $quotes[500]['rules'] = [['type' => 'unknown']];

        foreach (array_chunk($quotes, 500) as $index => $chunk) {
            app()->call([new ProcessImportBatch((int) $import->id, $index + 1, $chunk), 'handle']);
        }

        $this->assertDatabaseCount('quotes', 700);
        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => 'completed_with_errors',
            'processed_batches' => 3,
            'imported' => 700,
            'failed_batches' => 1,
        ]);
        Event::assertDispatchedTimes(ImportBatchProcessed::class, 3);
        Event::assertDispatched(ImportBatchProcessed::class, function (ImportBatchProcessed $event) use ($import): bool {
            if ($event->batch !== 2) {
                return false;
            }

            self::assertSame('imports.'.$import->id, $event->broadcastOn()[0]->name);
            self::assertSame('ImportBatchProcessed', $event->broadcastAs());
            self::assertSame('failed', $event->status);
            self::assertSame(0, $event->imported);
            self::assertArrayHasKey('quotes.0.rules.0.type', $event->errors);
            self::assertSame([
                'batch' => 2,
                'status' => 'failed',
                'imported' => 0,
                'errors' => $event->errors,
            ], $event->broadcastWith());

            return true;
        });
        Event::assertDispatchedTimes(ImportCompleted::class, 1);
    }

    #[Test]
    /** A43 */
    public function a_job_persists_its_batch_and_broadcasts_public_progress_events(): void
    {
        Event::fake([ImportBatchProcessed::class, ImportCompleted::class]);
        $import = ImportModel::query()->create([
            'status' => 'queued',
            'total_batches' => 1,
            'started_at' => now(),
        ]);

        $inserts = [];
        DB::listen(function ($query) use (&$inserts): void {
            if (str_starts_with(strtolower($query->sql), 'insert into')) {
                $inserts[] = $query->sql;
            }
        });

        app()->call([new ProcessImportBatch(
            (int) $import->id,
            1,
            QuoteFixtures::exact([QuoteFixtures::official(), QuoteFixtures::second()]),
        ), 'handle']);

        $this->assertDatabaseCount('quotes', 2);
        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => 'completed',
            'processed_batches' => 1,
            'imported' => 2,
        ]);
        foreach (['quotes', 'quote_items', 'quote_rules', 'quote_steps'] as $table) {
            self::assertCount(1, array_filter($inserts, fn (string $sql): bool => str_contains($sql, "`{$table}`")), $table);
        }
        Event::assertDispatched(ImportBatchProcessed::class);
        Event::assertDispatched(ImportCompleted::class);
    }

    /** @return array<string, mixed> */
    private function minimalQuote(int $index): array
    {
        return [
            'budget' => [
                'id' => "IMPORT-{$index}",
                'client' => 'Import test',
                'event' => 'Import test event',
                'items' => [[
                    'id' => 1,
                    'description' => 'Item',
                    'category' => 'test',
                    'quantity' => 1,
                    'unit_price' => 1,
                    'currency' => 'ARS',
                ]],
            ],
            'exchange_rates' => [],
            'currency' => 'ARS',
            'rules' => [],
        ];
    }
}
