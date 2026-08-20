<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LogicalAndAccuracyQuestionSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            $categories = $this->ensureCategories();

            foreach ($categories as $category) {
                $questionIds = DB::table('questions')->where('question_category_id', $category->id)->pluck('id');
                if ($questionIds->isNotEmpty()) {
                    DB::table('question_options')->whereIn('question_id', $questionIds)->delete();
                    DB::table('questions')->whereIn('id', $questionIds)->delete();
                }
            }

            foreach ($this->logicalQuestions() as $question) {
                $this->storeQuestion($categories['LOGIKA']->id, $question, 'medium');
            }
            foreach ($this->accuracyQuestions() as $question) {
                $this->storeQuestion($categories['NALAR']->id, $question, 'medium');
            }
            foreach ($this->integrityQuestions() as $question) {
                $this->storeQuestion($categories['INTEGRITAS']->id, $question, 'medium');
            }
        });
    }

    private function ensureCategories()
    {
        $definitions = [
            'LOGIKA' => 45,
            'NALAR' => 30,
            'INTEGRITAS' => 30,
        ];
        $now = Carbon::now();

        foreach ($definitions as $name => $duration) {
            $category = DB::table('question_categories')->where('name', $name)->first();
            if (!$category) {
                DB::table('question_categories')->insert([
                    'name' => $name,
                    'question_count' => 100,
                    'duration_minutes' => $duration,
                    'is_active' => 1,
                    'is_show' => 1,
                    'created_by' => 'LogicalAndAccuracyQuestionSeeder',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('question_categories')->where('id', $category->id)->update([
                    'question_count' => 100,
                    'is_active' => 1,
                    'is_show' => 1,
                    'updated_at' => $now,
                ]);
            }
        }

        return DB::table('question_categories')->whereIn('name', array_keys($definitions))->get()->keyBy('name');
    }

    private function storeQuestion($categoryId, array $question, $difficulty)
    {
        $now = Carbon::now();
        $questionId = DB::table('questions')->insertGetId([
            'question_category_id' => $categoryId,
            'question_type' => 'single_choice',
            'scale_type_id' => null,
            'scoring_type' => 'correct_answer',
            'question_text' => $question['text'],
            'question_image' => json_encode([]),
            'explanation' => $question['explanation'],
            'difficulty' => $difficulty,
            'status' => 'active',
            'is_active' => 1,
            'created_by' => 'LogicalAndAccuracyQuestionSeeder',
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

        for ($n = 1; $n <= 25; $n++) {
            $start = 2 + $n;
            $step = 2 + ($n % 7);
            $values = [$start, $start + $step, $start + (2 * $step), $start + (3 * $step)];
            $correct = $start + (4 * $step);
            $questions[] = $this->question(
                'Deret angka: ' . implode(', ', $values) . ', ... Angka berikutnya adalah',
                $correct,
                [$correct - $step, $correct + 1, $correct + $step],
                'Selisih antarangka pada deret ini selalu ' . $step . '.'
            );
        }

        for ($n = 1; $n <= 25; $n++) {
            $base = 40 + ($n * 4);
            $percent = [10, 15, 20, 25, 30][$n % 5];
            $afterDiscount = $base * (100 - $percent) / 100;
            $questions[] = $this->question(
                'Harga barang Rp' . number_format($base * 10000, 0, ',', '.') . ' didiskon ' . $percent . '%. Harga setelah diskon adalah',
                'Rp' . number_format($afterDiscount * 10000, 0, ',', '.'),
                [
                    'Rp' . number_format(($afterDiscount + 2) * 10000, 0, ',', '.'),
                    'Rp' . number_format(($base - 1) * 10000, 0, ',', '.'),
                    'Rp' . number_format(($afterDiscount - 2) * 10000, 0, ',', '.'),
                ],
                'Harga setelah diskon diperoleh dari harga awal dikali ' . (100 - $percent) . '%.',
                $n
            );
        }

        $relations = [
            ['Analis', 'teliti', 'pegawai', 'Sebagian pegawai teliti'],
            ['Supervisor', 'mengikuti briefing', 'karyawan', 'Sebagian karyawan mengikuti briefing'],
            ['Auditor', 'memeriksa dokumen', 'staf', 'Sebagian staf memeriksa dokumen'],
            ['Operator', 'mematuhi prosedur', 'pekerja', 'Sebagian pekerja mematuhi prosedur'],
            ['Teknisi', 'menggunakan APD', 'pegawai lapangan', 'Sebagian pegawai lapangan menggunakan APD'],
        ];
        for ($n = 0; $n < 25; $n++) {
            [$group, $trait, $subset, $conclusion] = $relations[$n % count($relations)];
            $questions[] = $this->question(
                'Semua ' . strtolower($group) . ' ' . $trait . '. Sebagian ' . $subset . ' adalah ' . strtolower($group) . '. Kesimpulan yang tepat adalah',
                $conclusion,
                ['Semua ' . $subset . ' ' . $trait, 'Tidak ada ' . $subset . ' yang ' . $trait, 'Semua ' . strtolower($group) . ' adalah ' . $subset],
                'Anggota ' . $subset . ' yang merupakan ' . strtolower($group) . ' memiliki sifat tersebut.',
                $n
            );
        }

        for ($n = 1; $n <= 25; $n++) {
            $workers = 2 + ($n % 5);
            $hours = 3 + ($n % 6);
            $total = $workers * $hours;
            $targetWorkers = $workers * 2;
            $correctHours = $total / $targetWorkers;
            $questions[] = $this->question(
                $workers . ' pekerja menyelesaikan satu pekerjaan dalam ' . $hours . ' jam dengan kecepatan sama. Jika dikerjakan oleh ' . $targetWorkers . ' pekerja, waktu yang diperlukan adalah',
                $correctHours . ' jam',
                [($correctHours + 1) . ' jam', ($correctHours + 2) . ' jam', ($correctHours + 4) . ' jam'],
                'Total beban kerja tetap ' . $total . ' jam-orang.',
                $n
            );
        }

        return $questions;
    }

    private function accuracyQuestions()
    {
        $questions = [];
        $months = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT'];
        $cities = ['Bandung', 'Bogor', 'Cilegon', 'Depok', 'Garut', 'Jakarta', 'Malang', 'Medan', 'Serang', 'Surabaya'];
        $names = ['Aditya', 'Bima', 'Citra', 'Dewi', 'Fajar', 'Gita', 'Hana', 'Indra', 'Johan', 'Karin'];

        // 1. Exact code matching
        for ($number = 1; $number <= 20; $number++) {
            $month = $months[($number - 1) % count($months)];
            $year = 2025 + ($number % 2);
            $reference = sprintf('ISL-%s-%04d-%03d', $month, $year, 100 + ($number * 7));
            $wrongLastDigit = preg_replace('/.$/', (string) (((int) substr($reference, -1) + 1) % 10), $reference);
            $wrongPrefix = str_replace('ISL-', 'ILS-', $reference);
            $wrongYear = str_replace((string) $year, (string) ($year === 2025 ? 2026 : 2025), $reference);
            $options = [$reference, $wrongLastDigit, $wrongPrefix, $wrongYear];
            $shift = ($number - 1) % 4;
            $options = array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
            $questions[] = [
                'text' => 'Pilih kode yang sama persis dengan kode referensi berikut: ' . $reference,
                'options' => $options,
                'answer' => array_search($reference, $options, true),
                'explanation' => 'Jawaban benar harus identik pada seluruh huruf, angka, dan tanda hubung.',
            ];
        }

        // 2. Locate the record with one mismatched character
        for ($number = 1; $number <= 20; $number++) {
            $reference = sprintf('LAB-%02d-%s-%03d', $number, $months[$number % count($months)], 200 + ($number * 3));
            $different = substr_replace($reference, (string) (($number + 5) % 10), -1);
            $options = [$reference, str_replace('LAB-', 'LBA-', $reference), str_replace('-', '/', $reference), $different];
            $shift = ($number + 1) % 4;
            $options = array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
            $questions[] = [
                'text' => 'Dari empat data berikut, pilih kode yang berbeda dari tiga kode lainnya.',
                'options' => $options,
                'answer' => array_search($different, $options, true),
                'explanation' => 'Periksa setiap karakter, termasuk urutan huruf, angka, dan tanda pemisah.',
            ];
        }

        // 3. Date comparison
        for ($number = 1; $number <= 20; $number++) {
            $day = str_pad((string) (($number * 3) % 27 + 1), 2, '0', STR_PAD_LEFT);
            $month = str_pad((string) (($number % 12) + 1), 2, '0', STR_PAD_LEFT);
            $year = 2024 + ($number % 3);
            $reference = $day . '/' . $month . '/' . $year;
            $matching = $reference;
            $options = [$matching, $month . '/' . $day . '/' . $year, $day . '/' . $month . '/' . ($year + 1), $day . '-' . $month . '-' . $year];
            $shift = ($number + 2) % 4;
            $options = array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
            $questions[] = [
                'text' => 'Tanggal pada formulir tertulis ' . $reference . '. Pilih data yang format dan nilainya sama persis.',
                'options' => $options,
                'answer' => array_search($matching, $options, true),
                'explanation' => 'Tanggal, bulan, tahun, dan tanda pemisah harus sama.',
            ];
        }

        // 4. Verify a transaction total
        for ($number = 1; $number <= 20; $number++) {
            $first = 12000 + ($number * 1250);
            $second = 7500 + ($number * 875);
            $third = 3000 + ($number * 425);
            $correct = $first + $second + $third;
            $options = [$correct, $correct + 500, $correct - 750, $correct + 1250];
            $shift = ($number + 3) % 4;
            $options = array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
            $questions[] = [
                'text' => 'Cocokkan total transaksi: Rp' . number_format($first, 0, ',', '.') . ' + Rp' . number_format($second, 0, ',', '.') . ' + Rp' . number_format($third, 0, ',', '.') . ' =',
                'options' => array_map(function ($value) { return 'Rp' . number_format($value, 0, ',', '.'); }, $options),
                'answer' => array_search($correct, $options, true),
                'explanation' => 'Jumlahkan ketiga nominal secara teliti.',
            ];
        }

        // 5. Alphabetical order and data sequence
        for ($number = 1; $number <= 20; $number++) {
            $list = [$names[$number % 10], $names[($number + 3) % 10], $names[($number + 6) % 10], $names[($number + 8) % 10]];
            $correct = $list;
            sort($correct, SORT_STRING);
            $incorrectOne = [$list[1], $list[0], $list[2], $list[3]];
            $incorrectTwo = array_reverse($correct);
            $incorrectThree = [$correct[0], $correct[2], $correct[1], $correct[3]];
            $options = [implode(', ', $correct), implode(', ', $incorrectOne), implode(', ', $incorrectTwo), implode(', ', $incorrectThree)];
            $shift = ($number + 1) % 4;
            $options = array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
            $questions[] = [
                'text' => 'Pilih urutan nama yang benar dari A sampai Z untuk data berikut: ' . implode(', ', $list) . '.',
                'options' => $options,
                'answer' => array_search(implode(', ', $correct), $options, true),
                'explanation' => 'Urutkan nama berdasarkan huruf pertama lalu huruf berikutnya.',
            ];
        }

        return $questions;
    }

    private function integrityQuestions()
    {
        $questions = [];
        $scenarios = [
            ['Anda menemukan angka yang salah pada laporan yang akan dikirim hari ini.', 'Laporkan kesalahan kepada atasan dan perbaiki sebelum laporan dikirim.', 'Biarkan saja karena nilainya kecil.', 'Ubah diam-diam tanpa memberi tahu pihak terkait.', 'Tunggu sampai ada pihak lain yang menyadari.'],
            ['Rekan kerja meminta Anda mengisi absensinya karena ia terlambat.', 'Tolak dengan baik dan minta rekan mengisi absensi sesuai waktu sebenarnya.', 'Isi absensi agar hubungan tetap baik.', 'Minta rekan lain yang mengisi absensi.', 'Hapus data absensi hari itu.'],
            ['Anda menerima dokumen pelanggan yang berisi data rahasia.', 'Simpan dan gunakan data hanya untuk pekerjaan yang berwenang.', 'Bagikan ke teman agar mendapat masukan.', 'Unggah ke penyimpanan pribadi agar mudah diakses.', 'Ceritakan isinya saat istirahat.'],
            ['Vendor menawarkan hadiah setelah Anda membantu proses administrasinya.', 'Tolak atau laporkan sesuai kebijakan gratifikasi perusahaan.', 'Terima selama nilainya tidak besar.', 'Minta hadiah diberikan setelah pekerjaan selesai.', 'Terima lalu bagikan kepada teman satu tim.'],
            ['Anda melihat rekan memakai aset kantor untuk kepentingan pribadi tanpa izin.', 'Sampaikan melalui jalur pelaporan yang sesuai dan faktual.', 'Diam karena bukan urusan Anda.', 'Ikut memakai aset tersebut.', 'Sebarkan kejadian itu di grup informal.'],
            ['Atasan meminta angka hasil kerja dibulatkan agar terlihat memenuhi target.', 'Sampaikan data apa adanya dan minta arahan tertulis bila ada perubahan metode.', 'Ubah angka agar target tercapai.', 'Hapus data yang tidak mendukung target.', 'Tunda laporan tanpa penjelasan.'],
            ['Anda memiliki hubungan keluarga dengan calon vendor yang sedang dievaluasi.', 'Ungkapkan potensi konflik kepentingan dan hindari terlibat dalam penilaian.', 'Tetap menilai karena Anda profesional.', 'Minta keluarga menghubungi tim pengadaan.', 'Sembunyikan hubungan tersebut.'],
            ['Anda salah memasukkan data pada sistem dan kesalahan sudah tersimpan.', 'Segera koreksi sesuai prosedur serta beri tahu pihak yang terdampak.', 'Hapus riwayat agar tidak diketahui.', 'Biarkan karena mungkin tidak ada yang memeriksa.', 'Menyalahkan sistem saat ditanya.'],
            ['Pelanggan meminta Anda melewati satu tahapan pemeriksaan agar proses lebih cepat.', 'Jelaskan bahwa tahapan wajib tetap dijalankan dan tawarkan jalur resmi yang tersedia.', 'Lewati tahap tersebut demi pelayanan cepat.', 'Minta imbalan agar risiko sepadan.', 'Serahkan keputusan tanpa mencatat permintaan pelanggan.'],
            ['Anda menemukan uang lebih pada klaim biaya perjalanan.', 'Kembalikan atau koreksi klaim sesuai bukti yang sebenarnya.', 'Simpan karena perusahaan tidak akan tahu.', 'Bagikan ke anggota tim.', 'Gunakan untuk biaya pribadi lalu perbaiki bulan depan.'],
        ];

        for ($number = 1; $number <= 100; $number++) {
            $scenario = $scenarios[($number - 1) % count($scenarios)];
            $options = array_slice($scenario, 1);
            $shift = ($number - 1) % 4;
            $options = array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
            $questions[] = [
                'text' => 'Situasi: ' . $scenario[0] . ' Tindakan yang paling tepat adalah',
                'options' => $options,
                'answer' => array_search($scenario[1], $options, true),
                'explanation' => 'Integritas menuntut kejujuran, kepatuhan pada prosedur, dan pelaporan yang bertanggung jawab.',
            ];
        }
        return $questions;
    }

    private function question($text, $correct, array $wrong, $explanation, $seed = 0)
    {
        $options = array_merge([$correct], $wrong);
        $shift = $seed % 4;
        $options = array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
        return [
            'text' => $text,
            'options' => $options,
            'answer' => array_search($correct, $options, true),
            'explanation' => $explanation,
        ];
    }
}
