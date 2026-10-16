<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();

use Illuminate\Support\Facades\DB;

$cat = DB::table('question_categories')->where('name', '16PF')->first();
echo 'question_categories 16PF: ';
if ($cat) {
    echo 'id=' . $cat->id . ', is_active=' . $cat->is_active . ', is_show=' . ($cat->is_show ?? 'n/a') . ', question_count=' . $cat->question_count . PHP_EOL;
} else {
    echo 'TIDAK ADA' . PHP_EOL;
}

$count = DB::table('soal_psikotes')->where('kategori_soal', '16PF')->count();
echo 'soal_psikotes (kategori_soal=16PF): ' . $count . ' baris' . PHP_EOL;

if ($count > 0) {
    $first = DB::table('soal_psikotes')->where('kategori_soal', '16PF')->orderBy('id')->first();
    $last = DB::table('soal_psikotes')->where('kategori_soal', '16PF')->orderByDesc('id')->first();
    $pf = json_decode($first->pertanyaan ?? '{}', true);
    $pl = json_decode($last->pertanyaan ?? '{}', true);
    echo 'Rentang nomor soal: ' . ($pf['no'] ?? '?') . ' - ' . ($pl['no'] ?? '?') . PHP_EOL;
}
