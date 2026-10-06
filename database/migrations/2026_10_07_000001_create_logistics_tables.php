<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modules 4, 5 and 6: Transformation, Transport, Distribution,
 * plus the hand-over of a lot to a transformer (lot_transfers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipper_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin_label');
            $table->decimal('origin_latitude', 10, 7)->nullable();
            $table->decimal('origin_longitude', 10, 7)->nullable();
            $table->string('destination_label');
            $table->decimal('destination_latitude', 10, 7)->nullable();
            $table->decimal('destination_longitude', 10, 7)->nullable();
            $table->string('transport_type', 20);
            // Haversine distance when both ends have coordinates, otherwise entered by hand.
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->dateTime('departure_date');
            $table->dateTime('arrival_date')->nullable();
            $table->decimal('quantity_transported', 12, 2);
            $table->string('unit', 10);
            $table->string('status', 20)->default('in_transit')->index();
            $table->timestamps();
        });

        Schema::create('lot_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('transport_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->string('note', 500)->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transformations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transformer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('output_lot_id')->unique()->constrained('lots')->cascadeOnDelete();
            $table->string('location_label');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('input_quantity', 12, 2);
            $table->string('input_unit', 10);
            $table->decimal('output_quantity', 12, 2);
            $table->date('transformation_date')->index();
            $table->text('process_description');
            $table->decimal('energy_used_kwh', 12, 2)->nullable();
            $table->decimal('water_used_l', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('transformation_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transformation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_used', 12, 2);
            $table->timestamps();
            $table->unique(['transformation_id', 'lot_id']);
        });

        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distributor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('transport_id')->nullable()->constrained()->nullOnDelete();
            // Store or region where the lot is sold.
            $table->string('destination');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('quantity', 12, 2);
            $table->date('distribution_date');
            $table->date('reception_date')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributions');
        Schema::dropIfExists('transformation_inputs');
        Schema::dropIfExists('transformations');
        Schema::dropIfExists('lot_transfers');
        Schema::dropIfExists('transports');
    }
};
