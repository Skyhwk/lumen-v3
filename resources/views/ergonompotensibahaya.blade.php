<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pemeriksaan Potensi Bahaya Ergonomi</title>
    <style>
        /* CSS Global untuk Halaman */


        /* Styling Body Utama (Area Konten Kiri) */
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin-right: 118mm; 
        }
         .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .konten-kiri {
            float: left; /* Membuat elemen ini 'mengambang' ke kiri */
            width: 159mm; /* Lebar pasti untuk konten kiri */
        }
        .konten-kanan {
            float: right; /* Membuat elemen ini 'mengambang' ke kanan */
            width: 110mm; /* Lebar pasti untuk konten kanan */
        }

        /* Styling Umum untuk Konten (Baik di Kiri maupun Kanan) */
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px; text-align: left; vertical-align: top; font-size: 12px; }
        th { font-weight: bold; text-align: center; background-color: #e8e8e8; }
        .section { padding: 8px; margin-bottom: 0; page-break-inside: avoid; }
        .sectionP{
            margin-bottom: 5px;
            /* border: 1px solid #000; */
            padding: 3px;}
        .section-titleP { font-weight: bold; font-size: 12px;}
        .info-table { border: none; }
        .info-table td { border: none; padding: 1px 0; font-size: 12px; }
        .lhp-info-table td {font-size: 12px; }
        .table-potensi-bahaya td[rowspan] { background-color: #f5f5f5; text-align: center; vertical-align: middle; font-weight: bold; }
        .total-score-table td, .rekap-table td { font-weight: bold; background-color: #f0f0f0; }
        .signature-section { text-align: center; font-size: 10px; margin-top: 10px; page-break-inside: avoid; }
        .signature-text { margin-top: 40px; }
        .signature-text strong { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="clearfix">
        <div class="konten-kiri">
            <div class="sectionP">
                <table style="border-collapse: collapse; width: 100%; border: 2px solid black; font-size: 12px;">
                    <tbody>
                        <tr style="background-color: #ffffff;">
                            <td colspan="4" style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                I. Daftar Periksa Potensi Bahaya Tubuh Bagian Atas
                            </td>
                        </tr>
                        <tr style="text-align: center; font-weight: bold;">
                            <th style="border: 1px solid black; padding: 5px; width: 15%;">No.</th>
                            <th style="border: 1px solid black; padding: 5px; width: 25%;">Kategori</th>
                            <th style="border: 1px solid black; padding: 5px;">Potensi Bahaya</th>
                            <th style="border: 1px solid black; padding: 5px; width: 80px;">Skor</th>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">1</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Leher: memuntir atau menekuk</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">0</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">2</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Bahu: Lengan / siku yang tak ditopang di atas tinggi perut</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">1</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">3</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Rotasi lengan bawah secara cepat</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">4</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Pergelangan tangan: Menekuk ke depan atau ke samping</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">5</td>
                            <td style="border: 1px solid black; padding: 5px;">Gerakan Lengan</td>
                            <td style="border: 1px solid black; padding: 5px;">Sedang: Gerakan stabil dengan jeda teratur</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">6</td>
                            <td style="border: 1px solid black; padding: 5px;">Gerakan Lengan</td>
                            <td style="border: 1px solid black; padding: 5px;">Intensif: Gerakan cepat yang stabil tanpa jeda teratur</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">7</td>
                            <td style="border: 1px solid black; padding: 5px;">Penggunaan Keyboard</td>
                            <td style="border: 1px solid black; padding: 5px;">Mengetik secara berselang (diselingi aktifitas / istirahat)</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">8</td>
                            <td style="border: 1px solid black; padding: 5px;">Penggunaan Keyboard</td>
                            <td style="border: 1px solid black; padding: 5px;">Mengetik secara Intensif</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">9</td>
                            <td style="border: 1px solid black; padding: 5px;">Usaha Tangan (Repetitif maupun Statis)</td>
                            <td style="border: 1px solid black; padding: 5px;">Menggenggam dalam posisi "<i>power grip</i>" gaya > 5 kg</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">10</td>
                            <td style="border: 1px solid black; padding: 5px;">Usaha Tangan (Repetitif maupun Statis)</td>
                            <td style="border: 1px solid black; padding: 5px;">Memencet / Menjepit benda dengan jari gaya > 1 kg</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">11</td>
                            <td style="border: 1px solid black; padding: 5px;">Tekanan Langsung ke bagian tubuh</td>
                            <td style="border: 1px solid black; padding: 5px;">Kulit tertekan oleh benda yang keras atau runcing</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">12</td>
                            <td style="border: 1px solid black; padding: 5px;">Tekanan Langsung ke bagian tubuh</td>
                            <td style="border: 1px solid black; padding: 5px;">Menggunakan telapak atau pergelangan tangan untuk memukul</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">13</td>
                            <td style="border: 1px solid black; padding: 5px;">Getaran</td>
                            <td style="border: 1px solid black; padding: 5px;">Getaran lokal (tanpa peredam)</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">14</td>
                            <td style="border: 1px solid black; padding: 5px;">Terdapat faktor yang membuat ritme kerja tubuh bagian atas dan/atau lengan tidak dapat dikontrol pekerja</td>
                            <td style="border: 1px solid black; padding: 5px;"></td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">15</td>
                            <td style="border: 1px solid black; padding: 5px;">Lingkungan</td>
                            <td style="border: 1px solid black; padding: 5px;">Pencahayaan (Pencahayaan yang kurang atau silau)</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">16</td>
                            <td style="border: 1px solid black; padding: 5px;">Lingkungan</td>
                            <td style="border: 1px solid black; padding: 5px;">Temperatur terlalu tinggi atau rendah</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>

                        <tr style="background-color: #ffffff;">
                            <td colspan="4" style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                II. Daftar Periksa Potensi Bahaya Tubuh Bagian Bawah
                            </td>
                        </tr>
                        <tr style="text-align: center; font-weight: bold;">
                            <td style="border: 1px solid black; padding: 5px;">No.</td>
                            <td style="border: 1px solid black; padding: 5px;">Kategori</td>
                            <td style="border: 1px solid black; padding: 5px;">Potensi Bahaya</td>
                            <td style="border: 1px solid black; padding: 5px;">Skor</td>
                        </tr>

                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">17</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Tubuh membungkuk ke depan / menekuk ke samping 20 - 45°</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">0</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">18</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Tubuh membungkuk ke depan > 45°</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">1</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">19</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Tubuh menekuk ke belakang hingga 30°</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">20</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Pemuntira torso (batang tubuh)</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">21</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Gerakan paha menjauhi tubuh ke samping secara berulang-ulang</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">22</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Posisi berlutut atau jongkok</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">23</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Pergelangan kaki menekuk ke atas / ke bawah secara berulang</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">24</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Aktivitas pergelangan kaki / berdiri dengan pijakan tidak memadai</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">0</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">25</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Duduk dalam waktu yang lama tanpa sandaran yang memadai</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">26</td>
                            <td style="border: 1px solid black; padding: 5px;">Postur Janggal</td>
                            <td style="border: 1px solid black; padding: 5px;">Bekerja berdiri dalam waktu lama / duduk tanpa pijakan memadai</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">1</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">27</td>
                            <td style="border: 1px solid black; padding: 5px;">Tekanan Langsung ke bagian tubuh</td>
                            <td style="border: 1px solid black; padding: 5px;">Tubuh tertekan oleh benda yang keras / runcing</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">28</td>
                            <td style="border: 1px solid black; padding: 5px;">Tekanan Langsung ke bagian tubuh</td>
                            <td style="border: 1px solid black; padding: 5px;">Menggunakan lutut untuk memukul / menendang</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">29</td>
                            <td style="border: 1px solid black; padding: 5px;">Tekanan Langsung ke bagian tubuh</td>
                            <td style="border: 1px solid black; padding: 5px;">Getaran pada seluruh tubuh (tanpa peredam)</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">30</td>
                            <td style="border: 1px solid black; padding: 5px;">Aktifitas Mendorong / Menarik beban</td>
                            <td style="border: 1px solid black; padding: 5px;">Beban sedang</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">31</td>
                            <td style="border: 1px solid black; padding: 5px;">Aktifitas Mendorong / Menarik beban</td>
                            <td style="border: 1px solid black; padding: 5px;">Beban berat</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">32</td>
                            <td style="border: 1px solid black; padding: 5px;">Terdapat faktor yang membuat ritme kerja tubuh bagian atas dan/atau lengan tidak dapat dikontrol pekerja</td>
                            <td style="border: 1px solid black; padding: 5px;"></td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>

                        <tr>
                            <td colspan="3" style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">Total Skor I dan II</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">3</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="sectionP">
                <table style="border-collapse: collapse; width: 100%; border: 2px solid black; font-size: 13px;">
                    <thead>
                        <tr style="background-color: #ffffff;">
                            <td colspan="4" style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                III. Daftar Periksa Pengamatan Beban Secara Manual
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="border: 1px solid black; padding: 10px; font-weight: bold; text-align: center; width: 15%;">
                                Skor Langkah 1
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">
                                <span style="font-weight: bold;">33. Jarak Pengangkatan</span><br>
                                <span style="color: red;">Pengangkatan dengan jarak dekat</span>
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; width: 20%;">
                                <b>Berat Beban</b><br>
                                <span style="color: red; font-weight: bold;">7 Kg</span>
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; width: 10%;">
                                <b>Skor</b><br>
                                <span style="font-weight: bold;">3</span>
                            </td>
                        </tr>

                        <tr>
                            <td rowspan="11" style="border: 1px solid black; padding: 10px; font-weight: bold; text-align: center;">
                                Skor Langkah 2
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">
                                Faktor Risiko
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">
                                Pengangkatan
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">
                                Skor
                            </td>
                        </tr>

                        <tr>
                            <td style="border: 1px solid black; padding: 5px;">34 &nbsp; Batang tubuh memuntir saat mengangkat</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px;">35 &nbsp; Mengangkat dengan satu tangan</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px;">36 &nbsp; Mengangkat dengan beban tidak terduga / tidak diprediksi</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px;">37 &nbsp; Mengangkat 1 - 5 kali per menit</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">Sesekali (< 1 jam/shift)</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">1</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px;">38 &nbsp; Mengangkat > 5 kali per menit</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px;">39 &nbsp; Posisi benda yang diangkat berada di atas bahu</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px;">40 &nbsp; Posisi benda yang diangkat berada di bawah posisi siku</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">Sering (> 1 jam/shift)</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">2</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px;">41 &nbsp; Mengangkut (membawa) benda dengan jarak 3 - 9 meter</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px;">42 &nbsp; Mengangkut (membawa) benda dengan jarak > 9 meter</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px;">43 &nbsp; Mengangkat benda saat duduk atau bertumpu pada lutut</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">gak muncul</td>
                        </tr>

                        <tr>
                            <td colspan="3" style="border: 1px solid black; padding: 10px; text-align: center; font-weight: bold;">
                                Skor Langkah Akhir
                            </td>
                            <td style="border: 1px solid black; padding: 10px; text-align: center; font-weight: bold;">
                                6
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
            <div class="sectionP">
                <table style="border-collapse: collapse; width: 100%; border: 2px solid black; font-size: 13px; margin-bottom: 20px;">
                    <thead>
                        <tr style="background-color: #ffffff;">
                            <td colspan="3" style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                IV. Rekapitulasi Penilaian Potensi Bahaya
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; font-weight: bold; width: 150px;">
                                Total Skor Akhir
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; width: 30px;">
                                :
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">
                                9
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table style="border-collapse: collapse; width: 100%; border: 2px solid black; font-size: 13px;">
                    <thead>
                        <tr style="background-color: #ffffff;">
                            <td style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                V. Kesimpulan
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="border: 1px solid black; padding: 10px; line-height: 1.5;">
                                Berdasarkan hasil pengamatan daftar periksa potensi bahaya ergonomi pada jenis pekerjaan tersebut, 
                                dapat disimpulkan bahwa Rekapitulasi Penilaian Potensi Bahaya memiliki hasil interpretasi tingkat risiko : <br>
                                <b>Berbahaya</b>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="konten-kanan">
            <table class="lhp-info-table">
                <thead>
                    <tr>
                        <th>No. LHP</th>
                        <th>No. Sampel</th>
                        <th>Jenis Sampel</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{$personal->no_lhp}}</td>
                        <td>{{$personal->no_sampel}}</td>
                        <td>ERGONOMI</td>
                    </tr>
                </tbody>
            </table>
                        
            <div class="sectionP">
                <div class="section-titleP">Informasi Pelanggan</div>
                <table class="info-table">
                    <tr>
                        <td style="width:25%">Nama Pelanggan</td>
                        <td style="width:3%">:</td>
                        <td style="width:72%;text-align:start;">{{ strtoupper($personal->nama_pelanggan) }}</td>
                    </tr>
                    <tr>
                        <td style="width:25%" >Alamat / Lokasi</td>
                        <td style="width:3%">:</td>
                        <td style="width: 72%;text-align:start;">{{ $personal->alamat_pelanggan }}</td>
                    </tr>
                </table>
            </div>
            <div class="sectionP">
                <div class="section-titleP">Informasi Sampling</div>
                <table class="info-table">
                    <tr>
                        <td style="width:25%">Tanggal Sampling</td>
                        <td style="width:3%">:</td>
                        <td style="width: 72%;text-align:start;">{{ $personal->tanggal_sampling }}</td>
                    </tr>
                    <tr>
                        <td style="width:25%">Metode Analisa</td>
                        <td style="width:3%">:</td>
                        <td style="width: 72%;text-align:start;">Observasi Potensi Bahaya Ergonomi SNI 9011:2021</td>
                    </tr>
                </table>
            </div>
            <div class="sectionP">
                    <div class="section-titleP">Data Individu/Pekerja yang Diukur</div>
                    <table class="info-table">
                        <tr>
                            <td style="width:25%">Nama</td>
                            <td style="width:3%">:</td>\
                            <td style="width: 72%;text-align:start;">{{ $personal->nama_pekerja }}</td>
                        </tr>
                        <tr>
                            <td style="width:25%">Usia</td>
                            <td style="width:3%">:</td>
                            <td style="width: 72%;text-align:start;">{{ $personal->usia }} Tahun</td>
                        </tr>
                        <tr>
                            <td style="width:25%">Jenis Pekerjaan</td>
                            <td style="width:3%">:</td>
                            <td style="width: 72%;text-align:start;">{{$personal->aktivitas_ukur}}</td>
                        </tr>
                        <tr>
                            <td style="width:25%">Lama Bekerja</td>
                            <td style="width:3%">:</td>
                            <td style="width: 72%;text-align:start;">{{ $personal->lama_kerja }} Tahun</td>
                        </tr>
                    </table>
            </div>
            <div class="sectionP">
                    <table class="uraian-tugas-table">
                    <thead>
                        <tr>
                            <th style="width:13%">No.</th>
                            <th>Uraian Tugas Singkat</th>
                            <th>Waktu/Durasi Kerja</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for($i = 1; $i <= 2; $i++)
                            <tr>
                                <td style="text-align: center;" >{{ $i }}</td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
            <div class="sectionP">
                <div class="section-titleP">Interpretasi Hasil Penilaian**</div>
                <table class="interpretasi-table">
                    <thead>
                        <tr>
                            <th style="width: 10%; text-align: center;">Skor</th>
                            <th style="width: 75%;">Interpretasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: center;">&lt;2</td>
                            <td>Kondisi tempat kerja aman</td>
                        </tr>
                        <tr>
                            <td style="text-align: center;">3 - 6</td>
                            <td>Perlu pengamatan lebih lanjut</td>
                        </tr>
                        <tr>
                            <td style="text-align: center;">&ge;7</td>
                            <td>Berbahaya</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="sectionP">
                <table style="border: 0;">
                    <tr>
                        <td style="border: 0; font-size: 9px; line-height: 1.3;">
                            * Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja.
                            <br>**Interpretasi Hasil Penilaian Daftar Periksa Potensi Bahaya Ergonomi Mengacu kepada Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja Bagian 5.1.
                        </td>
                    </tr>
                </table>
            </div>
            <table style="width: 100%; margin-top: 10px; border: none;">
                <tr>
                    <td style="width: 50%; border: none;"></td>

                    <td style="width: 50%; border: none; text-align: center; vertical-align: top;">
                        
                        <div style="margin-bottom: 5px;">
                            Tangerang, {{ $ttd->tanggal ?? '13 Agustus 2025' }}
                        </div>

                        @if($ttd && $ttd->qr_path)
                            <img src="{{ $ttd->qr_path }}" style="width: 50px; height: 50px; display: inline-block;" alt="QR TTD">
                        @else
                            <br><br><br>
                            <div style="font-weight: bold; text-decoration: underline;">
                                (Abidah Walfathiyyah)
                            </div>
                            <div>Technical Control Supervisor</div>
                        @endif

                    </td>
                </tr>
            </table>
        </div>  
    </div>
</body>
</html>