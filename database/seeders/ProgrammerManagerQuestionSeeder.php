<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder bank soal assessment user (manager scope) untuk posisi Programmer.
 *
 * 100 soal aktif per kategori:
 * - DEV_ALGORITMA      : kompleksitas, struktur data, tracing kode
 * - DEV_DATABASE       : SQL, relasi, transaksi, indexing
 * - DEV_ENGINEERING    : Git, API, keamanan, best practice tim dev
 *
 * Data soal:
 *   database/seeders/data/ProgrammerAlgorithmQuestions.php
 *   database/seeders/data/ProgrammerDatabaseQuestions.php
 *   database/seeders/data/ProgrammerEngineeringQuestions.php
 *
 * Mode aman: tidak menghapus soal/options yang sudah ada.
 * Duplikat (teks sama) dilewati per kategori + owner.
 *
 * Konfigurasi owner (nama manager pemilik bank soal):
 *   MANAGER_QUESTION_OWNER_KARYAWAN di .env
 *
 * PENTING: Jalankan hanya di staging/dev, bukan production langsung.
 *   php artisan db:seed --class=ProgrammerManagerQuestionSeeder
 */
class ProgrammerManagerQuestionSeeder extends Seeder
{
    private const QUESTIONS_PER_CATEGORY = 100;

    public function run()
    {
        DB::transaction(function () {
            $ownerKaryawan = $this->ownerKaryawan();
            $categories = $this->ensureCategories($ownerKaryawan);

            $this->seedCategory($categories['DEV_ALGORITMA'], $this->algorithmQuestions(), $ownerKaryawan);
            $this->seedCategory($categories['DEV_DATABASE'], $this->databaseQuestions(), $ownerKaryawan);
            $this->seedCategory($categories['DEV_ENGINEERING'], $this->engineeringQuestions(), $ownerKaryawan);
        });
    }

    private function ownerKaryawan(): string
    {
        $owner = trim((string) env('MANAGER_QUESTION_OWNER_KARYAWAN', ''));

        return $owner !== '' ? $owner : 'Programmer Manager';
    }

    private function seedCategory($category, array $questions, string $ownerKaryawan)
    {
        if (count($questions) !== self::QUESTIONS_PER_CATEGORY) {
            throw new \RuntimeException(
                'Kategori ' . $category->name . ' harus berisi tepat '
                . self::QUESTIONS_PER_CATEGORY . ' soal di file data, ditemukan ' . count($questions) . '.'
            );
        }

        $existingTexts = $this->existingManagerQuestionTexts($category->id, $ownerKaryawan);
        $inserted = 0;
        $skipped = 0;

        foreach ($questions as $question) {
            $text = trim((string) ($question['text'] ?? ''));
            if ($text === '' || isset($existingTexts[$text])) {
                $skipped++;
                continue;
            }

            $this->storeQuestion($category, $question, $ownerKaryawan, 'medium');
            $existingTexts[$text] = true;
            $inserted++;
        }

        $this->log($category->name . ': ditambah ' . $inserted . ' soal, dilewati ' . $skipped . ' duplikat.');
    }

    private function existingManagerQuestionTexts($categoryId, string $ownerKaryawan)
    {
        $texts = [];
        foreach (
            DB::table('questions')
                ->where('question_category_id', $categoryId)
                ->where('question_scope', 'manager')
                ->where('owner_karyawan', $ownerKaryawan)
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

    private function ensureCategories(string $ownerKaryawan)
    {
        $definitions = [
            'DEV_ALGORITMA' => ['duration_minutes' => 45, 'question_count' => 10],
            'DEV_DATABASE' => ['duration_minutes' => 30, 'question_count' => 10],
            'DEV_ENGINEERING' => ['duration_minutes' => 30, 'question_count' => 10],
        ];
        $now = Carbon::now();

        foreach ($definitions as $name => $config) {
            $category = DB::table('question_categories')
                ->where('name', $name)
                ->where('category_scope', 'manager')
                ->where('owner_karyawan', $ownerKaryawan)
                ->first();

            $payload = [
                'question_count' => $config['question_count'],
                'duration_minutes' => $config['duration_minutes'],
                'is_active' => 1,
                'is_show' => 1,
                'category_scope' => 'manager',
                'owner_karyawan' => $ownerKaryawan,
                'assigned_manager' => null,
                'updated_at' => $now,
            ];

            if (!$category) {
                DB::table('question_categories')->insert(array_merge($payload, [
                    'name' => $name,
                    'created_by' => 'ProgrammerManagerQuestionSeeder',
                    'created_at' => $now,
                ]));
            }
        }

        return DB::table('question_categories')
            ->where('category_scope', 'manager')
            ->where('owner_karyawan', $ownerKaryawan)
            ->whereIn('name', array_keys($definitions))
            ->get()
            ->keyBy('name');
    }

    private function storeQuestion($category, array $question, string $ownerKaryawan, $difficulty)
    {
        $now = Carbon::now();
        $questionId = DB::table('questions')->insertGetId([
            'question_category_id' => $category->id,
            'question_scope' => 'manager',
            'owner_karyawan' => $ownerKaryawan,
            'question_type' => 'single_choice',
            'scale_type_id' => null,
            'scoring_type' => 'correct_answer',
            'question_text' => $question['text'],
            'question_image' => json_encode([]),
            'explanation' => $question['explanation'],
            'difficulty' => $difficulty,
            'status' => 'active',
            'is_active' => 1,
            'created_by' => 'ProgrammerManagerQuestionSeeder',
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

    private function algorithmQuestions()
    {
        return require __DIR__ . '/data/ProgrammerAlgorithmQuestions.php';
    }

    private function databaseQuestions()
    {
        return require __DIR__ . '/data/ProgrammerDatabaseQuestions.php';
    }

    private function engineeringQuestions()
    {
        return require __DIR__ . '/data/ProgrammerEngineeringQuestions.php';
    }
}
