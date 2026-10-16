<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed modul 16PF untuk assessment penyesuaian karyawan.
 *
 *   php artisan db:seed --class=SixteenPfQuestionSeeder
 *
 * Sumber: storage/app/psychometric/16pf_bundle.json
 * (generate ulang: python scripts/extract_16pf_for_seed.py)
 */
class SixteenPfQuestionSeeder extends Seeder
{
    private const CATEGORY_NAME = '16PF';

    private const KATEGORI_SOAL = '16PF';

    public function run(): void
    {
        if (!Schema::hasTable('question_categories') || !Schema::hasTable('soal_psikotes')) {
            if ($this->command) {
                $this->command->warn('SixteenPfQuestionSeeder: tabel question_categories / soal_psikotes tidak ada.');
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
        $questions = is_array($bundle['questions'] ?? null) ? $bundle['questions'] : [];
        if (count($questions) !== 105) {
            if ($this->command) {
                $this->command->warn('Bundle berisi ' . count($questions) . ' soal (diharapkan 105).');
            }
        }

        $now = Carbon::now();

        DB::transaction(function () use ($questions, $now) {
            $categoryId = $this->ensureCategory($now);

            DB::table('soal_psikotes')->where('kategori_soal', self::KATEGORI_SOAL)->delete();

            foreach ($questions as $item) {
                $no = (int) ($item['no'] ?? 0);
                if ($no < 1) {
                    continue;
                }

                $pertanyaan = json_encode([
                    'no' => $no,
                    'text' => (string) ($item['text'] ?? ''),
                    'options' => $item['options'] ?? [],
                ], JSON_UNESCAPED_UNICODE);

                $jawaban = json_encode([
                    'factor' => (string) ($item['factor'] ?? 'A'),
                    'points' => $item['points'] ?? ['a' => 0, 'b' => 0, 'c' => 0],
                ], JSON_UNESCAPED_UNICODE);

                $row = [
                    'kategori_soal' => self::KATEGORI_SOAL,
                    'pertanyaan' => $pertanyaan,
                    'jawaban' => $jawaban,
                ];

                if (Schema::hasColumn('soal_psikotes', 'kategori')) {
                    $row['kategori'] = self::CATEGORY_NAME;
                }
                if (Schema::hasColumn('soal_psikotes', 'kunci_jawaban')) {
                    $row['kunci_jawaban'] = '';
                }

                DB::table('soal_psikotes')->insert($row);
            }

            if ($categoryId) {
                DB::table('question_categories')->where('id', $categoryId)->update([
                    'question_count' => 105,
                    'updated_at' => $now,
                ]);
            }
        });

        if ($this->command) {
            $this->command->info('SixteenPfQuestionSeeder: 16PF (' . count($questions) . ' soal) selesai.');
        }
    }

    private function ensureCategory(Carbon $now): ?int
    {
        $existing = DB::table('question_categories')->where('name', self::CATEGORY_NAME)->first();
        $payload = [
            'question_count' => 105,
            'duration_minutes' => 60,
            'is_active' => 1,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('question_categories', 'is_show')) {
            $payload['is_show'] = 0;
        }
        if (Schema::hasColumn('question_categories', 'category_scope')) {
            $payload['category_scope'] = 'hr';
        }
        if (Schema::hasColumn('question_categories', 'has_time_limit')) {
            $payload['has_time_limit'] = 1;
        }

        if ($existing) {
            DB::table('question_categories')->where('id', $existing->id)->update($payload);

            return (int) $existing->id;
        }

        $insert = array_merge($payload, [
            'name' => self::CATEGORY_NAME,
            'created_by' => 'SixteenPfQuestionSeeder',
            'created_at' => $now,
        ]);

        return (int) DB::table('question_categories')->insertGetId($insert);
    }
}
