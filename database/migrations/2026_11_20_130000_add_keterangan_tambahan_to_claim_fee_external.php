<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claim_fee_external')) {
            return;
        }

        if (!Schema::hasColumn('claim_fee_external', 'keterangan_tambahan')) {
            Schema::table('claim_fee_external', function (Blueprint $table) {
                $table->text('keterangan_tambahan')->nullable()->after('metode_pembayaran');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('claim_fee_external')
            && Schema::hasColumn('claim_fee_external', 'keterangan_tambahan')) {
            Schema::table('claim_fee_external', function (Blueprint $table) {
                $table->dropColumn('keterangan_tambahan');
            });
        }
    }
};