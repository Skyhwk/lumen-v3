<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFtcVerifierToMonitorKeterlambatanAnalisaTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monitor_keterlambatan_analisa') && !Schema::hasColumn('monitor_keterlambatan_analisa', 'ftc_verifier')) {
            Schema::table('monitor_keterlambatan_analisa', function (Blueprint $table) {
                $table->dateTime('ftc_verifier')->nullable()->after('ftc_laboratory');
                $table->index('ftc_verifier', 'idx_ftc_verifier');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('monitor_keterlambatan_analisa', 'ftc_verifier')) {
            Schema::table('monitor_keterlambatan_analisa', function (Blueprint $table) {
                $table->dropIndex('idx_ftc_verifier');
                $table->dropColumn('ftc_verifier');
            });
        }
    }
}
