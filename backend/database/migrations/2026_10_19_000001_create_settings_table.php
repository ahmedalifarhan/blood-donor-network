<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stored setting overrides (data-model.md §1). Definitions and defaults
 * live in code; this table holds overrides only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('key', 100)->unique();
            // Raw text: invalid overrides must still be readable so they fall back to the default.
            $table->text('value');
            $table->timestampTz('updated_at', precision: 0);
            $table->string('updated_by', 100);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
