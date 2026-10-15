<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('candidate_data_offers')) {
            return;
        }

        if (!Schema::hasColumn('candidate_data_offers', 'tunjangan_kerja')) {
            Schema::table('candidate_data_offers', function (Blueprint $table) {
                $table->decimal('tunjangan_kerja', 15, 2)->nullable()->after('gaji_pokok');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('candidate_data_offers')) {
            return;
        }

        if (Schema::hasColumn('candidate_data_offers', 'tunjangan_kerja')) {
            Schema::table('candidate_data_offers', function (Blueprint $table) {
                $table->dropColumn('tunjangan_kerja');
            });
        }
    }
};
