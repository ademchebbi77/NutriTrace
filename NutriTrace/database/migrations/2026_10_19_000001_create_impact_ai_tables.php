<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Impact environnemental - Avis et signalements: moderation of reviews by the
 * local AI (a flagged review waits for the admin), and AI checks and advice on the
 * environmental figures of the lots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // "published" (default: every existing review stays visible), "pending" or "rejected".
            $table->string('moderation_status', 12)->default('published')->index()->after('comment');
            $table->string('moderation_label', 20)->nullable()->after('moderation_status');
            $table->decimal('moderation_confidence', 5, 4)->nullable()->after('moderation_label');
            $table->json('moderation_reasons')->nullable()->after('moderation_confidence');
            $table->text('moderation_explanation')->nullable()->after('moderation_reasons');
            $table->foreignId('moderated_by')->nullable()->after('moderation_explanation')->constrained('users')->nullOnDelete();
            $table->dateTime('moderated_at')->nullable()->after('moderated_by');
        });

        // Check of the figures declared for a lot, before saving (attached to the lot).
        Schema::create('impact_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('data_version', 64);
            $table->string('label', 20);
            $table->boolean('is_anomaly');
            $table->decimal('confidence', 5, 4);
            // One verdict per declared indicator.
            $table->json('findings');
            $table->json('reasons');
            $table->text('explanation')->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamps();
            $table->unique(['lot_id', 'data_version']);
        });

        // Improvement advice for a lot's environmental grade.
        Schema::create('impact_advice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->char('data_version', 64);
            $table->char('grade', 1)->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->char('target_grade', 1)->nullable();
            $table->json('levers');
            $table->text('explanation')->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamps();
            $table->unique(['lot_id', 'data_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_advice');
        Schema::dropIfExists('impact_checks');
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['moderation_status', 'moderation_label', 'moderation_confidence', 'moderation_reasons', 'moderation_explanation', 'moderated_at']);
        });
    }
};
