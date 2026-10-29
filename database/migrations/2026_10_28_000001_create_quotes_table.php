<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_id')->nullable()->constrained('imports')->nullOnDelete();
            $table->string('budget_id', 64)->index();
            $table->string('client')->index();
            $table->text('event');
            $table->char('currency', 3);
            $table->decimal('subtotal', 20, 2);
            $table->decimal('total', 20, 2);
            $table->json('exchange_rates');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
