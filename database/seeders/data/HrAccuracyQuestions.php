<?php

/**
 * Bank soal NALAR (100 item) — ketelitian & kecepatan clerical.
 *
 * Konstruk (literatur seleksi personel):
 * - Clerical checking / accuracy test (tradisi Pauli, Kraepelin, TKB)
 * - Attention to detail, coding, matching, verification
 *
 * Return: array of ['text','options','answer','explanation']
 */

$rotate = static function (array $options, int $seed): array {
    $shift = abs($seed) % max(1, count($options));
    return array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
};

$makeChoice = static function (string $text, $correct, array $wrong, string $explanation, int $seed) use ($rotate): array {
    $options = $rotate(array_merge([(string) $correct], array_map('strval', array_slice($wrong, 0, 3))), $seed);
    return [
        'text' => $text,
        'options' => $options,
        'answer' => array_search((string) $correct, $options, true),
        'explanation' => $explanation,
    ];
};

$questions = [];
$months = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
$names = ['Aditya', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fitri', 'Gita', 'Hadi', 'Indah', 'Joko', 'Kartika', 'Lestari', 'Maya', 'Nanda', 'Oki', 'Putri', 'Rizky', 'Sari', 'Taufik', 'Ulfa'];
$cities = ['Bandung', 'Bogor', 'Cilegon', 'Depok', 'Garut', 'Jakarta', 'Malang', 'Medan', 'Serang', 'Surabaya', 'Tangerang', 'Semarang'];

// ── 1. Pencocokan kode dokumen exact (20) ─────────────────────────────────
for ($n = 1; $n <= 20; $n++) {
    $ref = sprintf('ISL-LAB-%s-%04d-%03d', $months[($n - 1) % 12], 2024 + ($n % 2), 100 + ($n * 11));
    $wrong = [
        preg_replace('/\d{3}$/', str_pad((string) ((($n * 11 + 1) % 1000)), 3, '0', STR_PAD_LEFT), $ref),
        str_replace('ISL-LAB', 'ISL-LBA', $ref),
        str_replace('-', '/', $ref),
    ];
    $questions[] = $makeChoice(
        'Pada berkas hasil uji, kode referensi resmi: ' . $ref . '. Pilih data yang identik.',
        $ref,
        $wrong,
        'Perhatikan urutan huruf, angka, dan tanda hubung secara persis.',
        $n
    );
}

// ── 2. Temukan data berbeda (20) ───────────────────────────────────────────
for ($n = 1; $n <= 20; $n++) {
    $ref = sprintf('NR-%02d-%s-%04d', $n, $months[$n % 12], 5000 + ($n * 17));
    $different = substr_replace($ref, 'O', 3, 1);
    $options = [$ref, $different, str_replace('NR-', 'RN-', $ref), strtoupper(strtolower($ref))];
    $rotated = $rotate($options, $n);
    $questions[] = [
        'text' => 'Manakah kode sampel yang TIDAK sama dengan tiga kode lainnya?',
        'options' => $rotated,
        'answer' => array_search($different, $rotated, true),
        'explanation' => 'Bandingkan karakter per karakter, termasuk posisi huruf dan angka.',
    ];
}

// ── 3. Validasi tanggal & format (20) ─────────────────────────────────────
for ($n = 1; $n <= 20; $n++) {
    $day = str_pad((string) ((($n * 2) % 28) + 1), 2, '0', STR_PAD_LEFT);
    $month = str_pad((string) (($n % 12) + 1), 2, '0', STR_PAD_LEFT);
    $year = 2024 + ($n % 2);
    $correct = $day . '/' . $month . '/' . $year;
    $options = [
        $correct,
        $month . '/' . $day . '/' . $year,
        $day . '-' . $month . '-' . $year,
        $day . '/' . $month . '/' . ($year + 1),
    ];
    $rotated = $rotate($options, $n + 2);
    $questions[] = [
        'text' => 'Tanggal sampling tercatat: ' . $correct . '. Pilih format yang benar-benar sama.',
        'options' => $rotated,
        'answer' => array_search($correct, $rotated, true),
        'explanation' => 'Format DD/MM/YYYY dan pemisah harus identik.',
    ];
}

// ── 4. Verifikasi penjumlahan transaksi (20) ──────────────────────────────
for ($n = 1; $n <= 20; $n++) {
    $a = 45000 + ($n * 3200);
    $b = 28000 + ($n * 1800);
    $c = 12000 + ($n * 900);
    $total = $a + $b + $c;
    $options = [$total, $total + 500, $total - 750, $total + 1200];
    $rotated = $rotate($options, $n + 1);
    $questions[] = [
        'text' => 'Verifikasi total: Rp' . number_format($a, 0, ',', '.') . ' + Rp' . number_format($b, 0, ',', '.') . ' + Rp' . number_format($c, 0, ',', '.') . ' =',
        'options' => array_map(fn ($v) => 'Rp' . number_format($v, 0, ',', '.'), $rotated),
        'answer' => array_search($total, $rotated, true),
        'explanation' => 'Jumlahkan ketiga nominal tanpa pembulatan di tengah perhitungan.',
    ];
}

// ── 5. Urutan alfabet & indeks data (20) ──────────────────────────────────
for ($n = 1; $n <= 20; $n++) {
    $pick = [
        $names[$n % 20],
        $names[($n + 4) % 20],
        $names[($n + 9) % 20],
        $names[($n + 13) % 20],
    ];
    $sorted = $pick;
    sort($sorted, SORT_STRING);
    $options = [
        implode(', ', $sorted),
        implode(', ', $pick),
        implode(', ', array_reverse($sorted)),
        implode(', ', [$sorted[1], $sorted[0], $sorted[2], $sorted[3]]),
    ];
    $rotated = $rotate($options, $n);
    $questions[] = [
        'text' => 'Urutkan nama petugas sampling dari A ke Z: ' . implode(', ', $pick) . '.',
        'options' => $rotated,
        'answer' => array_search(implode(', ', $sorted), $rotated, true),
        'explanation' => 'Urutkan berdasarkan abjad nama lengkap.',
    ];
}

return array_slice($questions, 0, 100);
