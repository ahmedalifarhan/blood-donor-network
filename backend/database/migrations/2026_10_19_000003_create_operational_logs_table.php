<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only operational log (data-model.md §3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('level', 10);
            $table->string('event', 100);
            $table->string('actor_type', 20);
            $table->string('actor_ref', 255)->nullable();
            // Text, not JSON: masking may truncate context to 5,000 chars with a "…" marker.
            $table->text('context')->nullable();
            $table->string('ip', 64)->nullable();
            $table->timestampTz('created_at', precision: 0)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_logs');
    }
};
