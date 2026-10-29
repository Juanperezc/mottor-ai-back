<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ImportCompleted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $importId,
        public readonly int $imported,
        public readonly int $failedBatches,
        public readonly int $durationMs,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("imports.{$this->importId}")];
    }

    public function broadcastAs(): string
    {
        return 'ImportCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'imported' => $this->imported,
            'failed_batches' => $this->failedBatches,
            'duration_ms' => $this->durationMs,
        ];
    }
}
