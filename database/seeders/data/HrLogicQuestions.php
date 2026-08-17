<?php

/**
 * Bank soal LOGIKA (100 item) — penalaran kognitif rekrutmen.
 *
 * Konstruk (literatur seleksi personel):
 * - Numerical / logical reasoning (TIU, IST, aptitude test)
 * - Deductive & syllogistic reasoning (verbal reasoning)
 * - Verbal analogies
 * - Work-rate & business numeracy
 * - Abstract / letter-series reasoning
 *
 * Return: array of ['text','options','answer','explanation']
 */

$rotate = static function (array $options, int $seed): array {
    $shift = abs($seed) % max(1, count($options));
    return array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
};

$make = static function (string $text, $correct, array $wrong, string $explanation, int $seed) use ($rotate): array {
    $options = $rotate(array_merge([(string) $correct], array_map('strval', array_slice($wrong, 0, 3))), $seed);
    return [
        'text' => $text,
        'options' => $options,
        'answer' => array_search((string) $correct, $options, true),
        'explanation' => $explanation,
    ];
};

$questions = [];

// ── 1. Deret angka (20) — numerical series ───────────────────────────────
for ($n = 1; $n <= 20; $n++) {
    $type = $n % 4;
    if ($type === 0) {
        $start = 4 + ($n * 2);
        $step = 3 + ($n % 5);
        $vals = [$start, $start + $step, $start + 2 * $step, $start + 3 * $step];
        $correct = $start + 4 * $step;
        $questions[] = $make(
            'Deret angka: ' . implode(', ', $vals) . ', ... Bilangan berikutnya adalah',
            (string) $correct,
            [(string) ($correct - $step), (string) ($correct + 1), (string) ($correct + $step)],
            'Pola aritmetika dengan selisih konstan ' . $step . '.',
            $n
        );
    } elseif ($type === 1) {
        $a = 2 + ($n % 6);
        $vals = [$a, $a * 2, $a * 4, $a * 8];
        $correct = $a * 16;
        $questions[] = $make(
            'Deret angka: ' . implode(', ', $vals) . ', ... Bilangan berikutnya adalah',
            (string) $correct,
            [(string) ($a * 10), (string) ($a * 12), (string) ($a * 14)],
            'Setiap suku bernilai dua kali suku sebelumnya (geometri).',
            $n + 20
        );
    } elseif ($type === 2) {
        $base = 10 + $n;
        $vals = [$base, $base + 1, $base + 4, $base + 9];
        $correct = $base + 16;
        $questions[] = $make(
            'Deret angka: ' . implode(', ', $vals) . ', ... Bilangan berikutnya adalah',
            (string) $correct,
            [(string) ($base + 15), (string) ($base + 17), (string) ($base + 20)],
            'Kenaikan selisih mengikuti kuadrat: +1, +3, +5, +7, ...',
            $n + 40
        );
    } else {
        $x = 50 + ($n * 3);
        $y = $x - 7;
        $z = $y - 7;
        $w = $z - 7;
        $correct = $w - 7;
        $questions[] = $make(
            'Deret angka: ' . $x . ', ' . $y . ', ' . $z . ', ' . $w . ', ... Bilangan berikutnya adalah',
            (string) $correct,
            [(string) ($correct + 7), (string) ($correct - 14), (string) ($w + 7)],
            'Selisih antar suku konstan (−7).',
            $n + 60
        );
    }
}

// ── 2. Silogisme / penalaran deduktif (20) — verbal deductive ─────────────
$syllogisms = [
    ['Semua karyawan administrasi wajib mengikuti briefing pagi.', 'Sebagian staf arsip adalah karyawan administrasi.', 'Sebagian staf arsip wajib mengikuti briefing pagi.', 'Semua staf arsip wajib briefing.', 'Tidak ada staf arsip yang wajib briefing.', 'Semua staf arsip adalah karyawan administrasi.'],
    ['Semua analis lab memakai jas lab saat bekerja.', 'Sebagian analis lab ditugaskan ke shift malam.', 'Sebagian analis shift malam memakai jas lab.', 'Semua analis shift malam memakai jas lab.', 'Tidak ada analis shift malam memakai jas lab.', 'Semua shift malam adalah analis lab.'],
    ['Semua surat keluar harus disetujui manajer.', 'Sebagian memo internal termasuk surat keluar.', 'Sebagian memo internal harus disetujui manajer.', 'Semua memo internal harus disetujui manajer.', 'Tidak ada memo internal perlu persetujuan.', 'Semua memo bukan surat keluar.'],
    ['Semua karyawan yang lembur mendapat uang makan.', 'Sebagian staf gudang lembur bulan ini.', 'Sebagian staf gudang lembur mendapat uang makan.', 'Semua staf gudang lembur mendapat uang makan.', 'Tidak ada staf gudang lembur dapat uang makan.', 'Semua staf gudang selalu lembur.'],
    ['Semua sampel uji harus diberi kode unik.', 'Sebagian sampel hari ini dari pelanggan korporat.', 'Sebagian sampel pelanggan korporat harus diberi kode unik.', 'Semua sampel pelanggan korporat harus diberi kode unik.', 'Tidak ada sampel pelanggan korporat perlu kode.', 'Semua sampel pelanggan korporat sudah tanpa kode.'],
    ['Tidak ada dokumen rahasia boleh dibawa pulang.', 'Sebagian berkas proyek bersifat rahasia.', 'Sebagian berkas proyek tidak boleh dibawa pulang.', 'Semua berkas proyek tidak boleh dibawa pulang.', 'Semua berkas proyek boleh dibawa pulang.', 'Tidak ada berkas proyek bersifat rahasia.'],
    ['Semua teknisi kalibrasi memiliki sertifikat kompetensi.', 'Budi adalah teknisi kalibrasi.', 'Budi memiliki sertifikat kompetensi.', 'Semua karyawan memiliki sertifikat kompetensi.', 'Budi bukan teknisi kalibrasi.', 'Tidak ada teknisi kalibrasi bersertifikat.'],
    ['Sebagian karyawan marketing bekerja remote.', 'Semua karyawan remote wajib lapor harian.', 'Sebagian karyawan marketing wajib lapor harian.', 'Semua karyawan marketing wajib lapor harian.', 'Tidak ada karyawan marketing lapor harian.', 'Semua karyawan marketing bekerja remote.'],
    ['Semua hasil uji out-of-spec wajib ditahan.', 'Sample X hasil uji out-of-spec.', 'Sample X wajib ditahan.', 'Semua sample wajib ditahan.', 'Sample X tidak out-of-spec.', 'Tidak ada sample out-of-spec.'],
    ['Semua pelamar yang lulus assessment lanjut interview.', 'Dina lulus assessment.', 'Dina lanjut ke interview.', 'Semua pelamar lanjut interview.', 'Dina tidak lulus assessment.', 'Dina tidak perlu interview.'],
];
for ($n = 0; $n < 20; $n++) {
    $s = $syllogisms[$n % count($syllogisms)];
    $questions[] = $make(
        $s[0] . ' ' . $s[1] . ' Kesimpulan yang paling tepat adalah',
        $s[2],
        [$s[3], $s[4], $s[5]],
        'Kesimpulan logis hanya boleh ditarik pada himpunan yang memenuhi premis.',
        100 + $n
    );
}

// ── 3. Analogi verbal (20) — verbal reasoning ─────────────────────────────
$analogies = [
    ['Analis : Laboratorium', 'Manajer :', 'Kantor', 'Gudang', 'Jalan', 'Rumah Sakit'],
    ['SOP : Prosedur', 'NDA :', 'Kerahasiaan', 'Gaji', 'Cuti', 'Transportasi'],
    ['Kalibrasi : Akurasi', 'Training :', 'Kompetensi', 'Biaya', 'Absensi', 'Parkir'],
    ['Sampel : Pengujian', 'CV :', 'Seleksi', 'Makan', 'Libur', 'Cuaca'],
    ['Audit : Kepatuhan', 'Interview :', 'Penilaian', 'Belanja', 'Renovasi', 'Olahraga'],
    ['Reagen : Kimia', 'Alat ukur :', 'Metrologi', 'Desain', 'Marketing', 'Musik'],
    ['KPI : Target', 'SLA :', 'Layanan', 'Hobi', 'Warna', 'Rasa'],
    ['Onboarding : Karyawan baru', 'Induction :', 'Orientasi', 'Pensiun', 'Resign', 'PHK'],
    ['QC : Mutu', 'HR :', 'Sumber daya manusia', 'Keuangan pribadi', 'Cuaca', 'Politik'],
    ['Batch record : Produksi', 'LAF :', 'Hasil uji', 'Gaji', 'Absen', 'Parkir'],
    ['Whistleblowing : Pelaporan', 'Grievance :', 'Pengaduan', 'Promosi', 'Bonus', 'Libur'],
    ['Probation : Evaluasi awal', 'Assessment :', 'Seleksi kemampuan', 'Pensiun', 'Cuti tahunan', 'THR'],
    ['Competency matrix : Skill', 'Job description :', 'Tugas', 'Gaji', 'Uniform', 'Kantin'],
    ['Chain of custody : Jejak sampel', 'Audit trail :', 'Jejak data', 'Menu kantin', 'Jadwal shift', 'Cuaca'],
    ['Conflict of interest : Netralitas', 'Gratifikasi :', 'Integritas', 'Fashion', 'Musik', 'Sport'],
    ['Reference material : Standar', 'Control chart :', 'Stabilitas proses', 'Harga saham', 'Film', 'Novel'],
    ['Personnel request : Kebutuhan SDM', 'Purchase order :', 'Pengadaan', 'Hobi', 'Wisata', 'Game'],
    ['Verifikasi : Validasi data', 'Review :', 'Pemeriksaan', 'Makan siang', 'Istirahat', 'Tidur'],
    ['Near-miss : Pencegahan', 'Corrective action :', 'Perbaikan', 'Hiburan', 'Fashion', 'Travel'],
    ['Integrity test : Kejujuran', 'Aptitude test :', 'Kemampuan', 'Zodiak', 'Horoskop', 'Untung-untungan'],
];
for ($n = 0; $n < 20; $n++) {
    $a = $analogies[$n];
    $questions[] = $make(
        'Analogi: ' . $a[0] . ' = ' . $a[1],
        $a[2],
        [$a[3], $a[4], $a[5]],
        'Relasi konsep A:B harus paralel dengan C:jawaban.',
        200 + $n
    );
}

// ── 4. Penalaran numerik bisnis (20) — numerical reasoning ────────────────
for ($n = 1; $n <= 20; $n++) {
    $subtype = $n % 3;
    if ($subtype === 0) {
        $workers = 3 + ($n % 5);
        $hours = 4 + ($n % 4);
        $total = $workers * $hours;
        $newWorkers = $workers + 2;
        $newHours = round($total / $newWorkers, 1);
        $questions[] = $make(
            $workers . ' staff menyelesaikan entri order dalam ' . $hours . ' jam (kecepatan sama). Jika ditambah menjadi ' . $newWorkers . ' staff, estimasi waktu penyelesaian adalah',
            $newHours . ' jam',
            [($newHours + 1) . ' jam', ($newHours + 2) . ' jam', max(1, $newHours - 1) . ' jam'],
            'Total jam-orang = ' . $total . '; waktu berbandung terbalik dengan jumlah pekerja.',
            300 + $n
        );
    } elseif ($subtype === 1) {
        $base = 200000 + ($n * 15000);
        $pct = [8, 10, 12, 15, 20, 25][$n % 6];
        $after = (int) round($base * (100 - $pct) / 100);
        $questions[] = $make(
            'Biaya pengujian Rp' . number_format($base, 0, ',', '.') . ' diberi diskon kontrak ' . $pct . '%. Nominal yang harus dibayar pelanggan adalah',
            'Rp' . number_format($after, 0, ',', '.'),
            [
                'Rp' . number_format($after + 20000, 0, ',', '.'),
                'Rp' . number_format($base, 0, ',', '.'),
                'Rp' . number_format(max(0, $after - 20000), 0, ',', '.'),
            ],
            'Nominal akhir = harga × ' . (100 - $pct) . '%.',
            320 + $n
        );
    } else {
        $mon = 120 + ($n * 8);
        $tue = 95 + ($n * 6);
        $wed = 110 + ($n * 5);
        $avg = round(($mon + $tue + $wed) / 3, 0);
        $questions[] = $make(
            'Jumlah sample masuk lab: Senin ' . $mon . ', Selasa ' . $tue . ', Rabu ' . $wed . '. Rata-rata per hari adalah',
            (string) $avg,
            [(string) ($avg + 5), (string) ($avg - 8), (string) ($mon + $tue + $wed)],
            'Rata-rata = total dibagi 3 hari.',
            340 + $n
        );
    }
}

// ── 5. Pola huruf & abstrak (20) — abstract / letter reasoning ─────────────
$letterPatterns = [
    ['A, C, E, G, ...', 'I', ['H', 'J', 'K'], 'Lompat dua huruf alfabet (A→C→E→G→I).'],
    ['B, D, F, H, ...', 'J', ['I', 'K', 'L'], 'Deret huruf genap alfabet (+2).'],
    ['Z, X, V, T, ...', 'R', ['S', 'Q', 'P'], 'Deret mundur −2 huruf.'],
    ['A, D, G, J, ...', 'M', ['L', 'N', 'O'], 'Loncat +3 huruf.'],
    ['M, J, G, D, ...', 'A', ['B', 'C', 'E'], 'Loncat mundur −3 huruf.'],
];
for ($n = 0; $n < 20; $n++) {
    $p = $letterPatterns[$n % count($letterPatterns)];
    $questions[] = $make(
        'Pola huruf: ' . $p[0] . ' Huruf berikutnya adalah',
        $p[1],
        $p[2],
        $p[3],
        400 + $n
    );
}

// Safety: pastikan tepat 100
return array_slice($questions, 0, 100);
