<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sallary_offer')) {
            return;
        }

        if (Schema::hasColumn('sallary_offer', 'tunjangan_gaji_user')) {
            return;
        }

        Schema::table('sallary_offer', function (Blueprint $table) {
            $table->decimal('tunjangan_gaji_user', 15, 2)->nullable()->after('sallary_offer_user');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sallary_offer')) {
            return;
        }

        if (!Schema::hasColumn('sallary_offer', 'tunjangan_gaji_user')) {
            return;
        }

        Schema::table('sallary_offer', function (Blueprint $table) {
            $table->dropColumn('tunjangan_gaji_user');
        });
    }
};
