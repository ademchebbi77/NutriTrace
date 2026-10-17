<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Transport - Traçabilité: answers of the local AI assistant and anomaly
 * analyses of transports, kept so they are shown instantly and survive an outage
 * of the AI service. "data_version" fingerprints the data the AI saw: when the
 * data changes, a new answer is computed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->string('question', 500);
            $table->char('question_hash', 64);
            $table->char('data_version', 64);
            $table->text('answer');
            $table->string('model', 100)->nullable();
            $table->timestamps();
            $table->unique(['lot_id', 'question_hash', 'data_version']);
        });

        Schema::create('transport_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_id')->constrained()->cascadeOnDelete();
            $table->char('data_version', 64);
            $table->boolean('is_anomaly');
            $table->string('label', 40);
            // Probability of the predicted label, 0 to 1.
            $table->decimal('confidence', 5, 4);
            $table->json('reasons');
            $table->text('explanation')->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamps();
            $table->unique(['transport_id', 'data_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_analyses');
        Schema::dropIfExists('ai_answers');
    }
};
