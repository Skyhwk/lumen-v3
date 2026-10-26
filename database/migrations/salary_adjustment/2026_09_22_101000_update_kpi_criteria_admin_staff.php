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
            1 => [
                'criteria_name' => 'Kualitas Pekerjaan',
                'weight_pct' => 20,
                'indicator_text' => 'Akurasi, ketelitian, dan kelengkapan hasil kerja administratif',
            ],
            2 => [
                'criteria_name' => 'Ketepatan Waktu',
                'weight_pct' => 20,
                'indicator_text' => 'Kemampuan menyelesaikan tugas dan dokumen sesuai deadline',
            ],
            3 => [
                'criteria_name' => 'Disiplin & Kehadiran',
                'weight_pct' => 20,
                'indicator_text' => 'Kedisiplinan, kehadiran, dan kepatuhan jam kerja',
            ],
            4 => [
                'criteria_name' => 'Inisiatif & Proaktif',
                'weight_pct' => 20,
                'indicator_text' => 'Kemampuan mengambil inisiatif tanpa menunggu diperintah',
            ],
            5 => [
                'criteria_name' => 'Kolaborasi & Komunikasi',
                'weight_pct' => 20,
                'indicator_text' => 'Kerjasama tim dan komunikasi dengan rekan kerja serta atasan',
            ],
        ];

        foreach ($criteria as $criteriaNo => $payload) {
            DB::connection('mysql')
                ->table('salary_adjustment_kpi_criteria')
                ->where('criteria_no', $criteriaNo)
                ->update([
                    'criteria_name' => $payload['criteria_name'],
                    'weight_pct' => $payload['weight_pct'],
                    'indicator_text' => $payload['indicator_text'],
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
        if (!Schema::hasTable('salary_adjustment_kpi_criteria')) {
            return;
        }

        $legacy = [
            1 => ['Kualitas Kode & Dokumentasi', 20, 'Clean code, standar coding, dokumentasi lengkap'],
            2 => ['Ketepatan Waktu Penyelesaian', 20, 'Persentase task/proyek selesai tepat waktu'],
            3 => ['Pemecahan Masalah & Debugging', 15, 'Kecepatan dan efektivitas perbaikan bug/error'],
            4 => ['Inovasi & Optimalisasi Sistem', 10, 'Fitur baru atau optimasi performa aplikasi'],
            5 => ['Kolaborasi Tim', 10, 'Kerjasama dengan design, QA, dan project management'],
            6 => ['Kepatuhan pada Proses & SOP', 10, 'Git flow, testing, code review'],
            7 => ['Kepuasan Stakeholder', 15, 'Feedback positif dari manajemen dan user'],
        ];

        foreach ($legacy as $criteriaNo => [$name, $weight, $indicator]) {
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
    }
};
