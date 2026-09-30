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
            $columns = [
                'hrd_final_adjustment_gaji_pokok' => 'decimal:15,2',
                'hrd_final_adjustment_tunjangan' => 'decimal:15,2',
                'hrd_final_requested_gaji_pokok' => 'decimal:15,2',
                'hrd_final_requested_tunjangan_kerja' => 'decimal:15,2',
                'finance_final_adjustment_notes' => 'text',
                'finance_return_reason' => 'text',
                'hrd_appeal_notes' => 'text',
            ];

            foreach ($columns as $column => $type) {
                if (Schema::hasColumn('salary_adjustment_requests', $column)) {
                    continue;
                }

                if (str_starts_with($type, 'decimal')) {
                    $table->decimal($column, 15, 2)->nullable();
                } else {
                    $table->text($column)->nullable();
                }
            }
        });

        DB::connection('mysql')->table('salary_adjustment_requests')
            ->whereNull('hrd_final_adjustment_gaji_pokok')
            ->whereNotNull('final_eval_approved_at')
            ->update([
                'hrd_final_adjustment_gaji_pokok' => DB::raw('adjustment_gaji_pokok'),
                'hrd_final_adjustment_tunjangan' => DB::raw('adjustment_tunjangan'),
                'hrd_final_requested_gaji_pokok' => DB::raw('requested_gaji_pokok'),
                'hrd_final_requested_tunjangan_kerja' => DB::raw('requested_tunjangan_kerja'),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('salary_adjustment_requests')) {
            return;
        }

        Schema::table('salary_adjustment_requests', function (Blueprint $table) {
            foreach ([
                'hrd_final_adjustment_gaji_pokok',
                'hrd_final_adjustment_tunjangan',
                'hrd_final_requested_gaji_pokok',
                'hrd_final_requested_tunjangan_kerja',
                'finance_final_adjustment_notes',
                'finance_return_reason',
                'hrd_appeal_notes',
            ] as $column) {
                if (Schema::hasColumn('salary_adjustment_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
