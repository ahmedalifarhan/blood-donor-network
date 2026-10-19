<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per registered scheduler step (data-model.md §2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduler_steps', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('step_name', 100)->unique();
            // Period key of the last completed run, e.g. 2026-10-19T10:15Z.
            $table->string('last_period', 32)->nullable();
            $table->timestampTz('last_run_at', precision: 0)->nullable();
            $table->string('last_outcome', 10)->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestampTz('last_success_at', precision: 0)->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_steps');
    }
};
