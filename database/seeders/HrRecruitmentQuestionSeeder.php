<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder bank soal HR: INTEGRITAS, LOGIKA, NALAR (100 soal aktif per kategori).
 * Struktur mengikuti LogicalAndAccuracyQuestionSeeder.
 *
 * Jalankan: php artisan db:seed --class=HrRecruitmentQuestionSeeder
 */
class HrRecruitmentQuestionSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            $categories = $this->ensureCategories();

            foreach ($categories as $category) {
                $questionIds = DB::table('questions')
                    ->where('question_category_id', $category->id)
                    ->where(function ($query) {
                        $query->where('question_scope', 'hr')->orWhereNull('question_scope');
                    })
                    ->pluck('id');

                if ($questionIds->isNotEmpty()) {
                    DB::table('question_options')->whereIn('question_id', $questionIds)->delete();
                    DB::table('questions')->whereIn('id', $questionIds)->delete();
                }
            }

            foreach ($this->logicalQuestions() as $question) {
                $this->storeQuestion($categories['LOGIKA'], $question, 'medium');
            }

            foreach ($this->accuracyQuestions() as $question) {
                $this->storeQuestion($categories['NALAR'], $question, 'medium');
            }

            foreach ($this->integrityQuestions() as $question) {
                $this->storeQuestion($categories['INTEGRITAS'], $question, 'medium');
            }
        });
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
            } else {
                DB::table('question_categories')->where('id', $category->id)->update($payload);
            }
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
        $questions = [];

        // 1. Deret angka (25 soal)
        for ($n = 1; $n <= 25; $n++) {
            $patterns = [
                ['start' => 3 + $n, 'step' => 2 + ($n % 6)],
                ['start' => 5 + ($n * 2), 'step' => 3 + ($n % 5)],
                ['start' => 10 + $n, 'step' => 5 + ($n % 4)],
            ];
            $p = $patterns[$n % 3];
            $values = [
                $p['start'],
                $p['start'] + $p['step'],
                $p['start'] + (2 * $p['step']),
                $p['start'] + (3 * $p['step']),
            ];
            $correct = $p['start'] + (4 * $p['step']);
            $questions[] = $this->question(
                'Deret angka berikut: ' . implode(', ', $values) . ', ... Bilangan selanjutnya adalah',
                (string) $correct,
                [(string) ($correct - $p['step']), (string) ($correct + 1), (string) ($correct + $p['step'])],
                'Pola selisih antar angka pada deret ini konstan (' . $p['step'] . ').',
                $n
            );
        }

        // 2. Silogisme / penalaran verbal kerja (25 soal)
        $syllogisms = [
            ['Semua staf administrasi membuat laporan harian.', 'Sebagian staf administrasi juga menjadi petugas arsip.', 'Sebagian petugas arsip membuat laporan harian.', 'Semua petugas arsip membuat laporan harian.', 'Tidak ada petugas arsip yang membuat laporan harian.', 'Semua staf administrasi adalah petugas arsip.'],
            ['Semua teknisi laboratorium memakai APD.', 'Sebagian teknisi laboratorium ditugaskan ke shift malam.', 'Sebagian teknisi shift malam memakai APD.', 'Semua teknisi shift malam memakai APD.', 'Tidak ada teknisi shift malam yang memakai APD.', 'Semua teknisi shift malam adalah teknisi laboratorium.'],
            ['Semua dokumen keluar wajib disetujui atasan.', 'Sebagian surat pengantaran adalah dokumen keluar.', 'Sebagian surat pengantaran wajib disetujui atasan.', 'Semua surat pengantaran wajib disetujui atasan.', 'Tidak ada surat pengantaran yang wajib disetujui atasan.', 'Semua surat pengantaran bukan dokumen keluar.'],
            ['Semua karyawan yang lembur mendapat makan malam.', 'Sebagian karyawan bagian produksi lembur minggu ini.', 'Sebagian karyawan produksi lembur mendapat makan malam.', 'Semua karyawan produksi lembur mendapat makan malam.', 'Tidak ada karyawan produksi lembur yang mendapat makan malam.', 'Semua karyawan produksi selalu lembur.'],
            ['Semua sampel uji harus diberi label sebelum disimpan.', 'Sebagian sampel dari pelanggan korporat masuk hari ini.', 'Sebagian sampel pelanggan korporat harus diberi label sebelum disimpan.', 'Semua sampel pelanggan korporat harus diberi label sebelum disimpan.', 'Tidak ada sampel pelanggan korporat yang perlu label.', 'Semua sampel pelanggan korporat sudah disimpan tanpa label.'],
        ];
        for ($n = 0; $n < 25; $n++) {
            $s = $syllogisms[$n % count($syllogisms)];
            $questions[] = $this->question(
                $s[0] . ' ' . $s[1] . ' Kesimpulan yang paling tepat adalah',
                $s[2],
                [$s[3], $s[4], $s[5]],
                'Kesimpulan hanya boleh ditarik pada anggota himpunan yang memenuhi kedua syarat.',
                $n + 30
            );
        }

        // 3. Perhitungan kerja & bisnis (25 soal)
        for ($n = 1; $n <= 25; $n++) {
            $workers = 2 + ($n % 6);
            $hours = 2 + ($n % 7);
            $total = $workers * $hours;
            $newWorkers = $workers + 1 + ($n % 3);
            $newHours = round($total / $newWorkers, 1);
            $questions[] = $this->question(
                $workers . ' analis menyelesaikan entri data dalam ' . $hours . ' jam (kecepatan kerja sama). Jika ditambah menjadi ' . $newWorkers . ' analis, estimasi waktu penyelesaian adalah',
                $newHours . ' jam',
                [($newHours + 1) . ' jam', ($newHours + 2) . ' jam', max(1, $newHours - 1) . ' jam'],
                'Total jam-orang tetap ' . $total . '; waktu berbanding terbalik dengan jumlah pekerja.',
                $n + 60
            );
        }

        // 4. Diskon, persentase, dan proporsi (25 soal)
        for ($n = 1; $n <= 25; $n++) {
            $base = 150000 + ($n * 12500);
            $percent = [5, 10, 12, 15, 20, 25][$n % 6];
            $after = (int) round($base * (100 - $percent) / 100);
            $questions[] = $this->question(
                'Biaya pengujian laboratorium sebesar Rp' . number_format($base, 0, ',', '.') . ' diberi diskon ' . $percent . '% untuk pelanggan kontrak. Nominal yang harus dibayar adalah',
                'Rp' . number_format($after, 0, ',', '.'),
                [
                    'Rp' . number_format($after + 25000, 0, ',', '.'),
                    'Rp' . number_format($base, 0, ',', '.'),
                    'Rp' . number_format(max(0, $after - 25000), 0, ',', '.'),
                ],
                'Nominal akhir = harga awal × ' . (100 - $percent) . '%.',
                $n + 90
            );
        }

        return $questions;
    }

    private function accuracyQuestions()
    {
        $questions = [];
        $months = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
        $names = ['Aditya', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fitri', 'Gita', 'Hadi', 'Indah', 'Joko', 'Kartika', 'Lestari', 'Maya', 'Nanda', 'Oki', 'Putri', 'Rizky', 'Sari', 'Taufik', 'Ulfa'];

        // 1. Pencocokan kode dokumen (20)
        for ($n = 1; $n <= 20; $n++) {
            $ref = sprintf('ISL-LAB-%s-%04d-%03d', $months[($n - 1) % 12], 2024 + ($n % 2), 100 + ($n * 11));
            $wrong = [
                preg_replace('/\d{3}$/', str_pad((string) (($n * 11 + 1) % 1000), 3, '0', STR_PAD_LEFT), $ref),
                str_replace('ISL-LAB', 'ISL-LBA', $ref),
                str_replace('-', '/', $ref),
            ];
            $questions[] = $this->question(
                'Pada berkas hasil uji, kode referensi yang benar adalah: ' . $ref . '. Pilih data yang identik.',
                $ref,
                $wrong,
                'Perhatikan urutan huruf, angka, dan tanda hubung secara persis.',
                $n
            );
        }

        // 2. Temukan data yang berbeda (20)
        for ($n = 1; $n <= 20; $n++) {
            $ref = sprintf('NR-%02d-%s-%04d', $n, $months[$n % 12], 5000 + ($n * 17));
            $different = substr_replace($ref, 'O', 3, 1);
            $options = [$ref, $different, str_replace('NR-', 'RN-', $ref), strtoupper(strtolower($ref))];
            $questions[] = [
                'text' => 'Manakah kode sampel yang TIDAK sama dengan tiga kode lainnya?',
                'options' => $this->rotateOptions($options, $n),
                'answer' => array_search($different, $this->rotateOptions($options, $n), true),
                'explanation' => 'Bandingkan karakter per karakter, termasuk posisi huruf dan angka.',
            ];
        }

        // 3. Validasi tanggal & format (20)
        for ($n = 1; $n <= 20; $n++) {
            $day = str_pad((string) (($n * 2 % 28) + 1), 2, '0', STR_PAD_LEFT);
            $month = str_pad((string) (($n % 12) + 1), 2, '0', STR_PAD_LEFT);
            $year = 2024 + ($n % 2);
            $correct = $day . '/' . $month . '/' . $year;
            $options = [
                $correct,
                $month . '/' . $day . '/' . $year,
                $day . '-' . $month . '-' . $year,
                $day . '/' . $month . '/' . ($year + 1),
            ];
            $rotated = $this->rotateOptions($options, $n + 2);
            $questions[] = [
                'text' => 'Tanggal sampling tercatat: ' . $correct . '. Pilih format yang benar-benar sama.',
                'options' => $rotated,
                'answer' => array_search($correct, $rotated, true),
                'explanation' => 'Format DD/MM/YYYY dan pemisah harus identik.',
            ];
        }

        // 4. Penjumlahan transaksi (20)
        for ($n = 1; $n <= 20; $n++) {
            $a = 45000 + ($n * 3200);
            $b = 28000 + ($n * 1800);
            $c = 12000 + ($n * 900);
            $total = $a + $b + $c;
            $options = [$total, $total + 500, $total - 750, $total + 1200];
            $rotated = $this->rotateOptions($options, $n + 1);
            $questions[] = [
                'text' => 'Hitung total: Rp' . number_format($a, 0, ',', '.') . ' + Rp' . number_format($b, 0, ',', '.') . ' + Rp' . number_format($c, 0, ',', '.') . ' =',
                'options' => array_map(fn ($v) => 'Rp' . number_format($v, 0, ',', '.'), $rotated),
                'answer' => array_search($total, $rotated, true),
                'explanation' => 'Jumlahkan ketiga nominal tanpa membulatkan di tengah perhitungan.',
            ];
        }

        // 5. Urutan alfabet & indeks data (20)
        for ($n = 1; $n <= 20; $n++) {
            $pick = [
                $names[$n % 20],
                $names[($n + 4) % 20],
                $names[($n + 9) % 20],
                $names[($n + 13) % 20],
            ];
            $sorted = $pick;
            sort($sorted, SORT_STRING);
            $wrong1 = $pick;
            $wrong2 = array_reverse($sorted);
            $wrong3 = [$sorted[1], $sorted[0], $sorted[2], $sorted[3]];
            $options = [
                implode(', ', $sorted),
                implode(', ', $wrong1),
                implode(', ', $wrong2),
                implode(', ', $wrong3),
            ];
            $rotated = $this->rotateOptions($options, $n);
            $questions[] = [
                'text' => 'Urutkan nama petugas sampling berikut dari A ke Z: ' . implode(', ', $pick) . '.',
                'options' => $rotated,
                'answer' => array_search(implode(', ', $sorted), $rotated, true),
                'explanation' => 'Urutkan berdasarkan abjad nama lengkap.',
            ];
        }

        return $questions;
    }

    private function integrityQuestions()
    {
        return require __DIR__ . '/data/HrIntegrityScenarios.php';
    }

    private function question($text, $correct, array $wrong, $explanation, $seed = 0)
    {
        $options = array_merge([$correct], array_slice($wrong, 0, 3));
        $options = $this->rotateOptions($options, $seed);

        return [
            'text' => $text,
            'options' => $options,
            'answer' => array_search($correct, $options, true),
            'explanation' => $explanation,
        ];
    }

    private function rotateOptions(array $options, $seed)
    {
        $shift = abs((int) $seed) % max(1, count($options));

        return array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
    }
}
