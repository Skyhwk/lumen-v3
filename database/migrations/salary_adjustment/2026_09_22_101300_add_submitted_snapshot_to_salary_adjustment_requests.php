<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salary_adjustment_requests')) {
            return;
        }

        Schema::table('salary_adjustment_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_adjustment_requests', 'submitted_adjustment_gaji_pokok')) {
                $table->decimal('submitted_adjustment_gaji_pokok', 15, 2)->nullable()->after('requested_tunjangan_kerja');
            }
            if (!Schema::hasColumn('salary_adjustment_requests', 'submitted_adjustment_tunjangan')) {
                $table->decimal('submitted_adjustment_tunjangan', 15, 2)->nullable()->after('submitted_adjustment_gaji_pokok');
            }
            if (!Schema::hasColumn('salary_adjustment_requests', 'submitted_requested_gaji_pokok')) {
                $table->decimal('submitted_requested_gaji_pokok', 15, 2)->nullable()->after('submitted_adjustment_tunjangan');
            }
            if (!Schema::hasColumn('salary_adjustment_requests', 'submitted_requested_tunjangan_kerja')) {
                $table->decimal('submitted_requested_tunjangan_kerja', 15, 2)->nullable()->after('submitted_requested_gaji_pokok');
            }
            if (!Schema::hasColumn('salary_adjustment_requests', 'hrd_final_adjustment_notes')) {
                $table->text('hrd_final_adjustment_notes')->nullable()->after('submitted_requested_tunjangan_kerja');
            }
        });

        DB::connection('mysql')->table('salary_adjustment_requests')
            ->whereNull('submitted_adjustment_gaji_pokok')
            ->update([
                'submitted_adjustment_gaji_pokok' => DB::raw('adjustment_gaji_pokok'),
                'submitted_adjustment_tunjangan' => DB::raw('adjustment_tunjangan'),
                'submitted_requested_gaji_pokok' => DB::raw('requested_gaji_pokok'),
                'submitted_requested_tunjangan_kerja' => DB::raw('requested_tunjangan_kerja'),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('salary_adjustment_requests')) {
            return;
        }

        Schema::table('salary_adjustment_requests', function (Blueprint $table) {
            $columns = [
                'submitted_adjustment_gaji_pokok',
                'submitted_adjustment_tunjangan',
                'submitted_requested_gaji_pokok',
                'submitted_requested_tunjangan_kerja',
                'hrd_final_adjustment_notes',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('salary_adjustment_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
