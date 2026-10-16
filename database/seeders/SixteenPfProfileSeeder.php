<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uraian kutub 16PF (Grafik Psikogram) + label pendek ke database.
 *
 *   python scripts/extract_16pf_for_seed.py
 *   php artisan db:seed --class=SixteenPfProfileSeeder
 */
class SixteenPfProfileSeeder extends Seeder
{
    private const FACTOR_ORDER = ['A', 'B', 'C', 'E', 'F', 'G', 'H', 'I', 'L', 'M', 'N', 'O', 'Q1', 'Q2', 'Q3', 'Q4'];

    public function run(): void
    {
        if (!Schema::hasTable('psychometric_16pf_profiles')) {
            if ($this->command) {
                $this->command->warn('SixteenPfProfileSeeder: tabel psychometric_16pf_profiles belum ada — jalankan migrate.');
            }

            return;
        }

        $path = storage_path('app/psychometric/16pf_bundle.json');
        if (!is_file($path)) {
            if ($this->command) {
                $this->command->error('File tidak ditemukan: ' . $path);
            }

            return;
        }

        $bundle = json_decode((string) file_get_contents($path), true);
        $profile = is_array($bundle['psychogram_profile'] ?? null) ? $bundle['psychogram_profile'] : [];
        $labels = is_array($bundle['psychogram_labels'] ?? null) ? $bundle['psychogram_labels'] : [];

        if ($profile === []) {
            if ($this->command) {
                $this->command->error('Bundle tidak berisi psychogram_profile.');
            }

            return;
        }

        $now = date('Y-m-d H:i:s');
        $rows = [];

        foreach (self::FACTOR_ORDER as $index => $factor) {
            if (!isset($profile[$factor]) || !is_array($profile[$factor])) {
                continue;
            }
            $pole = $profile[$factor];
            $short = is_array($labels[$factor] ?? null) ? $labels[$factor] : [];

            $rows[] = [
                'factor' => $factor,
                'low_text' => trim((string) ($pole['low'] ?? '')),
                'high_text' => trim((string) ($pole['high'] ?? '')),
                'short_low' => trim((string) ($short['low'] ?? '')) ?: null,
                'short_high' => trim((string) ($short['high'] ?? '')) ?: null,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::connection('mysql')->transaction(function () use ($rows) {
            DB::table('psychometric_16pf_profiles')->delete();
            DB::table('psychometric_16pf_profiles')->insert($rows);
        });

        if ($this->command) {
            $this->command->info('SixteenPfProfileSeeder: ' . count($rows) . ' faktor → psychometric_16pf_profiles.');
        }
    }
}
