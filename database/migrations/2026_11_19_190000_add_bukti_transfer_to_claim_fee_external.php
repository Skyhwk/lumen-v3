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

        Schema::table('claim_fee_external', function (Blueprint $table) {
            if (!Schema::hasColumn('claim_fee_external', 'bukti_transfer')) {
                $table->string('bukti_transfer', 255)->nullable()->after('filename');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('claim_fee_external')) {
            return;
        }

        Schema::table('claim_fee_external', function (Blueprint $table) {
            if (Schema::hasColumn('claim_fee_external', 'bukti_transfer')) {
                $table->dropColumn('bukti_transfer');
            }
        });
    }
};