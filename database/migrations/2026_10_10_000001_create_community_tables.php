<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 10: Review and Report, the consumer history and favorites,
 * and the cached transparency score on lots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 1000)->nullable();
            $table->timestamps();
            // One review per user per product.
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Product, Lot, Certification or EnvironmentalImpact.
            $table->morphs('reportable');
            $table->string('type', 40)->index();
            $table->string('description', 2000);
            $table->string('status', 20)->default('PENDING')->index();
            $table->string('admin_response', 2000)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lot_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->dateTime('viewed_at');
            $table->unique(['user_id', 'lot_id']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::table('lots', function (Blueprint $table) {
            $table->unsignedTinyInteger('trust_score')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropColumn('trust_score');
        });

        Schema::dropIfExists('favorites');
        Schema::dropIfExists('lot_views');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('reviews');
    }
};
