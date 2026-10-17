<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Transformation - Lots: results of the local AI, kept so they are shown instantly
 * and survive an outage (or a training run) of the AI service.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Check of a transformation: on the form before saving (transformer, no transformation yet),
        // then attached to it; and the admin's review of saved transformations.
        Schema::create('transformation_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transformation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audience', 12);
            $table->char('data_version', 64);
            $table->string('label', 30);
            $table->boolean('is_anomaly');
            // 0 = normal, 1 = surely anomalous.
            $table->decimal('risk', 5, 4);
            $table->decimal('confidence', 5, 4);
            // Expected output and resources for the inputs.
            $table->json('expected');
            $table->json('reasons');
            $table->text('explanation')->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamps();
            $table->unique(['data_version', 'audience']);
        });

        // Date limit of a lot compared with the shelf life the AI expects for its product.
        Schema::create('lot_expiry_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->char('data_version', 64);
            $table->string('label', 12);
            $table->unsignedInteger('predicted_days');
            $table->unsignedInteger('low_days');
            $table->unsignedInteger('high_days');
            $table->integer('actual_days')->nullable();
            $table->date('suggested_date')->nullable();
            $table->json('reasons');
            $table->string('model', 100)->nullable();
            $table->timestamps();
            $table->unique(['lot_id', 'data_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_expiry_checks');
        Schema::dropIfExists('transformation_checks');
    }
};
