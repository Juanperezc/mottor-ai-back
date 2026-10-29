<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->unsignedInteger('source_id');
            $table->unsignedSmallInteger('position');
            $table->string('description');
            $table->string('category', 64);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 20, 6);
            $table->char('currency', 3);
            $table->decimal('line_total', 20, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
