<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 32)->default('queued');
            $table->string('batch_id')->nullable();
            $table->unsignedInteger('total_batches')->default(0);
            $table->unsignedInteger('processed_batches')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('failed_batches')->default(0);
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
