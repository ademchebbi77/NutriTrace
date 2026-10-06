<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 9: append-only traceability events, chained by SHA-256 hashes per lot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traceability_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 20)->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->dateTime('occurred_at')->index();
            $table->string('description', 500);
            // Record the event was generated from (Production, Transformation, Transport, Distribution).
            $table->nullableMorphs('source');
            $table->json('metadata')->nullable();
            $table->char('previous_hash', 64);
            $table->char('hash', 64)->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traceability_events');
    }
};
