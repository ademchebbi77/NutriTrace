<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('producer_id')->constrained('users')->cascadeOnDelete();
            $table->string('location_address')->nullable();
            $table->string('location_city', 100);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('production_date')->index();
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 10);
            $table->string('production_method', 30);
            // Keys: water_l, energy_kwh, fertilizer_kg, pesticide_kg, notes.
            $table->json('resources_used')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
