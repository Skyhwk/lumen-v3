<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefactorMonitorKeterlambatanToLogAnalisa extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monitor_keterlambatan_analisa')) {
            if (Schema::hasColumn('monitor_keterlambatan_analisa', 'analis_input')
                && !Schema::hasColumn('monitor_keterlambatan_analisa', 'input_analisa')) {
                DB::statement('ALTER TABLE monitor_keterlambatan_analisa CHANGE analis_input input_analisa DATETIME NULL');
            }
        }

        Schema::dropIfExists('monitor_persentase_keterlambatan_analisa');
    }

    public function down(): void
    {
        if (Schema::hasTable('monitor_keterlambatan_analisa')) {
            if (Schema::hasColumn('monitor_keterlambatan_analisa', 'input_analisa')
                && !Schema::hasColumn('monitor_keterlambatan_analisa', 'analis_input')) {
                DB::statement('ALTER TABLE monitor_keterlambatan_analisa CHANGE input_analisa analis_input DATETIME NULL');
            }
        }
    }
}
