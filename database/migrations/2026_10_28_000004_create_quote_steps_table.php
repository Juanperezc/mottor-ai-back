<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->unsignedSmallInteger('step');
            $table->string('type', 64);
            $table->string('label');
            $table->decimal('base', 20, 2);
            $table->decimal('amount', 20, 2);
            $table->decimal('running_total', 20, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_steps');
    }
};
