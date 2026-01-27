<?php

namespace App\Services;

use App\Models\LhpsAirHeader;
use App\Models\LhpsAirDetail;
use App\Models\OrderDetail;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use Illuminate\Support\Facades\DB;
use App\Services\MpdfService as PDF;
use Carbon\Carbon;

class TemplateLhpp
{
    protected $pdf;
    protected $data;
    protected $fileName;

    // public function lhpp_psikologi($data, $data_detail, $mode_download, $cfr)
    // {
    //     $data->tanggal_rilis_lhp = Carbon::parse($data->tanggal_rilis_lhp)->format('Y-m-d');
    //     $pengesahanLhp = PengesahanLhp::where('berlaku_mulai', '<=', $data->tanggal_rilis_lhp)
    //     ->orderByDesc('berlaku_mulai')
    //     ->first();  

    //     $qr_img = '';
    //     $qr_img_k3 = '';
    //     $tanggal_qr = '';
    //     if (!is_null($data->file_qr) && !is_null($data->file_qr_k3)) {
    //         $file_qr = public_path('qr_documents/' . $data->file_qr);
    //         $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
    //         $file_qr_k3 = public_path('qr_documents/' . $data->file_qr_k3);
    //         $qr_img_k3 = '<img src="' . $file_qr_k3 . '" width="60px" height="60px" style="margin-top: 10px;">';
    //         $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_rilis_lhp);
    //     } else {
    //         $file_qr = public_path('qr_documents/' . $data->file_qr);
    //         $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
    //         $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_rilis_lhp);
    //     }
    //     $html = '<div style="padding:20px">';

    //     // Header laporan dengan text center
    //     $html = '<div style="text-align:center; margin-bottom: 30px;">';
    //     $html .= '<h2 style="margin-bottom: 5px;">LAPORAN HASIL PENGUJIAN</h2>';
    //     $html .= '<h4 style="margin-top: 0; margin-bottom: 5px;">PT INTI SURYA LABORATORIUM</h4>';
    //     $html .= '<p style="margin-top: 0; font-style: italic;">Icon Business Park Blok O No. 5-6, Sampora, Kec. Cisauk, Tangerang, Banten 15345</p>';
    //     $html .= '</div>';

    //     $html .= '<p style="font-size: 10px;">DATA UMUM</p>';
    //     $html .= '<table style="width:100%; border-collapse: collapse; font-size: 10px;">';
    //     $html .= '<tr><td style="width:5%; border:none;">a.</td><td style="width:35%; border:none;">Perusahaan</td><td style="width:2%; border:none;">:</td><td style="border:none;">' . ($data['nama_perusahaan'] ?? '-') . '</td></tr>';
    //     $html .= '<tr><td style="border:none;">b.</td><td style="border:none;">Alamat</td><td style="border:none;">:</td><td style="border:none;">' . ($data['alamat_perusahaan'] ?? '-') . '</td></tr>';
    //     if ($mode_download != 'downloadLHP') {
    //         $html .= '<tr><td style="border:none;">c.</td><td style="border:none;">Pengurus/Penanggungjawab</td><td style="border:none;">:</td><td style="border:none;">' . ($data['penanggung_jawab'] ?? '-') . '</td></tr>';
    //     }
    //     $html .= '<tr><td style="border:none;">d.</td><td style="border:none;">Lokasi Pemeriksaan/Pengujian</td><td style="border:none;">:</td><td style="border:none;">' . ($data['lokasi_pemeriksaan'] ?? '-') . '</td></tr>';
    //     if ($mode_download != 'downloadLHP') {
    //         $html .= '<tr><td style="border:none;">e.</td><td style="border:none;">Nomor Dokumen Pengujian Sebelumnya</td><td style="border:none;">:</td><td style="border:none;">' . ($cfr ?? '-') . '</td></tr>';
    //         $html .= '<tr><td style="border:none;">f.</td><td style="border:none;">Nomor SKP PJK3/Bidang</td><td style="border:none;">:</td><td style="border:none;">' . ($data['no_skp_pjk3'] ?? '-') . '</td></tr>';
    //         $html .= '<tr><td style="border:none;">g.</td><td style="border:none;">Nomor SKP Ahli K3</td><td style="border:none;">:</td><td style="border:none;">' . ($data['no_skp_ahli_k3'] ?? '-') . '</td></tr>';
    //     }
    //     $html .= '</table>';

    //     $html .= '<br><p style="font-size: 10px;">PEMERIKSAAN DAN/ATAU PENGUJIAN TEKNIS</p>';
    //     $html .= '<table style="width:100%; border-collapse: collapse; font-size: 10px;">';
    //     $html .= '<tr><td style="width:5%; border:none;">a.</td><td style="width:35%; border:none;">Tanggal Pemeriksaan/Pengujian/Pengukuran</td><td style="width:2%;border:none;">:</td><td style="border:none;">' . self::tanggal_indonesia($data['tanggal_pemeriksaan'] ?? '-') . '</td></tr>';
    //     if ($mode_download != 'downloadLHP') {
    //         $html .= '<tr><td style="border:none;">b.</td><td style="border:none;">Waktu Pemeriksaan/Pengujian/Pengukuran</td><td>:</td><td style="border:none;">' . ($data['waktu_pemeriksaan'] ?? '-') . '</td></tr>';
    //     }
    //     $html .= '</table>';

    //     $html .= '<br><p style="font-size: 10px;">PEMERIKSAAN DAN/ATAU PENGUJIAN TEKNIS</p>';
    //     $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse; font-size: 10px;">';
    //     $html .= '<thead><tr>';
    //     $html .= '<th>No</th><th>No Titik</th><th>Jenis Pekerjaan</th><th>Kategori Stress</th><th>Nilai</th><th>Kesimpulan</th>';
    //     if ($mode_download != 'downloadLHP') {
    //         $html .= '<th>Tindakan Pengendalian yang Telah Dilakukan</th>';
    //     }
    //     $html .= '</tr></thead><tbody>';

    //     $count = 1;
    //     $divisiGroups = [];

    //     // dd($data_detail);

    //     // Kelompokkan data berdasarkan divisi
    //     foreach ($data_detail as $item) {
    //         $divisi = $item['divisi'] ?? '-';
    //         $divisiGroups[$divisi][] = $item;
    //     }

    //     foreach ($divisiGroups as $divisi => $items) {
    // // Bagi item jadi chunk: 1 item pertama, lalu potongan 4-4
    //         $chunks = array_chunk(array_slice($items, 1), 4); // potong mulai dari index ke-1
    //         array_unshift($chunks, array_slice($items, 0, 1)); // taruh 1 item pertama di depan

    //         foreach ($chunks as $chunkItems) {
    //             $totalRowsForDivisi = 0;

    //             // hitung row untuk divisi (khusus chunk ini)
    //             foreach ($chunkItems as $item) {
    //                 $hasil = json_decode($item['hasil'], true);
    //                 if (!$hasil) continue;
    //                 if (isset($hasil['kategori_stress'])) $hasil = [$hasil];
    //                 $totalRowsForDivisi += count($hasil);
    //             }

    //             $printedDivisi = false;

    //             foreach ($chunkItems as $item) {
    //                 $hasil = json_decode($item['hasil'], true);
    //                 if (!$hasil) continue;
    //                 if (isset($hasil['kategori_stress'])) $hasil = [$hasil];

    //                 foreach ($hasil as $idx => $value) {
    //                     $html .= '<tr style="page-break-inside: avoid;">';

    //                     if ($idx == 0) {
    //                         $html .= '<td rowspan="' . count($hasil) . '">' . $count . '</td>';
    //                         $html .= '<td rowspan="' . count($hasil) . '">' . ($item['no_sampel'] ?? '-') . '</td>';

    //                         if (!$printedDivisi) {
    //                             $html .= '<td rowspan="' . $totalRowsForDivisi . '" style="text-align:center;">' . $divisi . '</td>';
    //                             $printedDivisi = true;
    //                         }
    //                     }

    //                     $html .= '<td style="text-align:center;">' . ($value['kategori_stress'] ?? '-') . '</td>';
    //                     $html .= '<td style="text-align:center;">' . ($value['nilai'] ?? '-') . '</td>';
    //                     $html .= '<td style="text-align:center;">' . ($value['kesimpulan'] ?? '-') . '</td>';

    //                     if ($idx == 0 && $mode_download != 'downloadLHP') {
    //                         $html .= '<td rowspan="' . count($hasil) . '">' . ($item['tindakan'] ?? '-') . '</td>';
    //                     }

    //                     $html .= '</tr>';
    //                 }

    //                 $count++;
    //             }
    //         }
    //     }




    //     $divisiCount = [];

    //     foreach ($data_detail as $item) {
    //         $divisi = $item['divisi'];
    //         $hasil = json_decode($item['hasil'], true); // hasil adalah array dari objek: kategori_stress, nilai, kesimpulan

    //         if (!isset($divisiCount[$divisi])) {
    //             $divisiCount[$divisi] = [
    //                 'divisi' => $divisi,
    //                 'jumlah_pekerja' => 0,
    //                 'detail' => [], // ini menampung per kategori_stress
    //             ];
    //         }

    //         // Tambah jumlah pekerja
    //         $divisiCount[$divisi]['jumlah_pekerja']++;

    //         foreach ($hasil as $result) {
    //             $kategori_stress = strtolower(trim($result['kategori_stress']));
    //             $kesimpulan = strtoupper(trim($result['kesimpulan'])); // RINGAN/SEDANG/BERAT

    //             // Pastikan detail kategori_stress sudah diinisialisasi
    //             if (!isset($divisiCount[$divisi]['detail'][$kategori_stress])) {
    //                 $divisiCount[$divisi]['detail'][$kategori_stress] = [
    //                     'ringan' => 0,
    //                     'sedang' => 0,
    //                     'berat' => 0,
    //                 ];
    //             }

    //             // Tambahkan berdasarkan kesimpulan
    //             if ($kesimpulan === 'RINGAN') {
    //                 $divisiCount[$divisi]['detail'][$kategori_stress]['ringan']++;
    //             } elseif ($kesimpulan === 'SEDANG') {
    //                 $divisiCount[$divisi]['detail'][$kategori_stress]['sedang']++;
    //             } elseif ($kesimpulan === 'BERAT') {
    //                 $divisiCount[$divisi]['detail'][$kategori_stress]['berat']++;
    //             }
    //         }
    //     }

    //     $divisiCount = array_values($divisiCount); // reset key divisi menjadi numerik
    //     // dd($data['no_order']);

    //     $html .= '</tbody></table>';
    //     $html .= '</div>';
    //     if ($mode_download == 'downloadLHP') {
    //         $html .= '<table style="border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 10px;">';
    //         $html .= '<tr>';
    //         $html .= '<td style="width: 30%; vertical-align: top; padding: 8px;">';
    //         $html .= '<b>Kesimpulan</b><br>';
    //         $html .= '<table style="border-collapse: collapse; width: 100%; margin-top: 5px;">';
    //         $html .= '<tr>';
    //         $html .= '<td style="border: 1px solid #000; padding: 4px;"><strong>[Skor ≤ 9]</strong></td>';
    //         $html .= '<td style="border: 1px solid #000; padding: 4px;">: derajat stres RINGAN</td>';
    //         $html .= '</tr>';
    //         $html .= '<tr>';
    //         $html .= '<td style="border: 1px solid #000; padding: 4px;"><strong>Skor 10-24</strong></td>';
    //         $html .= '<td style="border: 1px solid #000; padding: 4px;">: derajat stres SEDANG</td>';
    //         $html .= '</tr>';
    //         $html .= '<tr>';
    //         $html .= '<td style="border: 1px solid #000; padding: 4px;"><strong>Skor > 24</strong></td>';
    //         $html .= '<td style="border: 1px solid #000; padding: 4px;">: derajat stres BERAT</td>';
    //         $html .= '</tr>';
    //         $html .= '</table>';
    //         $html .= '</td>';

    //         $html .= '<td style="vertical-align: top; padding: 8px;">';
    //         $html .= '<p class="mb-3">1 <b>Ketaksaan Peran</b>: Terjadi ketika pekerja tidak memahami dengan jelas tugas, tanggung jawab, dan harapan dalam pekerjaannya.<br></p>';
    //         $html .= '<p class="mb-3">2 <b>Konflik Peran</b>: Muncul saat pekerja menerima dua atau lebih tuntutan tugas yang saling bertentangan dan sulit dijalankan bersamaan.<br></p>';
    //         $html .= '<p class="mb-3">3 <b>Beban Kerja Berlebih Kuantitatif</b>: Terjadi saat karyawan mendapat terlalu banyak tugas, hingga tidak cukup waktu untuk menyelesaikannya dengan baik.<br></p>';
    //         $html .= '<p class="mb-3">4 <b>Beban Kerja Berlebih Kualitatif</b>: Terjadi saat karyawan diberi tugas yang terlalu sulit atau rumit yang melebihi kemampuan atau keahlian yang mereka miliki.<br></p>';
    //         $html .= '<p class="mb-3">5 <b>Pengembangan Karir</b>: Proses karyawan untuk belajar, berkembang, dan merencanakan masa depan pekerjaannya sesuai dengan tujuan dan kemampuan.<br></p>';
    //         $html .= '<p class="mb-3">6 <b>Tanggung Jawab terhadap Orang Lain</b>: Sadar dan berkomitmen untuk menjalankan tugas dengan baik, menghormati hak orang lain, dan memikirkan dampak dari tindakan kita terhadap mereka.</p>';
    //         $html .= '</td>';
    //         $html .= '</tr>';
    //         $html .= '</table>';
    //     }



    //     $html .= '<table style="border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 10px;">';
    //     $html .= '<tr>';
    //     $html .= '<td style="width: 30%; vertical-align: top;"><p>METODE PENGUKURAN YANG DIPAKAI</p></td>';
    //     $html .= '<td style="width: 70%; vertical-align: top; text-align: justify"><div>';
    //     $html .= '<p>a. Rumus Penentuan Jumlah Responden Pada Pedoman PerMeNaKer No. 5 Tahun 2018 untuk menentukan jumlah responden dalam kegiatan pengukuran psikologi kerja.</p>';
    //     $html .= '<p>b. Metode SDS (Survei Diagnosis Stress) untuk Penentuan tingkat risiko stres akibat sumber-sumber penyebab stres di tempat kerja.</p>';
    //     $html .= '<div></td>';
    //     $html .= '</tr>';
    //     $html .= '</table>';

    //     $html .= '<div style="width: 100%; overflow: hidden; margin-bottom: 20px;">';

    //     // Kolom Kiri (judul ANALISIS)
    //     $html .= '<div style="width: 30%; float: left; vertical-align: top;">';
    //     $html .= '<p>ANALISIS</p>';
    //     $html .= '</div>';

    //     // Kolom Kanan (isi penjelasan)
    //     $html .= '<div style="width: 70%; float: left; vertical-align: top;">';
    //     $html .= '<p style="text-align: justify; font-size: 10px">Dalam pelaksanaannya, jumlah responden dihitung menggunakan rumus Slovin, sebagai berikut</p>';
    //     $html .= '<p style="text-align: justify; font-size: 10px">n = N / (1+(N x e2) </p>';
    //     $html .= '<p style="text-align: justify; font-size: 10px"></p>';
    //     $html .= '<p style="text-align: justify; font-size: 10px">keterangan</p>';
    //     $html .= '<p style="text-align: justify; font-size: 10px"> n : Jumlah responden</p>';
    //     $html .= '<p style="text-align: justify; font-size: 10px"> N : Jumlah populasi</p>';
    //     $html .= '<p style="text-align: justify; font-size: 10px"> e2 : Tingkat kepercayaan 10%</p>';

    //     $no = 1;

    //     foreach ($divisiCount as $key => $item) {
    //         $html .= '<p style="font-weight: bold; margin-top: 20px; text-align: justify; font-size: 10px">' . ($key + 1) . '. Divisi ' . $item['divisi'] . ' (' . $item['jumlah_pekerja'] . ' Jumlah Responden).</p>';
    //         // $html .= '<p style="margin-top: 10px; text-align: justify; font-size: 10px">a. Jumlah Populasi: ' . $item['jumlah_pekerja'] . ' Orang</p>';
    //         $html .= '<p style="margin-top: 10px; text-align: justify; font-size: 10px">a. Jumlah Responden: ' . $item['jumlah_pekerja'] . ' Orang</p>';

    //         $total = $item['jumlah_pekerja'];
    //         $jumlah_kategori = count($item['detail']);
    //         $total_ringan = 0;
    //         $total_sedang = 0;
    //         $total_berat = 0;
    //         $kategori_sedang_lebih_banyak = [];
    //         $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse; font-size: 10px;">';
    //         $html .= '<thead><tr>';
    //         $html .= '<th style="width: 30%; text-align: left !important">Kategori</th>';
    //         $html .= '<th style="text-align: left !important">Ringan</th>';
    //         $html .= '<th style="text-align: left !important">Ringan %</th>';
    //         $html .= '<th style="text-align: left !important">Sedang</th>';
    //         $html .= '<th style="text-align: left !important">Sedang %</th>';
    //         $html .= '<th style="text-align: left !important">Berat</th>';
    //         $html .= '<th style="text-align: left !important">Berat %</th>';
    //         $html .= '</tr></thead>';
    //         $html .= '<tbody>';

    //         foreach ($item['detail'] as $kategori_stress => $item2) {
    //             // dd($item2);
    //             $ringan = $item2['ringan'];
    //             $sedang = $item2['sedang'];
    //             $berat = $item2['berat'];

    //             $persen_ringan = $total > 0 ? round(($ringan / $total) * 100, 2) : 0;
    //             $persen_sedang = $total > 0 ? round(($sedang / $total) * 100, 2) : 0;
    //             $persen_berat = $total > 0 ? round(($berat / $total) * 100, 2) : 0;

    //             $judul_kategori = ucwords(str_replace('_', ' ', $kategori_stress));
    //             $html .= "<tr>";
    //             $html .= "<td style='width: 30%'>{$judul_kategori}</td>";
    //             $html .= "<td>{$ringan}</td>";
    //             $html .= "<td style='font-weight: bold'>{$persen_ringan}%</td>";
    //             $html .= "<td>{$sedang}</td>";
    //             $html .= "<td style='font-weight: bold'>{$persen_sedang}%</td>";
    //             $html .= "<td>{$berat}</td>";
    //             $html .= "<td style='font-weight: bold'>{$persen_berat}%</td>";
    //             $html .= "</tr>";
    //             // $html .= "<p style='text-align: justify'>{$no}. {$judul_kategori}, pekerja dengan kategori stres ringan sebanyak {$ringan} orang ({$persen_ringan}%), stres sedang {$sedang} orang ({$persen_sedang}%), dan stres berat {$berat} orang ({$persen_berat}%).</p>";
    //             $no++;

    //             $total_ringan += $ringan;
    //             $total_sedang += $sedang;
    //             $total_berat += $berat;

    //             if ($persen_sedang > $persen_ringan) {
    //                 $kategori_sedang_lebih_banyak[] = $judul_kategori;
    //             }
    //         }



    //         // Hitung total responden * kategori
    //         $total_per_kategori = $total * $jumlah_kategori;

    //         $avg_ringan = $total_per_kategori > 0 ? round(($total_ringan / $total_per_kategori) * 100, 2) : 0;
    //         $avg_sedang = $total_per_kategori > 0 ? round(($total_sedang / $total_per_kategori) * 100, 2) : 0;
    //         $avg_berat = $total_per_kategori > 0 ? round(($total_berat / $total_per_kategori) * 100, 2) : 0;

    //         $html .= "<tr style='font-weight: bold'>";
    //         $html .= "<td style='width: 30%; font-weight: bold'>RATA-RATA (%)</td>";
    //         $html .= "<td colspan='2' style='text-align: center !important; font-weight: bold'>{$avg_ringan}%</td>";
    //         $html .= "<td colspan='2' style='text-align: center !important; font-weight: bold'>{$avg_sedang}%</td>";
    //         $html .= "<td colspan='2' style='text-align: center !important; font-weight: bold'>{$avg_berat}%</td>";
    //         $html .= "</tr>";

    //         $html .= '</tbody>';
    //         $html .= '</table>';
    //         if ($mode_download != 'downloadLHP') {
    //             $html .= '<p><strong>Jumlah rata-rata persentase stres pada analisis pengukuran psikologi bagian Logistik sebanyak ' . $total . ' orang menunjukkan stres sedang lebih banyak dari stres ringan, dan adanya stres berat.</strong></p>';
    //         }
    //     }

    //     // === Kesimpulan Dinamis ===
    //     $divisiMemenuhi = [];
    //     $divisiTidakMemenuhi = [];

    //     foreach ($divisiCount as $item) {
    //         $total = $item['jumlah_pekerja'];
    //         $total_sedang = 0;
    //         $total_berat = 0;

    //         foreach ($item['detail'] as $item2) {
    //             $total_sedang += $item2['sedang'];
    //             $total_berat += $item2['berat'];
    //         }

    //         $persen_sedang = $total > 0 ? ($total_sedang / ($total * count($item['detail']))) * 100 : 0;
    //         $persen_berat = $total > 0 ? ($total_berat / ($total * count($item['detail']))) * 100 : 0;

    //         // Kriteria: stres berat == 0 dan stres sedang < 50% => Memenuhi
    //         if ($persen_berat == 0 && $persen_sedang < 50) {
    //             $divisiMemenuhi[] = $item['divisi'] . ' (' . $item['jumlah_pekerja'] . ' orang jumlah responden)';
    //         } else {
    //             $divisiTidakMemenuhi[] = $item['divisi'] . ' (' . $item['jumlah_pekerja'] . ' orang jumlah responden)';
    //         }
    //     }

    //     $html .= '</div>'; // tutup kolom kanan
    //     $html .= '</div>'; // tutup row utama


    //     if ($mode_download != 'downloadLHP') {
    //         $html .= '<table style="border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 10px;">';
    //         $html .= '<tr><td style="width: 30%; vertical-align: top;">';
    //         $html .= '<p style="margin: 0;">KESIMPULAN</p></td>';
    //         $html .= '<td style="width: 70%; vertical-align: top; text-align: justify">';
    //         if (!empty($divisiMemenuhi)) {
    //             $html .= '<p style="margin: 0 0 10px 0;">Berdasarkan hasil pengukuran dan analisis, dapat disimpulkan bahwa pengujian psikologi pada bagian <strong style="color: red;">' . implode(', ', $divisiMemenuhi) . ' telah memenuhi Standar. </strong></p>';
    //         }

    //         if (!empty($divisiTidakMemenuhi)) {
    //             $html .= '<p style="margin: 0 0 10px 0;">Adapun yang belum memenuhi Standar adalah pada bagian <strong style="color: red;">' . implode(', ', $divisiTidakMemenuhi) . '</strong>.</p>';
    //         }

    //         $html .= '<p style="margin: 0;"><strong style="color: red;">Perusahaan berkewajiban untuk melaksanakan riksa uji lingkungan kerja Psikologi kembali.</strong></p></td></tr>';

    //         $html .= '<tr><td style="width: 30%; vertical-align: top;">';
    //         $html .= '<p style="margin: 20px 0 0 0;">Persyaratan yang harus segera dipenuhi</p></td>';
    //         $html .= '<td style="width: 70%; vertical-align: top; text-align: justify">';
    //         $html .= '<p style="margin: 20px 0 0 0;">-</p></td></tr>';
    //         $html .= '</table>';
    //     }

    //     $ttd = '
    //         <table style="margin-top: 5px; font-family: Helvetica, sans-serif; font-size: 9px; width: 100%; border-collapse: collapse;">
    //             <tr>';

    //     if ($mode_download == 'downloadLHPP') {
    //         // Kolom Kiri 30% kosong
    //         $ttd .= '<td style="width: 30%; vertical-align: top;"></td>';
            
    //         // Kolom Kanan 70% berisi TTD
    //         $ttd .= '
    //             <td style="width: 70%; vertical-align: top;">
    //                 <table style="width: 100%; border-collapse: collapse;">
    //                     <tr>
    //                         <td style="width: 50%; text-align: center; vertical-align: top; padding: 0;">
    //                             <p style="margin: 0 0 5px 0; font-size: 9px;">' . $tanggal_qr . '</p>
    //                             <div style="height: 50px;"></div>
    //                             <p style="margin: 0; font-size: 10px; font-weight: bold;">'. $pengesahanLhp->nama_karyawan  .'</p>
    //                             <p style="margin: 0; font-size: 10px; font-weight: bold;">'. $pengesahanLhp->jabatan_karyawan  .'</p>
    //                         </td>
    //                         <td style="width: 50%; text-align: center; vertical-align: top; padding: 0;">
    //                             <p style="margin: 0 0 5px 0; font-size: 9px;">' . $tanggal_qr . '</p>
    //                             <p style="margin: 0 0 5px 0; font-size: 9px;">Yang Memeriksa dan Menguji<br>Ahli K3 Lingkungan Kerja Muda</p>
    //                             <div style="height: 50px;"></div>
    //                             <p style="margin: 0; font-size: 10px; font-weight: bold;">' . $data->nama_skp_ahli_k3 . '</p>
    //                             <p style="margin: 0; font-size: 10px; font-weight: bold;">' . $data->no_skp_ahli_k3 . '</p>
    //                         </td>
    //                     </tr>
    //                 </table>
    //             </td>
    //         </tr>
    //     </table>';
    //     } elseif ($mode_download == 'downloadLHP') {
    //         // Kolom Kiri 30% kosong
    //         $ttd .= '<td style="width: 30%; vertical-align: top;"></td>';
            
    //         // Kolom Kanan 70% berisi TTD dengan QR
    //         $ttd .= '
    //             <td style="width: 70%; vertical-align: top; text-align: center;">
    //                 <p style="margin: 0 0 5px 0; font-size: 9px;">' . $tanggal_qr . '</p>
    //                 <div>' . $qr_img . '</div>
    //             </td>
    //         </tr>
    //     </table>';
    //     }

    //     $no_lhp = str_replace("/", "-", $cfr);

    //     if ($mode_download == 'downloadLHPP') {
    //         $name = 'LHPP-' . $no_lhp . '.pdf';
    //     } else if ($mode_download == 'downloadLHP') {
    //         $name = 'LHP-' . $no_lhp . '.pdf';
    //     } else {
    //         return null;
    //     }
    //     // dd("lolos");
    //     $ress = self::formatTemplate($html, $name, $ttd, $qr_img, $mode_download, $custom2 = null);
    //     return $ress;
    // }

    public function lhpp_psikologi($data, $data_detail, $mode_download, $cfr)
    {
        // Initialize data
        $data->tanggal_rilis_lhp = Carbon::parse($data->tanggal_rilis_lhp)->format('Y-m-d');
        $pengesahanLhp = PengesahanLhp::where('berlaku_mulai', '<=', $data->tanggal_rilis_lhp)
            ->orderByDesc('berlaku_mulai')
            ->first();

        // Prepare QR images and date
        $qrData = $this->prepareQrData($data);
        
        // Build HTML content
        $header = $this->buildHeader('downloadLHPFinal', false);
        $html   = $this->buildDataUmum($data, $cfr, $mode_download);
        $html   .= $this->buildPemeriksaanSection($data, $mode_download);
        $html   .= $this->buildPengujianTeknis($data_detail, $mode_download);
        
        if ($mode_download == 'downloadLHP') {
            $html .= $this->buildKesimpulanLHP();
        }
        
        $html .= $this->buildMetodePengukuran();
        
        // Build analysis section
        $divisiCount = $this->groupDataByDivisi($data_detail);
        $html .= $this->buildAnalysis($divisiCount, $mode_download);
        
        // Build conclusion section
        if ($mode_download != 'downloadLHP') {
            $kesimpulan = $this->generateKesimpulan($divisiCount);
            $html .= $this->buildKesimpulanSection($kesimpulan);
        }
        
        // Build signature section
        $ttd = $this->buildSignatureSection($mode_download, $qrData, $pengesahanLhp, $data);
        $html .= $ttd;
        // Generate PDF
        return $this->generatePdf($html, $ttd, $cfr, $mode_download, $qrData['qr_img'], $header);
    }

    // ===============================================
    // HELPER METHODS
    // ===============================================

    /**
     * Prepare QR code data
     */
    private function prepareQrData($data)
    {
        $qr_img = '';
        $qr_img_k3 = '';
        $tanggal_qr = '';
        
        if (!is_null($data->file_qr)) {
            $file_qr = public_path('qr_documents/' . $data->file_qr);
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_rilis_lhp);
        }
        
        if (!is_null($data->file_qr_k3)) {
            $file_qr_k3 = public_path('qr_documents/' . $data->file_qr_k3);
            $qr_img_k3 = '<img src="' . $file_qr_k3 . '" width="60px" height="60px" style="margin-top: 10px;">';
        }
        
        return [
            'qr_img' => $qr_img,
            'qr_img_k3' => $qr_img_k3,
            'tanggal_qr' => $tanggal_qr
        ];
    }

    /**
     * Build header section
     */
    private function buildHeader($mode, $showKan = false)
    {
        $logoISL = public_path('img/isl_logo.png');
        $logoKAN = public_path('img/logo_kan.png');

        // MODE: downloadWSDraft & downloadLHP
        if ($mode == 'downloadWSDraft' || $mode == 'downloadLHP') {
            return '
                <table width="100%" style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td style="text-align: center;">
                            <span style="font-weight: bold; border-bottom: 1px solid #000;">LAPORAN HASIL PENGUJIAN</span>
                        </td>
                    </tr>
                </table>
            ';
        }

        // MODE: downloadLHPFinal
        if ($mode == 'downloadLHPFinal') {
            return '
                <table width="100%" style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td style="width: 33.33%; text-align: left; padding-left: 30px; vertical-align: top;">
                            <img src="'.$logoISL.'" alt="ISL" style="height: 40px;">
                        </td>

                        <td style="width: 33.33%; text-align: center; vertical-align: middle;">
                            <span style="font-weight: bold; border-bottom: 1px solid #000;">LAPORAN HASIL PENGUJIAN</span>
                        </td>

                        '.(
                            $showKan
                            ? '<td style="width: 33.33%; text-align: right; padding-right: 50px; height: 50px;">
                                    <img src="'.$logoKAN.'" alt="KAN" style="height: 50px;">
                            </td>'
                            : '<td style="width: 33.33%; text-align: right; padding-right: 50px; height: 55px;"></td>'
                        ).'
                    </tr>
                </table>
            ';
        }

        return '';
    }

    /**
     * Build data umum section
     */
    private function buildDataUmum($data, $cfr, $mode_download)
    {
        $html = '<p style="font-size: 10px;">DATA UMUM</p>';
        $html .= '<table style="width:100%; border-collapse: collapse; font-size: 10px;">';
        
        $rows = [
            ['a.', 'Perusahaan', $data['nama_perusahaan'] ?? '-'],
            ['b.', 'Alamat', $data['alamat_perusahaan'] ?? '-'],
        ];
        
        if ($mode_download != 'downloadLHP') {
            $rows[] = ['c.', 'Pengurus/Penanggungjawab', $data['penanggung_jawab'] ?? '-'];
            $rows[] = ['d.', 'Lokasi Pemeriksaan/Pengujian', $data['lokasi_pemeriksaan'] ?? '-'];
        } else {
            $rows[] = ['c.', 'Lokasi Pemeriksaan/Pengujian', $data['lokasi_pemeriksaan'] ?? '-'];
        }
        
        
        if ($mode_download != 'downloadLHP') {
            $rows[] = ['e.', 'Nomor Dokumen Pengujian Sebelumnya', $cfr ?? '-'];
            $rows[] = ['f.', 'Nomor SKP PJK3/Bidang', $data['no_skp_pjk3'] ?? '-'];
            $rows[] = ['g.', 'Nomor SKP Ahli K3', $data['no_skp_ahli_k3'] ?? '-'];
        }
        
        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td style="width:5%; border:none;">' . $row[0] . '</td>';
            $html .= '<td style="width:35%; border:none;">' . $row[1] . '</td>';
            $html .= '<td style="width:2%; border:none;">:</td>';
            $html .= '<td style="border:none;">' . $row[2] . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        return $html;
    }

    /**
     * Build pemeriksaan section
     */
    private function buildPemeriksaanSection($data, $mode_download)
    {
        $html = '<br><p style="font-size: 10px;">PEMERIKSAAN DAN/ATAU PENGUJIAN TEKNIS</p>';
        $html .= '<table style="width:100%; border-collapse: collapse; font-size: 10px;">';
        
        $tanggal = self::tanggal_indonesia($data['tanggal_pemeriksaan'] ?? '-');
        $html .= '<tr>';
        $html .= '<td style="width:5%; border:none;">a.</td>';
        $html .= '<td style="width:35%; border:none;">Tanggal Pemeriksaan/Pengujian/Pengukuran</td>';
        $html .= '<td style="width:2%;border:none;">:</td>';
        $html .= '<td style="border:none;">' . $tanggal . '</td>';
        $html .= '</tr>';
        
        if ($mode_download != 'downloadLHP') {
            $waktu = $data['waktu_pemeriksaan'] ?? '-';
            $html .= '<tr>';
            $html .= '<td style="border:none;">b.</td>';
            $html .= '<td style="border:none;">Waktu Pemeriksaan/Pengujian/Pengukuran</td>';
            $html .= '<td>:</td>';
            $html .= '<td style="border:none;">' . $waktu . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        return $html;
    }

    /**
     * Build pengujian teknis table
     */
    private function buildPengujianTeknis($data_detail, $mode_download)
    {
        $html = '<br><p style="font-size: 10px;">PEMERIKSAAN DAN/ATAU PENGUJIAN TEKNIS</p>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse; font-size: 10px;">';
        
        // Table header
        $html .= '<thead><tr>';
        $html .= '<th>No</th><th>No Titik</th><th>Jenis Pekerjaan</th><th>Kategori Stress</th><th>Nilai</th><th>Kesimpulan</th>';
        if ($mode_download != 'downloadLHP') {
            $html .= '<th>Tindakan Pengendalian yang Telah Dilakukan</th>';
        }
        $html .= '</tr></thead><tbody>';
        
        // Table body
        $html .= $this->buildTableRows($data_detail, $mode_download);
        
        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Build table rows for pengujian teknis
     */
    private function buildTableRows($data_detail, $mode_download)
    {
        $html = '';
        $count = 1;
        $divisiGroups = $this->groupByDivisi($data_detail);
        
        foreach ($divisiGroups as $divisi => $items) {
            $chunks = $this->chunkItems($items);
            
            foreach ($chunks as $chunkItems) {
                $totalRowsForDivisi = $this->calculateTotalRows($chunkItems);
                $printedDivisi = false;
                
                foreach ($chunkItems as $item) {
                    $hasil = json_decode($item['hasil'], true);
                    if (!$hasil) continue;
                    
                    if (isset($hasil['kategori_stress'])) {
                        $hasil = [$hasil];
                    }
                    
                    foreach ($hasil as $idx => $value) {
                        $html .= '<tr style="page-break-inside: avoid;">';
                        
                        if ($idx == 0) {
                            $html .= '<td rowspan="' . count($hasil) . '">' . $count . '</td>';
                            $html .= '<td rowspan="' . count($hasil) . '">' . ($item['no_sampel'] ?? '-') . '</td>';
                            
                            if (!$printedDivisi) {
                                $html .= '<td rowspan="' . $totalRowsForDivisi . '" style="text-align:center;">' . $divisi . '</td>';
                                $printedDivisi = true;
                            }
                        }
                        
                        $html .= '<td style="text-align:center;">' . ($value['kategori_stress'] ?? '-') . '</td>';
                        $html .= '<td style="text-align:center;">' . ($value['nilai'] ?? '-') . '</td>';
                        $html .= '<td style="text-align:center;">' . ($value['kesimpulan'] ?? '-') . '</td>';
                        
                        if ($idx == 0 && $mode_download != 'downloadLHP') {
                            $html .= '<td rowspan="' . count($hasil) . '">' . ($item['tindakan'] ?? '-') . '</td>';
                        }
                        
                        $html .= '</tr>';
                    }
                    
                    $count++;
                }
            }
        }
        
        return $html;
    }

    /**
     * Group data by divisi
     */
    private function groupByDivisi($data_detail)
    {
        $divisiGroups = [];
        
        foreach ($data_detail as $item) {
            $divisi = $item['divisi'] ?? '-';
            $divisiGroups[$divisi][] = $item;
        }
        
        return $divisiGroups;
    }

    /**
     * Chunk items: 1 item pertama, lalu potongan 4-4
     */
    private function chunkItems($items)
    {
        $chunks = array_chunk(array_slice($items, 1), 4);
        array_unshift($chunks, array_slice($items, 0, 1));
        return $chunks;
    }

    /**
     * Calculate total rows for divisi
     */
    private function calculateTotalRows($chunkItems)
    {
        $totalRows = 0;
        
        foreach ($chunkItems as $item) {
            $hasil = json_decode($item['hasil'], true);
            if (!$hasil) continue;
            
            if (isset($hasil['kategori_stress'])) {
                $hasil = [$hasil];
            }
            
            $totalRows += count($hasil);
        }
        
        return $totalRows;
    }

    /**
     * Build kesimpulan section for LHP mode
     */
    private function buildKesimpulanLHP()
    {
        $html = '<table style="border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 10px;">';
        $html .= '<tr>';
        
        // Left column - Kesimpulan stress levels
        $html .= '<td style="width: 30%; vertical-align: top; padding-right: 10px;">';
        $html .= '<b>Kesimpulan</b><br>';
        $html .= '<table style="border-collapse: collapse; width: 100%; margin-top: 5px;">';
        
        $kesimpulanData = [
            ['[Skor ≤ 9]', 'derajat stres RINGAN'],
            ['Skor 10-24', 'derajat stres SEDANG'],
            ['Skor > 24', 'derajat stres BERAT']
        ];
        
        foreach ($kesimpulanData as $row) {
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid #000; padding: 4px;"><strong>' . $row[0] . '</strong></td>';
            $html .= '<td style="border: 1px solid #000; padding: 4px;">: ' . $row[1] . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table></td>';
        
        // Right column - Kategori definitions
        $html .= '<td style="vertical-align: top;">';
        
        $kategoriData = [
            'Ketaksaan Peran' => 'Terjadi ketika pekerja tidak memahami dengan jelas tugas, tanggung jawab, dan harapan dalam pekerjaannya.',
            'Konflik Peran' => 'Muncul saat pekerja menerima dua atau lebih tuntutan tugas yang saling bertentangan dan sulit dijalankan bersamaan.',
            'Beban Kerja Berlebih Kuantitatif' => 'Terjadi saat karyawan mendapat terlalu banyak tugas, hingga tidak cukup waktu untuk menyelesaikannya dengan baik.',
            'Beban Kerja Berlebih Kualitatif' => 'Terjadi saat karyawan diberi tugas yang terlalu sulit atau rumit yang melebihi kemampuan atau keahlian yang mereka miliki.',
            'Pengembangan Karir' => 'Proses karyawan untuk belajar, berkembang, dan merencanakan masa depan pekerjaannya sesuai dengan tujuan dan kemampuan.',
            'Tanggung Jawab terhadap Orang Lain' => 'Sadar dan berkomitmen untuk menjalankan tugas dengan baik, menghormati hak orang lain, dan memikirkan dampak dari tindakan kita terhadap mereka.'
        ];
        
        $counter = 1;
        foreach ($kategoriData as $title => $desc) {
            $html .= '<p class="mb-3">' . $counter . '. <b>' . $title . '</b>: ' . $desc . '<br></p>';
            $counter++;
        }
        
        $html .= '</td></tr></table>';
        return $html;
    }

    /**
     * Build metode pengukuran section
     */
    private function buildMetodePengukuran()
    {
        $html = '<table style="border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 10px;">';
        $html .= '<tr>';
        $html .= '<td style="width: 30%; vertical-align: top;"><p>METODE PENGUKURAN YANG DIPAKAI</p></td>';
        $html .= '<td style="width: 70%; vertical-align: top; text-align: justify"><div>';
        $html .= '<p>a. Rumus Penentuan Jumlah Responden Pada Pedoman PerMeNaKer No. 5 Tahun 2018 untuk menentukan jumlah responden dalam kegiatan pengukuran psikologi kerja.</p>';
        $html .= '<p>b. Metode SDS (Survei Diagnosis Stress) untuk Penentuan tingkat risiko stres akibat sumber-sumber penyebab stres di tempat kerja.</p>';
        $html .= '</div></td>';
        $html .= '</tr></table>';
        
        return $html;
    }

    /**
     * Group data by divisi for analysis
     */
    private function groupDataByDivisi($data_detail)
    {
        $divisiCount = [];
        
        foreach ($data_detail as $item) {
            $divisi = $item['divisi'];
            $hasil = json_decode($item['hasil'], true);
            
            if (!isset($divisiCount[$divisi])) {
                $divisiCount[$divisi] = [
                    'divisi' => $divisi,
                    'jumlah_pekerja' => 0,
                    'detail' => [],
                ];
            }
            
            $divisiCount[$divisi]['jumlah_pekerja']++;
            
            foreach ($hasil as $result) {
                $kategori_stress = strtolower(trim($result['kategori_stress']));
                $kesimpulan = strtoupper(trim($result['kesimpulan']));
                
                if (!isset($divisiCount[$divisi]['detail'][$kategori_stress])) {
                    $divisiCount[$divisi]['detail'][$kategori_stress] = [
                        'ringan' => 0,
                        'sedang' => 0,
                        'berat' => 0,
                    ];
                }
                
                if ($kesimpulan === 'RINGAN') {
                    $divisiCount[$divisi]['detail'][$kategori_stress]['ringan']++;
                } elseif ($kesimpulan === 'SEDANG') {
                    $divisiCount[$divisi]['detail'][$kategori_stress]['sedang']++;
                } elseif ($kesimpulan === 'BERAT') {
                    $divisiCount[$divisi]['detail'][$kategori_stress]['berat']++;
                }
            }
        }
        
        return array_values($divisiCount);
    }

    /**
     * Build analysis section
     */
    private function buildAnalysis($divisiCount, $mode_download)
    {
        $html = '<div style="width: 100%; overflow: hidden; margin-bottom: 20px;">';
        
        // Left column - Title
        $html .= '<div style="width: 30%; float: left; vertical-align: top;">';
        $html .= '<p style="font-size: 10px">ANALISIS</p>';
        $html .= '</div>';
        
        // Right column - Content
        $html .= '<div style="width: 70%; float: left; vertical-align: top;">';
        $html .= $this->buildAnalysisIntro();
        $html .= $this->buildDivisiAnalysis($divisiCount, $mode_download);
        $html .= '</div>';
        
        // PENTING: Clear float di sini
        $html .= '<div style="clear: both;"></div>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Build analysis introduction
     */
    private function buildAnalysisIntro()
    {
        $html = '<p style="text-align: justify; font-size: 10px">Dalam pelaksanaannya, jumlah responden dihitung menggunakan rumus Slovin, sebagai berikut</p>';
        $html .= '<p style="text-align: justify; font-size: 10px">n = N / (1+(N x e2))</p>';
        $html .= '<p style="text-align: justify; font-size: 10px"></p>';
        $html .= '<p style="text-align: justify; font-size: 10px">keterangan</p>';
        $html .= '<p style="text-align: justify; font-size: 10px">n : Jumlah responden</p>';
        $html .= '<p style="text-align: justify; font-size: 10px">N : Jumlah populasi</p>';
        $html .= '<p style="text-align: justify; font-size: 10px">e2 : Tingkat kepercayaan 10%</p>';
        
        return $html;
    }

    /**
     * Build divisi analysis tables
     */
    private function buildDivisiAnalysis($divisiCount, $mode_download)
    {
        $html = '';
        
        foreach ($divisiCount as $key => $item) {
            $nomor = $key + 1;
            $divisi = $item['divisi'];
            $jumlah = $item['jumlah_pekerja'];
            
            $html .= '<p style="font-weight: bold; margin-top: 20px; text-align: justify; font-size: 10px">';
            $html .= $nomor . '. Divisi ' . $divisi . ' (' . $jumlah . ' Jumlah Responden).';
            $html .= '</p>';
            
            $html .= '<p style="margin-top: 10px; text-align: justify; font-size: 10px">';
            $html .= 'a. Jumlah Responden: ' . $jumlah . ' Orang';
            $html .= '</p>';
            
            $html .= $this->buildDivisiTable($item, $mode_download);
        }
        
        return $html;
    }

    /**
     * Build divisi table with stress categories
     */
    private function buildDivisiTable($item, $mode_download)
    {
        $total = $item['jumlah_pekerja'];
        $jumlah_kategori = count($item['detail']);
        
        $html = '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse; font-size: 10px;">';
        $html .= '<thead><tr>';
        $html .= '<th style="width: 30%; text-align: left !important">Kategori</th>';
        $html .= '<th style="text-align: left !important">Ringan</th>';
        $html .= '<th style="text-align: left !important">Ringan %</th>';
        $html .= '<th style="text-align: left !important">Sedang</th>';
        $html .= '<th style="text-align: left !important">Sedang %</th>';
        $html .= '<th style="text-align: left !important">Berat</th>';
        $html .= '<th style="text-align: left !important">Berat %</th>';
        $html .= '</tr></thead><tbody>';
        
        $total_ringan = 0;
        $total_sedang = 0;
        $total_berat = 0;
        
        foreach ($item['detail'] as $kategori_stress => $item2) {
            $ringan = $item2['ringan'];
            $sedang = $item2['sedang'];
            $berat = $item2['berat'];
            
            $persen_ringan = $total > 0 ? round(($ringan / $total) * 100, 2) : 0;
            $persen_sedang = $total > 0 ? round(($sedang / $total) * 100, 2) : 0;
            $persen_berat = $total > 0 ? round(($berat / $total) * 100, 2) : 0;
            
            $judul_kategori = ucwords(str_replace('_', ' ', $kategori_stress));
            
            $html .= "<tr>";
            $html .= "<td style='width: 30%'>{$judul_kategori}</td>";
            $html .= "<td>{$ringan}</td>";
            $html .= "<td style='font-weight: bold'>{$persen_ringan}%</td>";
            $html .= "<td>{$sedang}</td>";
            $html .= "<td style='font-weight: bold'>{$persen_sedang}%</td>";
            $html .= "<td>{$berat}</td>";
            $html .= "<td style='font-weight: bold'>{$persen_berat}%</td>";
            $html .= "</tr>";
            
            $total_ringan += $ringan;
            $total_sedang += $sedang;
            $total_berat += $berat;
        }
        
        // Calculate average
        $total_per_kategori = $total * $jumlah_kategori;
        $avg_ringan = $total_per_kategori > 0 ? round(($total_ringan / $total_per_kategori) * 100, 2) : 0;
        $avg_sedang = $total_per_kategori > 0 ? round(($total_sedang / $total_per_kategori) * 100, 2) : 0;
        $avg_berat = $total_per_kategori > 0 ? round(($total_berat / $total_per_kategori) * 100, 2) : 0;
        
        $html .= "<tr style='font-weight: bold'>";
        $html .= "<td style='width: 30%; font-weight: bold'>RATA-RATA (%)</td>";
        $html .= "<td colspan='2' style='text-align: center !important; font-weight: bold'>{$avg_ringan}%</td>";
        $html .= "<td colspan='2' style='text-align: center !important; font-weight: bold'>{$avg_sedang}%</td>";
        $html .= "<td colspan='2' style='text-align: center !important; font-weight: bold'>{$avg_berat}%</td>";
        $html .= "</tr>";
        
        $html .= '</tbody></table>';
        
        if ($mode_download != 'downloadLHP') {
            $html .= '<p><strong>Jumlah rata-rata persentase stres pada analisis pengukuran psikologi bagian ' . $item['divisi'] . ' sebanyak ' . $total . ' orang menunjukkan stres sedang lebih banyak dari stres ringan, dan adanya stres berat.</strong></p>';
        }
        
        return $html;
    }

    /**
     * Generate kesimpulan based on divisi data
     */
    private function generateKesimpulan($divisiCount)
    {
        $divisiMemenuhi = [];
        $divisiTidakMemenuhi = [];
        
        foreach ($divisiCount as $item) {
            $total = $item['jumlah_pekerja'];
            $total_sedang = 0;
            $total_berat = 0;
            
            foreach ($item['detail'] as $item2) {
                $total_sedang += $item2['sedang'];
                $total_berat += $item2['berat'];
            }
            
            $persen_sedang = $total > 0 ? ($total_sedang / ($total * count($item['detail']))) * 100 : 0;
            $persen_berat = $total > 0 ? ($total_berat / ($total * count($item['detail']))) * 100 : 0;
            
            $divisiLabel = $item['divisi'] . ' (' . $item['jumlah_pekerja'] . ' orang jumlah responden)';
            
            if ($persen_berat == 0 && $persen_sedang < 50) {
                $divisiMemenuhi[] = $divisiLabel;
            } else {
                $divisiTidakMemenuhi[] = $divisiLabel;
            }
        }
        
        return [
            'memenuhi' => $divisiMemenuhi,
            'tidak_memenuhi' => $divisiTidakMemenuhi
        ];
    }

    /**
     * Build kesimpulan section
     */
    private function buildKesimpulanSection($kesimpulan)
    {
        $html = '<table style="border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 10px;">';
        $html .= '<tr><td style="width: 30%; vertical-align: top;">';
        $html .= '<p style="margin: 0;">KESIMPULAN</p></td>';
        $html .= '<td style="width: 70%; vertical-align: top; text-align: justify">';
        
        if (!empty($kesimpulan['memenuhi'])) {
            $divisiMemenuhi = implode(', ', $kesimpulan['memenuhi']);
            $html .= '<p style="margin: 0 0 10px 0;">Berdasarkan hasil pengukuran dan analisis, dapat disimpulkan bahwa pengujian psikologi pada bagian <strong style="color: red;">' . $divisiMemenuhi . ' telah memenuhi Standar.</strong></p>';
        }
        
        if (!empty($kesimpulan['tidak_memenuhi'])) {
            $divisiTidakMemenuhi = implode(', ', $kesimpulan['tidak_memenuhi']);
            $html .= '<p style="margin: 0 0 10px 0;">Adapun yang belum memenuhi Standar adalah pada bagian <strong style="color: red;">' . $divisiTidakMemenuhi . '</strong>.</p>';
        }
        
        $html .= '<p style="margin: 0;"><strong style="color: red;">Perusahaan berkewajiban untuk melaksanakan riksa uji lingkungan kerja Psikologi kembali.</strong></p></td></tr>';
        
        $html .= '<tr><td style="width: 30%; vertical-align: top;">';
        $html .= '<p style="margin: 20px 0 0 0;">Persyaratan yang harus segera dipenuhi</p></td>';
        $html .= '<td style="width: 70%; vertical-align: top; text-align: justify">';
        $html .= '<p style="margin: 20px 0 0 0;">-</p></td></tr>';
        $html .= '</table>';
        
        return $html;
    }

    /**
     * Build signature section
     */
    private function buildSignatureSection($mode_download, $qrData, $pengesahanLhp, $data)
    {
        $ttd = '<table style="margin-top: 5px; font-family: Helvetica, sans-serif; font-size: 9px; width: 100%; border-collapse: collapse;"><tr>';
        
        if ($mode_download == 'downloadLHPP') {
            $ttd .= $this->buildLHPPSignature($qrData, $pengesahanLhp, $data);
        } elseif ($mode_download == 'downloadLHP') {
            $ttd .= $this->buildLHPSignature($qrData);
        }
        
        $ttd .= '</tr></table>';
        
        return $ttd;
    }

    /**
     * Build LHPP signature (with two signatures)
     */
    private function buildLHPPSignature($qrData, $pengesahanLhp, $data)
    {
        return '
            <td style="width: 30%; vertical-align: top;"></td>
            <td style="width: 70%; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 50%; text-align: center; vertical-align: top; padding: 0;">
                            <p style="margin: 0 0 5px 0; font-size: 9px;">' . $qrData['tanggal_qr'] . '</p>
                            <div style="height: 50px;"></div>
                            <p style="margin: 0; font-size: 10px; font-weight: bold;">' . $pengesahanLhp->nama_karyawan . '</p>
                            <p style="margin: 0; font-size: 10px; font-weight: bold;">' . $pengesahanLhp->jabatan_karyawan . '</p>
                        </td>
                        <td style="width: 50%; text-align: center; vertical-align: top; padding: 0;">
                            <p style="margin: 0 0 5px 0; font-size: 9px;">' . $qrData['tanggal_qr'] . '</p>
                            <p style="margin: 0 0 5px 0; font-size: 9px;">Yang Memeriksa dan Menguji<br>Ahli K3 Lingkungan Kerja Muda</p>
                            <div style="height: 50px;"></div>
                            <p style="margin: 0; font-size: 10px; font-weight: bold;">' . $data->nama_skp_ahli_k3 . '</p>
                            <p style="margin: 0; font-size: 10px; font-weight: bold;">' . $data->no_skp_ahli_k3 . '</p>
                        </td>
                    </tr>
                </table>
            </td>';
    }

    /**
     * Build LHP signature (with QR code only)
     */
    private function buildLHPSignature($qrData)
    {
        return '
            <td style="width: 70%; vertical-align: top;"></td>
            <td style="width: 30%; vertical-align: top; text-align: center;">
                <p style="margin: 0 0 5px 0; font-size: 9px;">' . $qrData['tanggal_qr'] . '</p>
                <div>' . $qrData['qr_img'] . '</div>
            </td>';
    }

    /**
     * Generate PDF document
     */
    private function generatePdf($html, $ttd, $cfr, $mode_download, $qr_img, $header)
    {
        $no_lhp = str_replace("/", "-", $cfr);
        
        if ($mode_download == 'downloadLHPP') {
            $name = 'LHPP-' . $no_lhp . '.pdf';
        } elseif ($mode_download == 'downloadLHP') {
            $name = 'LHP-' . $no_lhp . '.pdf';
        } else {
            return null;
        }
        return self::formatTemplate($html, $name, $ttd, $qr_img, $mode_download, $header);
    }

    private function formatTemplate($bodi, $filename, $ttd, $qr_img, $mode_download, $header)
    {
        $mpdfConfig = array(
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => ($mode_download == 'downloadLHPP' ? 10 : 10),
            'margin_bottom' => 22,
            'margin_footer' => 8,
            'margin_top' => 23.5, //23.5
            'margin_left' => 10,
            'margin_right' => 10,
            // 'orientation' => 'P',
            'orientation' => 'L',
        );

        $pdf = new PDF($mpdfConfig);
        $pdf->SetProtection(
            ['print'], // hanya boleh print
            '',        // user password kosong (bisa dibuka tanpa password)
            'skyhwk12',
            128,       // level enkripsi 128-bit
            [
                'copy' => false,
                'modify' => false,
                'print' => true,
                'annot-forms' => false,
                'fill-forms' => false,
                'extract' => false,
                'assemble' => false,
                'print-highres' => true
            ]
        );
        $stylesheet = " .custom {
                            padding: 3px;
                            text-align: center;
                            border: 1px solid #000000;
                            font-weight: bold;
                            font-size: 9px;
                        }
                        .custom {
                            padding: 3px;
                            text-align: center;
                            border: 1px solid #000000;
                            font-size: 9px;
                        }
                        .custom1 {
                            padding: 3px;
                            text-align: left;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #5b5b5b;
                            font-size: 9px;
                        }
                        .custom2 {
                            padding: 3px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #5b5b5b;
                            font-size: 9px;
                        }
                        .custom3 {
                            padding: 3px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .custom4 {
                            padding: 3px;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .custom5 {
                            text-align: left;
                        }
                        .custom6 {
                            padding: 3px;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                        }
                        .custom7 {
                            text-align: center;
                            border: 1px solid #000000;
                            font-weight: bold;
                            font-size: 9px;
                        }
                        .custom8 {
                            text-align: center;
                            font-style: italic;
                            border: 1px solid #000000;
                            font-weight: bold;
                            font-size: 9px;
                        }
                        .custom9 {
                            padding: 3px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .pd-5-dot-center {
                            padding: 8px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #000000;
                            font-size: 9px;
                        }
                        .pd-5-dot-left {
                            padding: 8px;
                            text-align: left;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #000000;
                            font-size: 9px;
                        }
                        .pd-5-solid-left {
                            padding: 8px;
                            text-align: left;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .pd-5-solid-center {
                            padding: 8px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .pd-5-solid-top-center {
                            padding: 8px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            border-top: 1px solid #000000;
                            font-size: 9px;
                            font-weight: bold;
                        }
                        .bordered{
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            border-top: 1px solid #000000;
                            font-size: 9px;
                        }
                        .right {
                            float: right;
                            width: 40%;
                            height: 100%;
                        }
                        .left {
                            float: left;
                            padding-top: " . ($mode_download == 'downloadLHP' || $mode_download == 'downloadLHP' ? '18px' : '14px') . ";
                            width: 59%;
                        }
                        .left2 {
                            float: left;
                            width: 69%;
                        }";
        // $file_qr = public_path('qr_documents/' . $qr_img . '.svg');
        if ($mode_download == 'downloadLHPP' || $mode_download == 'downloadLHP') {
            if (!is_null($qr_img)) {
                $qr = 'DP/7.8.1/ISL; Rev 3; 08 November 2022';
            } else {
                $qr = 'DP/7.8.1/ISL; Rev 3; 08 November 2022';
            }
            $ketFooter = '<td width="15%" style="vertical-align: bottom;">
                        <div>PT Inti Surya Laboratorium</div>
                        <div>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
                        <div>021-5089-8988/89 contact@intilab.com</div>
                        </td>
                        <td width="59%" style="vertical-align: bottom; text-align:center; padding:0; padding-left:44px; margin:0; position:relative; min-height:100px;">
                        Hasil uji ini hanya berlaku untuk kondisi sampel yang tercantum pada lembar ini dan tidak dapat digeneralisasikan untuk sampel lain. Lembar ini tidak dapat di gandakan tanpa izin dari laboratorium.
                        <br>Halaman {PAGENO} - {nbpg}
                        </td>';
            $body = '<body>';
        }
        $pdf->SetHTMLHeader($header);
        $pdf->SetWatermarkImage(public_path() . "/logo-watermark.png", -1, "", [110, 35]);
        $pdf->showWatermarkImage = true;
        // $pdf->SetHTMLHeader($header, '', TRUE);
        $pdf->WriteHTML($stylesheet, 1);
        $pdf->WriteHTML('<!DOCTYPE html>
            <html>
                ' . $body . '');
        // =================Isi Data==================
        $pdf->SetHTMLFooter('
            <table width="100%" style="font-size:7px">
                <tr>
                    ' . $ketFooter . '
                    <td width="23%" style="text-align: right;">
                    <table 
                        style="position: absolute; bottom: 0; right: 0; font-family: Helvetica, sans-serif; font-size: 7px; text-align: right;"
                    >
                        <tr><td> ' . $qr . '</td></tr>
                    </table>
                </td>
                </tr>
                <tr>
                </tr>
            </table>
        ');
        // $tot = count($bodi) - 1;
        // foreach ($bodi as $key => $val) {
        //     $pdf->WriteHTML($val);
        //     if ($tot > $key) {
        //         $pdf->AddPage();
        //     }
        // }
        $pdf->writeHTML($bodi);

        $pdf->WriteHTML('</body>
                </html>');
        if ($mode_download == 'downloadLHP') {
            $dir = public_path('dokumen/LHP_DOWNLOAD/');
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $pdf->Output($dir . '/' . $filename, \Mpdf\Output\Destination::FILE);
            return $filename;
        } else {
            $dir = public_path('dokumen/LHPP/');
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $pdf->Output($dir . '/' . $filename, \Mpdf\Output\Destination::FILE);
            return $filename;
        }



    }

    private function tanggal_indonesia($tanggal)
    {
        $bulan = array(
            1 => 'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
        );
        if ($tanggal != '') {
            $var = explode('-', $tanggal);
            return $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
        } else {
            return '-';
        }
    }

    private function waktuPemaparan($waktu)
    {
        $jam = floor($waktu / 60);
        $menit = $waktu % 60;

        $hasil = '';
        if ($jam > 0) {
            $hasil .= $jam . ' jam';
        }
        if ($menit > 0) {
            $hasil .= ($jam > 0 ? ' ' : '') . $menit . ' menit';
        }

        return $hasil ?: '0 menit';
    }
}
