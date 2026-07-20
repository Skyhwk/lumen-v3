<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();

$data = (object) [
    'nama_lengkap' => 'John Doe',
    'nama_jabatan' => 'Software Engineer',
    'posisi_di_lamar' => 'Developer',
    'umur' => '28',
    'alamat_domisili' => 'Jakarta Selatan',
    'no_hp' => '081234567890',
    'email' => 'john@example.com',
    'foto_selfie' => '',
    'nama_cabang' => 'Jakarta',
    'shio' => 'Dragon',
    'elemen' => 'Wood',
    'salary_user' => 15000000,
    'nama_hrd' => 'HRD Admin',
    'kepercayaan_diri' => 'Baik',
    'kemampuan_komunikasi' => 'Baik',
    'antusias_perusahaan' => 'Tinggi',
    'pengetahuan_perusahaan' => 'Cukup',
    'pengetahuan_jobs' => 'Baik',
    'motivasi_kerja' => 'Tinggi',
    'kesimpulan' => 'Recommended',
    'catatan' => 'Kandidat potensial',
    'kebangsaan' => 'Indonesia',
    'tempat_lahir' => 'Jakarta',
    'gender' => 'Laki-laki',
    'status_nikah' => 'Belum Menikah',
    'tgl_nikah' => '-',
    'tempat_nikah' => '-',
    'bpjs_kesehatan' => '-',
    'orang_dalam' => 'Tidak',
    'nik_ktp' => '3174010101010001',
    'tanggal_lahir' => '1998-01-01',
    'agama' => 'Islam',
    'nama_panggilan' => 'John',
    'tgl_exp_identitas' => '2030-01-01',
    'bpjs_ketenagakerjaan' => '-',
    'tinggi_badan' => '170',
    'berat_badan' => '65',
    'mata' => 'Normal',
    'golongan_darah' => 'O',
    'penyakit_bawaan_lahir' => 'Tidak',
    'penyakit_kronis' => 'Tidak',
    'riwayat_kecelakaan' => 'Tidak',
    'alamat_ktp' => 'Jakarta',
    'no_hp' => '081234567890',
    'pendidikan' => json_encode([['jenjang' => 'S1', 'jurusan' => 'Informatika', 'institusi' => 'Universitas X', 'tahun_masuk' => '2016', 'tahun_lulus' => '2020']]),
    'pengalaman_kerja' => json_encode([['posisi_kerja' => 'Developer', 'nama_perusahaan' => 'PT ABC', 'mulai_kerja' => '2020', 'akhir_kerja' => '2024', 'alasan_keluar' => 'Career growth']]),
    'skill' => json_encode([['keahlian' => 'PHP', 'rate' => '4']]),
    'skill_bahasa' => json_encode([['bahasa' => 'Indonesia', 'baca' => 'Baik', 'tulis' => 'Baik', 'dengar' => 'Baik', 'bicara' => 'Baik']]),
    'organisasi' => null,
    'sertifikat' => null,
    'kursus' => null,
    'tglInter' => '2026-07-25',
    'hariIndonesia' => 'Jumat',
    'alamat' => 'Jl. Contoh No. 1, Jakarta',
];

$btn = (object) [
    'approve' => 'https://example.com/approve',
    'reject' => 'https://example.com/reject',
    'keep' => 'https://example.com/keep',
];

$outDir = __DIR__ . '/../storage/email-preview';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$templates = [
    'permohonan-ibu' => \App\Services\GenerateMessageHRD::bodyEmailKeepApproveKandidat($data, $btn, 'Ibu Boss'),
    'permohonan-bapak' => \App\Services\GenerateMessageHRD::bodyEmailKeepApproveKandidat($data, $btn, 'Bapak Boss'),
    'approve-director' => \App\Services\GenerateMessageHRD::bodyEmailApproveBapakBoss($data),
    'approve-candidate' => \App\Services\GenerateMessageHRD::bodyEmailApproveOSCalon($data),
];

foreach ($templates as $name => $html) {
    file_put_contents("$outDir/$name.html", $html);
    echo "Generated: $name.html (" . strlen($html) . " bytes)\n";
}

echo "Done.\n";
