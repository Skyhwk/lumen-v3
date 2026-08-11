<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssessmentQuestionSeeder extends Seeder
{
    public function run()
    {
        $categories = DB::table('question_categories')->whereIn('name', ['Personality', 'Ketangkasan'])->where('is_active', 1)->get();
        if ($categories->count() !== 2) {
            throw new \RuntimeException('Kategori Personality dan Ketangkasan aktif belum lengkap.');
        }

        foreach ($categories as $category) {
            for ($number = 1; $number <= 100; $number++) {
                $type = 'single_choice';
                $now = Carbon::now();
                $questionId = DB::table('questions')->insertGetId([
                    'question_category_id' => $category->id,
                    'question_type' => $type,
                    'scale_type_id' => null,
                    'scoring_type' => 'correct_answer',
                    'question_text' => $this->questionText($category->name, $number, $type),
                    'question_image' => json_encode([]),
                    'explanation' => 'Dummy assessment question generated for testing.',
                    'difficulty' => ['easy', 'medium', 'hard'][array_rand(['easy', 'medium', 'hard'])],
                    'status' => 'active', 'is_active' => 1, 'created_by' => 'AssessmentQuestionSeeder',
                    'created_at' => $now, 'updated_at' => $now,
                ]);

                if ($type === 'single_choice') {
                    $correct = [array_rand([1, 2, 3, 4]) + 1];
                    foreach (['Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D'] as $index => $option) {
                        DB::table('question_options')->insert([
                            'question_id' => $questionId, 'option_text' => $option . ' untuk soal ' . $number,
                            'option_image' => null, 'is_correct' => in_array($index + 1, $correct),
                            'option_order' => $index + 1, 'created_at' => $now, 'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }

    private function questionText($category, $number, $type)
    {
        if ($type === 'scale') return 'Seberapa setuju Anda dengan pernyataan ' . $category . ' nomor ' . $number . '?';
        if ($type === 'text') return 'Tuliskan respons singkat untuk pertanyaan ' . $category . ' nomor ' . $number . '.';
        return 'Pilih jawaban paling tepat untuk soal ' . $category . ' nomor ' . $number . '.';
    }
}
