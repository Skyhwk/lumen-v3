<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Isi tabel norma STEN 16PF dari hasil ekstraksi Excel.
 *
 *   python scripts/extract_16pf_for_seed.py
 *   php artisan migrate
 *   php artisan db:seed --class=SixteenPfNormSeeder
 */
class SixteenPfNormSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('psychometric_16pf_norms')) {
            if ($this->command) {
                $this->command->warn('SixteenPfNormSeeder: tabel psychometric_16pf_norms belum ada — jalankan migrate.');
            }

            return;
        }

        $path = storage_path('app/psychometric/16pf_bundle.json');
        if (!is_file($path)) {
            if ($this->command) {
                $this->command->error('File tidak ditemukan: ' . $path . ' — jalankan scripts/extract_16pf_for_seed.py');
            }

            return;
        }

        $bundle = json_decode((string) file_get_contents($path), true);
        $norms = is_array($bundle['norms'] ?? null) ? $bundle['norms'] : [];

        if ($norms === []) {
            if ($this->command) {
                $this->command->error('Bundle tidak berisi norms — regenerate dengan extract script.');
            }

            return;
        }

        $now = date('Y-m-d H:i:s');
        $rows = [];

        foreach ($norms as $groupKey => $factors) {
            if (!is_array($factors)) {
                continue;
            }
            foreach ($factors as $factor => $table) {
                if (!is_array($table) || $table === []) {
                    continue;
                }
                $factor = strtoupper(trim((string) $factor));
                foreach ($table as $rawKey => $sten) {
                    $rows[] = [
                        'norm_group' => (string) $groupKey,
                        'factor' => $factor,
                        'raw_score' => (int) $rawKey,
                        'sten' => max(1, min(10, (int) $sten)),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        DB::connection('mysql')->transaction(function () use ($rows) {
            DB::table('psychometric_16pf_norms')->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('psychometric_16pf_norms')->insert($chunk);
            }
        });

        if ($this->command) {
            $groups = count($norms);
            $this->command->info('SixteenPfNormSeeder: ' . count($rows) . ' baris norma (' . $groups . ' grup) dari bundle → psychometric_16pf_norms.');
        }
    }
}
