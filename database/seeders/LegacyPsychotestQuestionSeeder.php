<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyPsychotestQuestionSeeder extends Seeder
{
    private const CREATED_BY = 'LegacyPsychotestQuestionSeeder';

    private const IST_NAMES = [
        'umum' => 'IST - Umum',
        'kesamaan_kata' => 'IST - Kesamaan Kata',
        'hubungan_kata' => 'IST - Hubungan Kata',
        'pengertian_kata' => 'IST - Pengertian Kata',
        'aritmatika' => 'IST - Aritmatika',
        'deret_angka' => 'IST - Deret Angka',
        'potongan_gambar' => 'IST - Potongan Gambar',
        'kemampuan_ruang' => 'IST - Kemampuan Ruang',
        'menghafal_cepat' => 'IST - Menghafal Cepat',
    ];

    public function run()
    {
        if (!Schema::hasTable('soal_psikotes') || !Schema::hasTable('question_categories')
            || !Schema::hasTable('questions') || !Schema::hasTable('question_options')
            || !Schema::hasTable('scale_types')) {
            return;
        }

        DB::transaction(function () {
            $legacyRows = DB::table('soal_psikotes')
                ->whereNotIn('kategori_soal', ['DISC', 'KOSTICK PAPI', 'PAPI KOSTICK'])
                ->orderBy('id')
                ->get();

            $categoryIds = [];
            foreach ($legacyRows->groupBy(fn ($row) => $this->categoryName($row)) as $name => $rows) {
                $categoryIds[$name] = $this->ensureCategory($name, $rows->count());
            }

            foreach ($categoryIds as $categoryId) {
                $questionIds = DB::table('questions')
                    ->where('question_category_id', $categoryId)
                    ->where('created_by', self::CREATED_BY)
                    ->pluck('id');
                if ($questionIds->isNotEmpty()) {
                    DB::table('question_options')->whereIn('question_id', $questionIds)->delete();
                    DB::table('questions')->whereIn('id', $questionIds)->delete();
                }
            }

            foreach ($legacyRows as $row) {
                $this->migrateQuestion($row, $categoryIds[$this->categoryName($row)]);
            }
        });
    }

    private function categoryName($row): string
    {
        if (strtoupper(trim((string) $row->kategori_soal)) === 'IST') {
            return self::IST_NAMES[$row->kategori] ?? ('IST - ' . ucwords(str_replace('_', ' ', (string) $row->kategori)));
        }

        return ucwords(strtolower((string) $row->kategori_soal));
    }

    private function ensureCategory(string $name, int $count): int
    {
        $now = Carbon::now();
        $category = DB::table('question_categories')
            ->where('name', $name)
            ->where('category_scope', 'default')
            ->first();

        $payload = [
            'category_scope' => 'default',
            'owner_karyawan' => null,
            'assigned_manager' => null,
            'question_count' => $count,
            'duration_minutes' => 30,
            'has_time_limit' => 1,
            'is_show' => 1,
            'is_active' => 1,
            'created_by' => self::CREATED_BY,
            'updated_at' => $now,
        ];

        if ($category) {
            DB::table('question_categories')->where('id', $category->id)->update($payload);
            return (int) $category->id;
        }

        return (int) DB::table('question_categories')->insertGetId(array_merge($payload, [
            'name' => $name,
            'created_at' => $now,
        ]));
    }

    private function migrateQuestion($row, int $categoryId): void
    {
        $prompt = json_decode($row->pertanyaan ?: '{}', true) ?: [];
        $answer = json_decode($row->jawaban ?: '{}', true) ?: [];
        $promptType = strtolower((string) ($prompt['type'] ?? 'text'));
        $answerType = strtolower((string) ($answer['type'] ?? 'text'));
        $hasScaleValues = isset($answer['value']) && is_array($answer['value']) && !$row->kunci_jawaban;
        $isFreeText = in_array($answerType, ['text', 'number'], true);
        $isScale = !$isFreeText && $hasScaleValues;
        $questionType = $isScale ? 'scale' : ($isFreeText ? 'text' : 'single_choice');
        $scaleTypeId = $isScale ? $this->ensureScaleType($answer) : null;
        $now = Carbon::now();
        $questionText = $promptType === 'image'
            ? 'Pilih jawaban yang tepat untuk gambar berikut.'
            : preg_replace('/^\s*\d+\s*[\.\)\-:]\s*/', '', trim((string) ($prompt['data'] ?? '')));
        $questionImage = $promptType === 'image' && !empty($prompt['data'])
            ? [(string) $prompt['data']]
            : [];

        $questionId = DB::table('questions')->insertGetId([
            'question_category_id' => $categoryId,
            'question_scope' => 'hr',
            'owner_karyawan' => null,
            'question_type' => $questionType,
            'scale_type_id' => $scaleTypeId,
            'scoring_type' => $isScale ? 'weighted_option' : 'correct_answer',
            'question_text' => $questionText,
            'question_image' => json_encode($questionImage),
            'explanation' => 'Migrasi soal_psikotes #' . $row->id . ($row->kategori ? ' | ' . $row->kategori : ''),
            'difficulty' => 'medium',
            'status' => 'active',
            'is_active' => 1,
            'created_by' => self::CREATED_BY,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($isScale) {
            return;
        }

        if ($isFreeText) {
            DB::table('question_options')->insert([
                'question_id' => $questionId,
                'option_text' => (string) $row->kunci_jawaban,
                'option_image' => null,
                'is_correct' => 1,
                'option_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return;
        }

        $labels = array_values($answer['data'] ?? []);
        $values = array_values($answer['value'] ?? []);
        foreach ($labels as $index => $label) {
            $value = $values[$index] ?? $label;
            $isImage = $promptType === 'image';
            DB::table('question_options')->insert([
                'question_id' => $questionId,
                'option_text' => $isImage ? (string) $value : (string) $label,
                'option_image' => $isImage ? (string) $label : null,
                'is_correct' => strcasecmp(trim((string) $value), trim((string) $row->kunci_jawaban)) === 0
                    || strcasecmp(trim((string) $label), trim((string) $row->kunci_jawaban)) === 0,
                'option_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function ensureScaleType(array $answer): int
    {
        $labels = array_values($answer['data'] ?? []);
        $values = array_values($answer['value'] ?? []);
        $options = [];
        foreach ($labels as $index => $label) {
            $options[] = ['label' => (string) $label, 'value' => (float) ($values[$index] ?? 0)];
        }
        $signature = substr(sha1(json_encode($options)), 0, 12);
        $name = 'Legacy Scale ' . $signature;
        $existing = DB::table('scale_types')->where('name', $name)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $now = Carbon::now();
        return (int) DB::table('scale_types')->insertGetId([
            'name' => $name,
            'description' => 'Skala hasil migrasi dari soal_psikotes.',
            'options' => json_encode($options),
            'is_active' => 1,
            'created_by' => self::CREATED_BY,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
