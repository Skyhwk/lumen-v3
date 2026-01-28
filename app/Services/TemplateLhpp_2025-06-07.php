<?php

namespace App\Services;

use App\Models\LhpsAirHeader;
use App\Models\LhpsAirDetail;
use App\Models\OrderDetail;
use App\Models\QrDocument;
use Illuminate\Support\Facades\DB;
use App\Services\MpdfService as PDF;
use Carbon\Carbon;

class TemplateLhpp
{
    protected $pdf;
    protected $data;
    protected $fileName;

    public function lhpp_psikologi($data, $data_detail, $mode_download, $cfr)
    {

        $qr_img = '';
        $qr_img_k3 = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && !is_null($data->file_qr_k3)) {
            $qr_img = '<img src="' . env('APP_URL') . ('/public/qr_documents/' . $data->file_qr) . '" width="40px" height="40px" style="margin: 20px 0;">';
            $qr_img_k3 = '<img src="' . env('APP_URL') . ('/public/qr_documents/' . $data->file_qr_k3) . '" width="60px" height="60px" style="margin: 20px 0;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia(Carbon::now()->format('Y-m-d'));
        } else {
            $qr_img = '<img src="' . env('APP_URL') . ('/public/qr_documents/' . $data->file_qr) . '" width="40px" height="40px" style="margin: 20px 0;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia(Carbon::now()->format('Y-m-d'));
        }

        $html = '<div style="padding:20px">';

        // Header laporan dengan text center
        $html = '<div style="text-align:center; margin-bottom: 30px;">';
        $html .= '<h2 style="margin-bottom: 5px;">LAPORAN HASIL PENGUJIAN</h2>';
        $html .= '<h4 style="margin-top: 0; margin-bottom: 5px;">PT INTI SURYA LABORATORIUM</h4>';
        $html .= '<p style="margin-top: 0; font-style: italic;">Icon Business Park Blok O No. 5-6, Sampora, Kec. Cisauk, Tangerang, Banten 15345</p>';
        $html .= '</div>';

        $html .= '<p style="font-size: 10px;">DATA UMUM</p>';
        $html .= '<table style="width:100%; border-collapse: collapse; font-size: 10px;">';
        $html .= '<tr><td style="width:5%; border:none;">a.</td><td style="width:35%; border:none;">Perusahaan</td><td style="width:2%; border:none;">:</td><td style="border:none;">' . ($data['nama_perusahaan'] ?? '-') . '</td></tr>';
        $html .= '<tr><td style="border:none;">b.</td><td style="border:none;">Alamat</td><td style="border:none;">:</td><td style="border:none;">' . ($data['alamat_perusahaan'] ?? '-') . '</td></tr>';
        if ($mode_download != 'downloadLHP') {
            $html .= '<tr><td style="border:none;">c.</td><td style="border:none;">Pengurus/Penanggungjawab</td><td style="border:none;">:</td><td style="border:none;">' . ($data['penanggung_jawab'] ?? '-') . '</td></tr>';
        }
        $html .= '<tr><td style="border:none;">d.</td><td style="border:none;">Lokasi Pemeriksaan/Pengujian</td><td style="border:none;">:</td><td style="border:none;">' . ($data['lokasi_pemeriksaan'] ?? '-') . '</td></tr>';
        if ($mode_download != 'downloadLHP') {
            $html .= '<tr><td style="border:none;">e.</td><td style="border:none;">Nomor Dokumen Pengujian Sebelumnya</td><td style="border:none;">:</td><td style="border:none;">' . ($cfr ?? '-') . '</td></tr>';
            $html .= '<tr><td style="border:none;">f.</td><td style="border:none;">Nomor SKP PJK3/Bidang</td><td style="border:none;">:</td><td style="border:none;">' . ($data['no_skp_pjk3'] ?? '-') . '</td></tr>';
            $html .= '<tr><td style="border:none;">g.</td><td style="border:none;">Nomor SKP Ahli K3</td><td style="border:none;">:</td><td style="border:none;">' . ($data['no_skp_ahli_k3'] ?? '-') . '</td></tr>';
        }
        $html .= '</table>';

        $html .= '<br><p style="font-size: 10px;">PEMERIKSAAN DAN/ATAU PENGUJIAN TEKNIS</p>';
        $html .= '<table style="width:100%; border-collapse: collapse; font-size: 10px;">';
        $html .= '<tr><td style="width:5%; border:none;">a.</td><td style="width:35%; border:none;">Tanggal Pemeriksaan/Pengujian/Pengukuran</td><td style="width:2%;border:none;">:</td><td style="border:none;">' . self::tanggal_indonesia($data['tanggal_pemeriksaan'] ?? '-') . '</td></tr>';
        if ($mode_download != 'downloadLHP') {
            $html .= '<tr><td style="border:none;">b.</td><td style="border:none;">Waktu Pemeriksaan/Pengujian/Pengukuran</td><td>:</td><td style="border:none;">' . ($data['waktu_pemeriksaan'] ?? '-') . '</td></tr>';
        }
        $html .= '</table>';

        $html .= '<br><p style="font-size: 10px;">PEMERIKSAAN DAN/ATAU PENGUJIAN TEKNIS</p>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse; font-size: 10px;">';
        $html .= '<thead><tr>';
        $html .= '<th>No</th><th>No Titik</th><th>Jenis Pekerjaan</th><th>Kategori Stress</th><th>Nilai</th><th>Kesimpulan</th>';
        if ($mode_download != 'downloadLHP') {
            $html .= '<th>Tindakan Pengendalian yang Telah Dilakukan</th>';
        }
        $html .= '</tr></thead><tbody>';

        $count = 1;
        $divisiGroups = [];

        // Kelompokkan data berdasarkan divisi
        foreach ($data_detail as $item) {
            $divisi = $item['divisi'] ?? '-';
            $divisiGroups[$divisi][] = $item;
        }

        foreach ($divisiGroups as $divisi => $items) {
            $totalRowsForDivisi = 0;

            // Hitung total row (jumlah hasil) untuk rowspan divisi
            foreach ($items as $item) {
                $hasil = json_decode($item['hasil'], true);
                
                if (!$hasil)
                    continue;

                if (isset($hasil['kategori_stress'])) {
                    $hasil = [$hasil];
                }

                $totalRowsForDivisi += count($hasil);
            }

            $printedDivisi = false;

            foreach ($items as $item) {
                $hasil = json_decode($item['hasil'], true);
                if (!$hasil)
                    continue;
                
                if (isset($hasil['kategori_stress'])) {
                    $hasil = [$hasil];
                }

                foreach ($hasil as $idx => $value) {
                    $html .= '<tr style="page-break-inside: avoid;">';

                    // Kolom nomor dan sampel
                    if ($idx == 0) {
                        $html .= '<td rowspan="' . count($hasil) . '">' . $count . '</td>';
                        $html .= '<td rowspan="' . count($hasil) . '">' . ($item['no_sampel'] ?? '-') . '</td>';

                        // Cetak kolom divisi sekali untuk semua item di grup ini
                        if (!$printedDivisi) {
                            $html .= '<td rowspan="' . $totalRowsForDivisi . '" style="text-align:center;">' . $divisi . '</td>';
                            $printedDivisi = true;
                        }
                    }

                    // Kolom hasil
                    $html .= '<td style="text-align:center;">' . ($value['kategori_stress'] ?? '-') . '</td>';
                    $html .= '<td style="text-align:center;">' . ($value['nilai'] ?? '-') . '</td>';
                    $html .= '<td style="text-align:center;">' . ($value['kesimpulan'] ?? '-') . '</td>';

                    // Tindakan
                    if ($idx == 0 && $mode_download != 'downloadLHP') {
                        $html .= '<td style="text-align:center;" rowspan="' . count($hasil) . '">' . ($item['tindakan'] ?? '-') . '</td>';
                    }

                    $html .= '</tr>';
                }

                $count++;
            }
        }



        $divisiCount = [];

        foreach ($data_detail as $item) {
            $divisi = $item['divisi'];
            $hasil = json_decode($item['hasil'], true); // hasil adalah array dari objek: kategori_stress, nilai, kesimpulan

            if (!isset($divisiCount[$divisi])) {
                $divisiCount[$divisi] = [
                    'divisi' => $divisi,
                    'jumlah_pekerja' => 0,
                    'detail' => [], // ini menampung per kategori_stress
                ];
            }

            // Tambah jumlah pekerja
            $divisiCount[$divisi]['jumlah_pekerja']++;

            foreach ($hasil as $result) {
                // dump($result);
                $kategori_stress = strtolower(trim($result['kategori_stress']));
                $kesimpulan = strtoupper(trim($result['kesimpulan'])); // RINGAN/SEDANG/BERAT
                
                // Pastikan detail kategori_stress sudah diinisialisasi
                if (!isset($divisiCount[$divisi]['detail'][$kategori_stress])) {
                    $divisiCount[$divisi]['detail'][$kategori_stress] = [
                        'ringan' => 0,
                        'sedang' => 0,
                        'berat' => 0,
                    ];
                }

                // Tambahkan berdasarkan kesimpulan
                if ($kesimpulan === 'RINGAN') {
                    $divisiCount[$divisi]['detail'][$kategori_stress]['ringan']++;
                } elseif ($kesimpulan === 'SEDANG') {
                    $divisiCount[$divisi]['detail'][$kategori_stress]['sedang']++;
                } elseif ($kesimpulan === 'BERAT') {
                    $divisiCount[$divisi]['detail'][$kategori_stress]['berat']++;
                }
            }
        }
        // dd('masuk divisiCount');
        $divisiCount = array_values($divisiCount); // reset key divisi menjadi numerik
        // dd($data['no_order']);

        $html .= '</tbody></table>';
        $html .= '</div>';
        if ($mode_download == 'downloadLHP') {
            $html .= '<table style="border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 10px;">';
            $html .= '<tr>';
            $html .= '<td style="width: 30%; vertical-align: top; padding: 8px;">';
            $html .= '<b>Kesimpulan</b><br>';
            $html .= '<table style="border-collapse: collapse; width: 100%; margin-top: 5px;">';
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid #000; padding: 4px;"><strong>[Skor ≤ 9]</strong></td>';
            $html .= '<td style="border: 1px solid #000; padding: 4px;">: derajat stres RINGAN</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid #000; padding: 4px;"><strong>Skor 10-24</strong></td>';
            $html .= '<td style="border: 1px solid #000; padding: 4px;">: derajat stres SEDANG</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid #000; padding: 4px;"><strong>Skor > 24</strong></td>';
            $html .= '<td style="border: 1px solid #000; padding: 4px;">: derajat stres BERAT</td>';
            $html .= '</tr>';
            $html .= '</table>';
            $html .= '</td>';

            $html .= '<td style="vertical-align: top; padding: 8px;">';
            $html .= '<p class="mb-3">1 <b>Ketaksaan Peran</b>: Terjadi ketika pekerja tidak memahami dengan jelas tugas, tanggung jawab, dan harapan dalam pekerjaannya.<br></p>';
            $html .= '<p class="mb-3">2 <b>Konflik Peran</b>: Muncul saat pekerja menerima dua atau lebih tuntutan tugas yang saling bertentangan dan sulit dijalankan bersamaan.<br></p>';
            $html .= '<p class="mb-3">3 <b>Beban Kerja Berlebih Kuantitatif</b>: Terjadi saat karyawan mendapat terlalu banyak tugas, hingga tidak cukup waktu untuk menyelesaikannya dengan baik.<br></p>';
            $html .= '<p class="mb-3">4 <b>Beban Kerja Berlebih Kualitatif</b>: Terjadi saat karyawan diberi tugas yang terlalu sulit atau rumit yang melebihi kemampuan atau keahlian yang mereka miliki.<br></p>';
            $html .= '<p class="mb-3">5 <b>Pengembangan Karir</b>: Proses karyawan untuk belajar, berkembang, dan merencanakan masa depan pekerjaannya sesuai dengan tujuan dan kemampuan.<br></p>';
            $html .= '<p class="mb-3">6 <b>Tanggung Jawab terhadap Orang Lain</b>: Sadar dan berkomitmen untuk menjalankan tugas dengan baik, menghormati hak orang lain, dan memikirkan dampak dari tindakan kita terhadap mereka.</p>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';
        }



        $html .= '<table style="border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 10px;">';
        $html .= '<tr>';
        $html .= '<td style="width: 30%; vertical-align: top;"><p>METODE PENGUKURAN YANG DIPAKAI</p></td>';
        $html .= '<td style="width: 70%; vertical-align: top; text-align: justify"><div>';
        $html .= '<p>a. Rumus Penentuan Jumlah Responden Pada Pedoman PerMeNaKer No. 5 Tahun 2018 untuk menentukan jumlah responden dalam kegiatan pengukuran psikologi kerja.</p>';
        $html .= '<p>b. Metode SDS (Survei Diagnosis Stress) untuk Penentuan tingkat risiko stres akibat sumber-sumber penyebab stres di tempat kerja.</p>';
        $html .= '<div></td>';
        $html .= '</tr>';
        $html .= '</table>';

        $html .= '<div style="width: 100%; overflow: hidden; margin-bottom: 20px;">';

        // Kolom Kiri (judul ANALISIS)
        $html .= '<div style="width: 30%; float: left; vertical-align: top;">';
        $html .= '<p>ANALISIS</p>';
        $html .= '</div>';

        // Kolom Kanan (isi penjelasan)
        $html .= '<div style="width: 70%; float: left; vertical-align: top;">';
        $html .= '<p style="text-align: justify; font-size: 10px">Dalam pelaksanaannya, jumlah responden dihitung menggunakan rumus Slovin, sebagai berikut</p>';
        $html .= '<p style="text-align: justify; font-size: 10px">n = N / (1+(N x e2) </p>';
        $html .= '<p style="text-align: justify; font-size: 10px"></p>';
        $html .= '<p style="text-align: justify; font-size: 10px">keterangan</p>';
        $html .= '<p style="text-align: justify; font-size: 10px"> n : Jumlah responden</p>';
        $html .= '<p style="text-align: justify; font-size: 10px"> N : Jumlah populasi</p>';
        $html .= '<p style="text-align: justify; font-size: 10px"> e2 : Tingkat kepercayaan 10%</p>';

        $no = 1;

        foreach ($divisiCount as $key => $item) {
            $html .= '<p style="font-weight: bold; margin-top: 20px; text-align: justify; font-size: 10px">' . ($key + 1) . '. Divisi ' . $item['divisi'] . ' (' . $item['jumlah_pekerja'] . ' Jumlah Responden).</p>';
            // $html .= '<p style="margin-top: 10px; text-align: justify; font-size: 10px">a. Jumlah Populasi: ' . $item['jumlah_pekerja'] . ' Orang</p>';
            $html .= '<p style="margin-top: 10px; text-align: justify; font-size: 10px">a. Jumlah Responden: ' . $item['jumlah_pekerja'] . ' Orang</p>';

            $total = $item['jumlah_pekerja'];
            $jumlah_kategori = count($item['detail']);
            $total_ringan = 0;
            $total_sedang = 0;
            $total_berat = 0;
            $kategori_sedang_lebih_banyak = [];
            $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse; font-size: 10px;">';
            $html .= '<thead><tr>';
            $html .= '<th style="width: 30%; text-align: left !important">Kategori</th>';
            $html .= '<th style="text-align: left !important">Ringan</th>';
            $html .= '<th style="text-align: left !important">Ringan %</th>';
            $html .= '<th style="text-align: left !important">Sedang</th>';
            $html .= '<th style="text-align: left !important">Sedang %</th>';
            $html .= '<th style="text-align: left !important">Berat</th>';
            $html .= '<th style="text-align: left !important">Berat %</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($item['detail'] as $kategori_stress => $item2) {
                // dd($item2);
                // dd($item2);
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
                // $html .= "<p style='text-align: justify'>{$no}. {$judul_kategori}, pekerja dengan kategori stres ringan sebanyak {$ringan} orang ({$persen_ringan}%), stres sedang {$sedang} orang ({$persen_sedang}%), dan stres berat {$berat} orang ({$persen_berat}%).</p>";
                $no++;

                $total_ringan += $ringan;
                $total_sedang += $sedang;
                $total_berat += $berat;

                if ($persen_sedang > $persen_ringan) {
                    $kategori_sedang_lebih_banyak[] = $judul_kategori;
                }
            }



            // Hitung total responden * kategori
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

            $html .= '</tbody>';
            $html .= '</table>';
            if ($mode_download != 'downloadLHP') {
                $html .= '<p><strong>Jumlah rata-rata persentase stres pada analisis pengukuran psikologi bagian Logistik sebanyak ' . $total . ' orang menunjukkan stres sedang lebih banyak dari stres ringan, dan adanya stres berat.</strong></p>';
            }
        }

        // === Kesimpulan Dinamis ===
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

            // Kriteria: stres berat == 0 dan stres sedang < 50% => Memenuhi
            if ($persen_berat == 0 && $persen_sedang < 50) {
                $divisiMemenuhi[] = $item['divisi'] . ' (' . $item['jumlah_pekerja'] . ' orang jumlah responden)';
            } else {
                $divisiTidakMemenuhi[] = $item['divisi'] . ' (' . $item['jumlah_pekerja'] . ' orang jumlah responden)';
            }
        }

        $html .= '</div>'; // tutup kolom kanan
        $html .= '</div>'; // tutup row utama


        if ($mode_download != 'downloadLHP') {
            $html .= '<table style="border-collapse: collapse; width: 100%; margin-top: 20px">';
            $html .= '<tr><td style="width: 30%; vertical-align: top;">';
            $html .= '<p style="" class="mt-4">KESIMPULAN</p></td>';
            $html .= '<td style="width: 70%; vertical-align: top; text-align: justify">';
            if (!empty($divisiMemenuhi)) {
                $html .= '<p>Berdasarkan hasil pengukuran dan analisis, dapat disimpulkan bahwa pengujian psikologi pada bagian <strong style="color: red;">' . implode(', ', $divisiMemenuhi) . ' telah memenuhi Standar. </strong></p>';
            }

            if (!empty($divisiTidakMemenuhi)) {
                $html .= '<p>Adapun yang belum memenuhi Standar adalah pada bagian <strong style="color: red;">' . implode(', ', $divisiTidakMemenuhi) . '</strong>.</p>';
            }

            $html .= '<p><strong style="color: red;">Perusahaan berkewajiban untuk melaksanakan riksa uji lingkungan kerja Psikologi kembali.</strong></p>';

            $html .= '<tr><td style="width: 30%; vertical-align: top;">';
            $html .= '<p style="" class="mt-4">Persyaratan yang harus segera dipenuhi</p></td>';
            $html .= '<td style="width: 70%; vertical-align: top; text-align: justify">';
            $html .= '<p>-</p> </td></tr>';
            $html .= '</table>';
        }

        $ttd = '
            <table style="font-family: Helvetica, sans-serif; font-size: 11px; margin-top: 100px;" width="100%">
                <tr>';

        if ($mode_download == 'downloadLHPP') {
            // TTD Kiri
            $ttd .= '
                <td style="width: 50%; text-align: center;">
                    <table>
                        <tr><td>' .$tanggal_qr . '</td></tr>
                        <tr><td style="height: 70px;"></td></tr>
                        <tr><td><strong>( <u>Abidah Walfathiyyah</u> )</strong></td></tr>
                        <tr><td>Technical Control Supervisor</td></tr>
                    </table>
                </td>';

            // TTD Kanan
            $ttd .= '
                <td style="width: 50%; text-align: center;">
                    ' . $tanggal_qr . '<br>Yang Memeriksa dan Menguji<br>Ahli K3 Lingkungan Kerja Muda<br>' . $qr_img_k3 . '<br>
                    <span style="font-size: 10px; font-weight: bold;">' . $data->nama_skp_ahli_k3 . '</span><br>
                    <span style="font-size: 10px; font-weight: bold;">No Reg : ' . $data->no_skp_ahli_k3 . '</span>
                </td>';
        } elseif ($mode_download == 'downloadLHP') {
            // TTD hanya kanan, isi tetap center
            $ttd .= '
                <td style="width: 50%; text-align: center;"></td>
                <td style="width: 50%; text-align: center;">
                    <table>
                        <tr><td>' .$tanggal_qr . '</td></tr>
                        <tr><td style="height: 70px;"></td></tr>
                        <tr><td><strong>( <u>Abidah Walfathiyyah</u> )</strong></td></tr>
                        <tr><td>Technical Control Supervisor</td></tr>
                    </table>
                </td>';
        }

        $ttd .= '
                </tr>
            </table>
            <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; margin-bottom: 40px;" width="100%">
                <tr><td></td></tr>
                <tr><td></td></tr>
            </table>';


        $no_lhp = str_replace("/", "-", $cfr);

        if ($mode_download == 'downloadLHPP') {
            $name = 'LHPP-' . $no_lhp . '.pdf';
        } else if ($mode_download == 'downloadLHP') {
            $name = 'LHP-' . $no_lhp . '.pdf';
        } else {
            return null;
        }
        // dd("lolos");
        $ress = self::formatTemplate($html, $name, $ttd, $qr_img, $mode_download, $custom2 = null);
        return $ress;
    }

    private function formatTemplate($bodi, $filename, $ttd, $qr_img, $mode_download, $custom2)
    {
        $mpdfConfig = array(
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => ($mode_download == 'downloadLHPP' ? 12 : 18),
            'margin_bottom' => 30,
            'margin_footer' => 8,
            'margin_top' => 23.5,
            'margin_left' => 10,
            'margin_right' => 10,
            // 'orientation' => 'P',
            'orientation' => 'L',
        );

        $pdf = new PDF($mpdfConfig);
        $pdf->SetProtection(array(
            'print'
        ), '', 'skyhwk12');
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
                        <tr>
                            <td>' . $qr_img . '</td>
                        </tr>
                        <tr><td> '.$qr .'</td></tr>
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
        $pdf->writeHTML($ttd);

        $pdf->WriteHTML('</body>
        </html>');
        if ($mode_download == 'downloadLHP') {
            $dir = public_path('dokumen/LHP/');
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
