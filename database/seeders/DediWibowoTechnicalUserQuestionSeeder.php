<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder bank soal user (manager scope) untuk Dedi Wibowo (IT Programming Manager).
 *
 * 3 Kategori Ujian Teknis (masing-masing 100 soal berbobot jurnal / standar industri):
 * 1. FRONTEND_DEVELOPMENT (100 soal)
 * 2. BACKEND_DEVELOPMENT (100 soal)
 * 3. UI_UX_DESIGN (100 soal)
 *
 * Total: 300 soal dengan penjelasan berbasis literatur/jurnal ilmiah dan standar industri.
 */
class DediWibowoTechnicalUserQuestionSeeder extends Seeder
{
    private const QUESTIONS_PER_CATEGORY = 100;

    public function run()
    {
        DB::transaction(function () {
            $ownerKaryawan = $this->ownerKaryawan();
            $categories = $this->ensureCategories($ownerKaryawan);

            $this->seedCategory($categories['FRONTEND_DEVELOPMENT'], $this->frontendQuestions(), $ownerKaryawan);
            $this->seedCategory($categories['BACKEND_DEVELOPMENT'], $this->backendQuestions(), $ownerKaryawan);
            $this->seedCategory($categories['UI_UX_DESIGN'], $this->uiUxQuestions(), $ownerKaryawan);
        });
    }

    private function ownerKaryawan(): string
    {
        return 'Dedi Wibowo';
    }

    private function ensureCategories(string $ownerKaryawan)
    {
        $definitions = [
            'FRONTEND_DEVELOPMENT' => ['duration_minutes' => 50, 'question_count' => 30],
            'BACKEND_DEVELOPMENT'  => ['duration_minutes' => 50, 'question_count' => 30],
            'UI_UX_DESIGN'        => ['duration_minutes' => 50, 'question_count' => 30],
        ];
        $now = Carbon::now();

        foreach ($definitions as $name => $config) {
            $category = DB::table('question_categories')
                ->where('name', $name)
                ->where('category_scope', 'manager')
                ->where('owner_karyawan', $ownerKaryawan)
                ->first();

            $payload = [
                'question_count'   => $config['question_count'],
                'duration_minutes' => $config['duration_minutes'],
                'has_time_limit'   => 1,
                'is_active'        => 1,
                'is_show'          => 1,
                'category_scope'   => 'manager',
                'owner_karyawan'   => $ownerKaryawan,
                'assigned_manager' => null,
                'updated_at'       => $now,
            ];

            if (!$category) {
                DB::table('question_categories')->insert(array_merge($payload, [
                    'name'       => $name,
                    'created_by' => $ownerKaryawan,
                    'created_at' => $now,
                ]));
            } else {
                DB::table('question_categories')
                    ->where('id', $category->id)
                    ->update($payload);
            }
        }

        return DB::table('question_categories')
            ->where('category_scope', 'manager')
            ->where('owner_karyawan', $ownerKaryawan)
            ->whereIn('name', array_keys($definitions))
            ->get()
            ->keyBy('name');
    }

    private function seedCategory($category, array $questions, string $ownerKaryawan)
    {
        if (count($questions) !== self::QUESTIONS_PER_CATEGORY) {
            throw new \RuntimeException(
                'Kategori ' . $category->name . ' harus tepat berisi '
                . self::QUESTIONS_PER_CATEGORY . ' soal, namun ditemukan ' . count($questions) . '.'
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

        $this->logMessage($category->name . ": {$inserted} soal berhasil ditambahkan, {$skipped} soal duplikat dilewati.");
    }

    private function existingManagerQuestionTexts($categoryId, string $ownerKaryawan): array
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

    private function storeQuestion($category, array $question, string $ownerKaryawan, string $difficulty)
    {
        $now = Carbon::now();
        $questionId = DB::table('questions')->insertGetId([
            'question_category_id' => $category->id,
            'question_scope'       => 'manager',
            'owner_karyawan'       => $ownerKaryawan,
            'question_type'        => 'single_choice',
            'scale_type_id'        => null,
            'scoring_type'         => 'correct_answer',
            'question_text'        => $question['text'],
            'question_image'       => '[]',
            'explanation'          => $question['explanation'],
            'difficulty'           => $difficulty,
            'status'               => 'active',
            'is_active'            => 1,
            'created_by'           => $ownerKaryawan,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        foreach ($question['options'] as $index => $option) {
            DB::table('question_options')->insert([
                'question_id'  => $questionId,
                'option_text'  => $option,
                'option_image' => null,
                'is_correct'   => $index === $question['answer'] ? 1 : 0,
                'option_order' => $index + 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    private function logMessage(string $msg)
    {
        if ($this->command) {
            $this->command->info($msg);
        } else {
            echo $msg . PHP_EOL;
        }
    }

    private function frontendQuestions(): array
    {
        return require __DIR__ . '/data/FrontendDevelopmentQuestions.php';
    }

    private function backendQuestions(): array
    {
        return require __DIR__ . '/data/BackendDevelopmentQuestions.php';
    }

    private function uiUxQuestions(): array
    {
        return require __DIR__ . '/data/UiUxDesignQuestions.php';
    }
}
