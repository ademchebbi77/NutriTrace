<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->string('lot_number', 20)->unique();
            // Random token used in the public trace URL and QR code.
            $table->string('public_token', 40)->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Null for lots that come out of a transformation.
            $table->foreignId('production_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_holder_id')->constrained('users')->restrictOnDelete();
            $table->decimal('initial_quantity', 12, 2);
            // Remaining quantity: decreases when the lot is consumed by a transformation.
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 10);
            $table->date('production_date');
            $table->date('expiration_date')->nullable();
            $table->string('status', 20)->default('created')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
