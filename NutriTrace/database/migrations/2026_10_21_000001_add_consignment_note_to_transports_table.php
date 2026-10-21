<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Electronic consignment note ("lettre de voiture") of a transport: a number, a public
 * token for its QR code, and the hand-over signed by the recipient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transports', function (Blueprint $table) {
            $table->string('consignment_number', 20)->nullable()->unique();
            $table->string('consignment_token', 40)->nullable()->unique();
            $table->decimal('handover_quantity', 12, 3)->nullable();
            $table->string('handover_comment', 500)->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->foreignId('handed_over_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Existing transports get their note too.
        DB::table('transports')->whereNull('consignment_token')->orderBy('id')->each(function (object $transport) {
            DB::table('transports')->where('id', $transport->id)->update([
                'consignment_number' => sprintf('LV-%06d', $transport->id),
                'consignment_token' => Str::random(40),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('transports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handed_over_by');
            $table->dropColumn(['consignment_number', 'consignment_token', 'handover_quantity', 'handover_comment', 'handed_over_at']);
        });
    }
};
