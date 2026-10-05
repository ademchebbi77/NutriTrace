<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modules 7 and 8: Certification and EnvironmentalImpact, plus admin-editable settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            // Attached to a Product or to a Lot.
            $table->morphs('certifiable');
            $table->string('name');
            $table->string('type', 30)->index();
            $table->string('issuing_organization');
            $table->string('certificate_number', 100)->nullable();
            $table->date('issue_date');
            $table->date('expiration_date')->nullable()->index();
            // Proof stored on the private disk and served through authorized routes only.
            $table->string('document_path')->nullable();
            $table->string('status', 20)->default('PENDING')->index();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('environmental_impacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->unique()->constrained()->cascadeOnDelete();

            // Each indicator keeps the origin of its value: MEASURED, PROVIDED or CALCULATED.
            foreach (['co2_kg', 'water_l', 'energy_kwh', 'waste_kg', 'transport_co2_kg', 'packaging_co2_kg'] as $indicator) {
                $table->decimal($indicator, 14, 3)->nullable();
                $table->string($indicator.'_source', 12)->nullable();
            }

            $table->decimal('food_miles_km', 10, 2)->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->char('grade', 1)->nullable()->index();
            // Values declared by the holder: {indicator: {value, source}}.
            $table->json('declared')->nullable();
            $table->foreignId('declared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('calculated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('environmental_impacts');
        Schema::dropIfExists('certifications');
    }
};
