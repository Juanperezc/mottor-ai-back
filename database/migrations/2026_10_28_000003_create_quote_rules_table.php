<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type', 64);
            $table->json('configuration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_rules');
    }
};
