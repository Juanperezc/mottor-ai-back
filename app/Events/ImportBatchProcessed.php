<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ImportBatchProcessed implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /** @param array<string, list<string>> $errors */
    public function __construct(
        public readonly int $importId,
        public readonly int $batch,
        public readonly string $status,
        public readonly int $imported,
        public readonly array $errors,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("imports.{$this->importId}")];
    }

    public function broadcastAs(): string
    {
        return 'ImportBatchProcessed';
    }

    public function broadcastWith(): array
    {
        return [
            'batch' => $this->batch,
            'status' => $this->status,
            'imported' => $this->imported,
            'errors' => $this->errors,
        ];
    }
}
