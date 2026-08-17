<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder bank soal HR rekrutmen: LOGIKA, NALAR, INTEGRITAS.
 *
 * 100 soal aktif per kategori, mengikuti struktur LogicalAndAccuracyQuestionSeeder.
 *
 * Konstruk psikometri (referensi literatur seleksi personel):
 * - LOGIKA  : numerical reasoning, deductive reasoning, verbal analogies,
 *             abstract/letter series (aptitude test / TIU)
 * - NALAR   : clerical checking & accuracy (tradisi Pauli/Kraepelin, TKB)
 * - INTEGRITAS : Situational Judgment Test (SJT) etika kerja
 *
 * Data soal: database/seeders/data/HrLogicQuestions.php
 *            database/seeders/data/HrAccuracyQuestions.php
 *            database/seeders/data/HrIntegrityScenarios.php
 *
 * Mode aman: tidak menghapus soal/options yang sudah ada.
 * Setiap dijalankan, coba tambah 100 soal per kategori; duplikat (teks sama) dilewati.
 *
 * PENTING: Jalankan hanya di staging/dev, bukan production langsung.
 *   php artisan db:seed --class=HrRecruitmentQuestionSeeder
 */
class HrRecruitmentQuestionSeeder extends Seeder
{
    private const QUESTIONS_PER_CATEGORY = 100;

    public function run()
    {
        DB::transaction(function () {
            $categories = $this->ensureCategories();

            $this->seedCategory($categories['LOGIKA'], $this->logicalQuestions());
            $this->seedCategory($categories['NALAR'], $this->accuracyQuestions());
            $this->seedCategory($categories['INTEGRITAS'], $this->integrityQuestions());
        });
    }

    private function seedCategory($category, array $questions)
    {
        if (count($questions) !== self::QUESTIONS_PER_CATEGORY) {
            throw new \RuntimeException(
                'Kategori ' . $category->name . ' harus berisi tepat '
                . self::QUESTIONS_PER_CATEGORY . ' soal di file data, ditemukan ' . count($questions) . '.'
            );
        }

        $existingTexts = $this->existingHrQuestionTexts($category->id);
        $inserted = 0;
        $skipped = 0;

        foreach ($questions as $question) {
            $text = trim((string) ($question['text'] ?? ''));
            if ($text === '' || isset($existingTexts[$text])) {
                $skipped++;
                continue;
            }

            $this->storeQuestion($category, $question, 'medium');
            $existingTexts[$text] = true;
            $inserted++;
        }

        $this->log($category->name . ': ditambah ' . $inserted . ' soal, dilewati ' . $skipped . ' duplikat.');
    }

    private function existingHrQuestionTexts($categoryId)
    {
        $texts = [];
        foreach (
            DB::table('questions')
                ->where('question_category_id', $categoryId)
                ->where(function ($query) {
                    $query->where('question_scope', 'hr')->orWhereNull('question_scope');
                })
                ->pluck('question_text') as $text
        ) {
            $texts[trim((string) $text)] = true;
        }

        return $texts;
    }

    private function log($message)
    {
        if ($this->command) {
            $this->command->info($message);
        }
    }

    private function ensureCategories()
    {
        $definitions = [
            'LOGIKA' => ['duration_minutes' => 45, 'question_count' => 5],
            'NALAR' => ['duration_minutes' => 30, 'question_count' => 5],
            'INTEGRITAS' => ['duration_minutes' => 30, 'question_count' => 3],
        ];
        $now = Carbon::now();

        foreach ($definitions as $name => $config) {
            $category = DB::table('question_categories')->where('name', $name)->first();
            $payload = [
                'question_count' => $config['question_count'],
                'duration_minutes' => $config['duration_minutes'],
                'is_active' => 1,
                'is_show' => 1,
                'category_scope' => 'hr',
                'updated_at' => $now,
            ];

            if (!$category) {
                DB::table('question_categories')->insert(array_merge($payload, [
                    'name' => $name,
                    'created_by' => 'HrRecruitmentQuestionSeeder',
                    'created_at' => $now,
                ]));
            }
            // Kategori yang sudah ada tidak di-update agar konfigurasi production tetap utuh.
        }

        return DB::table('question_categories')
            ->whereIn('name', array_keys($definitions))
            ->get()
            ->keyBy('name');
    }

    private function storeQuestion($category, array $question, $difficulty)
    {
        $now = Carbon::now();
        $questionId = DB::table('questions')->insertGetId([
            'question_category_id' => $category->id,
            'question_scope' => 'hr',
            'owner_karyawan' => null,
            'question_type' => 'single_choice',
            'scale_type_id' => null,
            'scoring_type' => 'correct_answer',
            'question_text' => $question['text'],
            'question_image' => json_encode([]),
            'explanation' => $question['explanation'],
            'difficulty' => $difficulty,
            'status' => 'active',
            'is_active' => 1,
            'created_by' => 'HrRecruitmentQuestionSeeder',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($question['options'] as $index => $option) {
            DB::table('question_options')->insert([
                'question_id' => $questionId,
                'option_text' => $option,
                'option_image' => null,
                'is_correct' => $index === $question['answer'],
                'option_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function logicalQuestions()
    {
        return require __DIR__ . '/data/HrLogicQuestions.php';
    }

    private function accuracyQuestions()
    {
        return require __DIR__ . '/data/HrAccuracyQuestions.php';
    }

    private function integrityQuestions()
    {
        return require __DIR__ . '/data/HrIntegrityScenarios.php';
    }
}
