<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salary_adjustment_kpi_criteria')) {
            return;
        }

        $criteria = [
            1 => ['Kualitas Pekerjaan', 20, 'Akurasi, ketelitian, dan kelengkapan hasil kerja administratif'],
            2 => ['Ketepatan Waktu', 20, 'Kemampuan menyelesaikan tugas dan dokumen sesuai deadline'],
            3 => ['Disiplin & Kehadiran', 20, 'Kedisiplinan, kehadiran, dan kepatuhan jam kerja'],
            4 => ['Inisiatif & Proaktif', 20, 'Kemampuan mengambil inisiatif tanpa menunggu diperintah'],
            5 => ['Kolaborasi & Komunikasi', 20, 'Kerjasama tim dan komunikasi dengan rekan kerja serta atasan'],
        ];

        foreach ($criteria as $criteriaNo => [$name, $weight, $indicator]) {
            DB::connection('mysql')
                ->table('salary_adjustment_kpi_criteria')
                ->where('criteria_no', $criteriaNo)
                ->update([
                    'criteria_name' => $name,
                    'weight_pct' => $weight,
                    'indicator_text' => $indicator,
                    'is_active' => true,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }

        DB::connection('mysql')
            ->table('salary_adjustment_kpi_criteria')
            ->whereIn('criteria_no', [6, 7])
            ->update([
                'is_active' => false,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function down(): void
    {
        // Tidak di-reverse.
    }
};
