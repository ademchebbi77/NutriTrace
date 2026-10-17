<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Distribution - Certification: results of the local AI, kept so they are shown
 * instantly and survive an outage (or a training run) of the AI service. "data_version"
 * fingerprints the data the AI saw: when it changes, a new result is computed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Risk that a submitted certificate is not genuine.
        Schema::create('certification_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_id')->constrained()->cascadeOnDelete();
            $table->char('data_version', 64);
            $table->string('level', 10);
            // 0 = surely genuine, 1 = surely suspicious.
            $table->decimal('risk', 5, 4);
            $table->decimal('confidence', 5, 4);
            $table->json('reasons');
            $table->boolean('document_readable')->default(false);
            $table->text('explanation')->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamps();
            $table->unique(['certification_id', 'data_version']);
        });

        // Advice given to a distributor before accepting a lot.
        Schema::create('reception_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained()->cascadeOnDelete();
            $table->char('data_version', 64);
            $table->string('decision', 10);
            $table->decimal('confidence', 5, 4);
            $table->json('reasons');
            $table->text('explanation')->nullable();
            $table->string('transport_label', 40)->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamps();
            $table->unique(['distribution_id', 'data_version']);
        });

        // Answers of the distributor assistant, about that distributor's own stock and sales.
        Schema::create('distributor_ai_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('question', 500);
            $table->char('question_hash', 64);
            $table->char('data_version', 64);
            $table->text('answer');
            $table->string('model', 100)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'question_hash', 'data_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_ai_answers');
        Schema::dropIfExists('reception_checks');
        Schema::dropIfExists('certification_analyses');
    }
};
