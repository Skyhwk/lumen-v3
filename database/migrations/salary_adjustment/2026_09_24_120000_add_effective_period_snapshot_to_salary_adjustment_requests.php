<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salary_adjustment_requests')) {
            return;
        }

        Schema::table('salary_adjustment_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_adjustment_requests', 'submitted_bulan_efektif')) {
                $table->char('submitted_bulan_efektif', 7)->nullable()->after('bulan_efektif');
            }
            if (!Schema::hasColumn('salary_adjustment_requests', 'hrd_final_bulan_efektif')) {
                $table->char('hrd_final_bulan_efektif', 7)->nullable()->after('submitted_bulan_efektif');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('salary_adjustment_requests')) {
            return;
        }

        Schema::table('salary_adjustment_requests', function (Blueprint $table) {
            $columns = ['submitted_bulan_efektif', 'hrd_final_bulan_efektif'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('salary_adjustment_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
