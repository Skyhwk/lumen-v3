<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian (DRAFT)</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 15px;
            font-size: 9px;
            background-color: #f9f9f9;
        }

        .page-container {
            width: 100%;
            margin: auto;
            background-color: #fff;
            padding: 15px;
            border: 1px solid #ccc;
        }

        .main-header-title {
            text-align: center;
            font-weight: bold;
            font-size: 1.5em;
            margin-bottom: 15px;
            text-decoration: underline;
        }

        .two-column-layout {
            width: 100%;
            overflow: hidden;
            margin-bottom: 15px;
        }

        .column {
            float: left;
        }

        .column-left {
            width: 60%;
            padding-right: 10px;
        }

        .column-right {
            width: 30%;
        }

        .section {
            border: 1px solid #000;
            padding: 6px;
            background-color: #fff;
            margin-bottom: 10px;
        }

        .section-title {
            font-weight: bold;
            background-color: #e0e0e0;
            padding: 3px 6px;
            margin: -6px -6px 6px -6px;
            border-bottom: 1px solid #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #000;
            padding: 3px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }

        .text-input-space {
            width: 100%;
            border: 1px solid #ccc;
            padding: 2px;
            min-height: 1.5em;
            background-color: #fff;
        }

        .multi-line-input {
            width: 100%;
            border: 1px solid #000;
            padding: 4px;
            min-height: 40px;
            background-color: #fff;
        }

        .footer-text {
            font-size: 0.85em;
            margin-top: 15px;
            border-top: 1px solid #ccc;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
        }

        .signature-block {
            margin-top: 15px;
            text-align: right;
        }

        .signature-block .signature-name {
            margin-top: 30px;
            font-weight: bold;
            text-decoration: underline;
        }

        .interpretasi-table td { text-align: center; }
        .interpretasi-table td:last-child { text-align: left; }

        .uraian-tugas-table td { height: 1.8em; }

    </style>
</head>
<body>
    <div class="page-container">
        <div class="main-header-title">LAPORAN HASIL PENGUJIAN</div>

        <div class="two-column-layout">
            <!-- KIRI -->
            <div class="column column-left">
                <div class="section">
                    <div class="section-title">I. Daftar Periksa Potensi Bahaya Tubuh Bagian Atas</div>
                    <table class="table-potensi-bahaya">
                        <thead><tr><th>Kategori</th><th>Potensi Bahaya</th><th>Skor</th></tr></thead>
                        <tbody>
                            <tr><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                            <tr><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                            <tr><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="section">
                    <div class="section-title">II. Daftar Periksa Potensi Bahaya Tubuh Bagian Bawah</div>
                    <table class="table-potensi-bahaya">
                        <thead><tr><th>Kategori</th><th>Potensi Bahaya</th><th>Skor</th></tr></thead>
                        <tbody>
                            <tr><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                            <tr><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                             <tr><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                        </tbody>
                    </table>
                    <table class="total-score-table" style="margin-top: -1px; border-top: 1px solid #000;"> <tr>
                            <td>Total Skor I dan II</td>
                            <td><div class="text-input-space"></div></td>
                        </tr>
                    </table>
                </div>

                <div class="section manual-load-section">
                    <div class="section-title">III. Daftar Periksa Pengamatan Beban Secara Manual</div>
                    <table>
                        <thead><tr><th>Jarak Pengangkatan</th><th>Berat Beban</th><th>Skor</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Skor Langkah 1</td>
                                <td><div class="text-input-space"></div></td>
                                <td><div class="text-input-space"></div></td>
                            </tr>
                        </tbody>
                    </table>
                    <table style="margin-top: -6px;"> <thead><tr><th>Faktor Risiko</th><th>Pengangkatan</th><th>Skor</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Skor Langkah 2</td>
                                <td><div class="text-input-space"></div></td>
                                <td><div class="text-input-space"></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="section">
                     <table class="total-score-table">
                        <tr>
                            <td>Skor Langkah Akhir</td>
                            <td><div class="text-input-space"></div></td>
                        </tr>
                    </table>
                </div>


                <div class="section">
                    <div class="section-title">IV. Rekapitulasi Penilaian Potensi Bahaya</div>
                    <table class="rekap-table">
                        <tr>
                            <td>Total Skor Akhir :</td>
                            <td><div class="text-input-space"></div></td>
                        </tr>
                    </table>
                </div>

                <div class="section">
                    <div class="section-title">V. Kesimpulan</div>
                    <div class="multi-line-input">Berdasarkan hasil pengamatan daftar periksa potensi bahaya ergonomi pada jenis pekerjaan tersebut, dapat disimpulkan bahwa Rekapitulasi Penilaian Potensi Bahaya memiliki hasil interpretasi tingkat resiko : Kondisi tempat kerja aman / Perlu pengamatan lebih lanjut / Berbahaya</div>
                </div>
            </div>

            <!-- KANAN -->
            <div class="column column-right">
                <table class="lhp-info-table info-table">
                    <tbody>
                        <tr>
                            <th>No. LHP</th>
                            <th>No. Sampel</th>
                            <th>Jenis Sampel</th>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <td>Ergonomi</td>
                        </tr>
                    </tbody>
                </table>

                <div class="section">
                    <div class="section-title" style="margin-bottom: 3px;">Informasi Pelanggan</div>
                    <table class="info-table">
                        <tr><td style="width: 90px;">Nama Pelanggan</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                        <tr><td>Alamat / Lokasi</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                        <tr><td>Sampling</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                    </table>
                </div>
                <div class="section">
                     <div class="section-title" style="margin-bottom: 3px;">Informasi Sampling</div>
                    <table class="info-table">
                        <tr><td style="width: 90px;">Tanggal Sampling</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                        <tr><td>Periode Analisis</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                        <tr><td>Metode Analisis*</td><td>:</td><td>Observasi Potensi Bahaya Ergonomi SNI 9011:2021</td></tr>
                    </table>
                </div>

                <div class="section">
                    <div class="section-title" style="margin-bottom: 3px;">Data Individu/Pekerja yang Diukur</div>
                    <table class="info-table">
                        <tr><td style="width: 90px;">Nama Pekerja</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                        <tr><td>Posisi/Jabatan</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                    </table>
                </div>

                <div class="section">
                    <table class="uraian-tugas-table">
                        <thead>
                            <tr><th>No.</th><th>Uraian Tugas Singkat</th><th>Waktu/Durasi Kerja Tiap Tugas</th></tr>
                        </thead>
                        <tbody>
                            <tr><td class="centered-text">1</td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                            <tr><td class="centered-text">2</td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                            <tr><td class="centered-text">3</td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                            <tr><td class="centered-text">4</td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                            <tr><td class="centered-text">5</td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="section">
                     <div class="section-title">Interpretasi Hasil Penilaian Daftar Periksa Potensi Bahaya Ergonomi**</div>
                     <table class="interpretasi-table">
                         <thead><tr><th>Skor</th><th>Interpretasi</th></tr></thead>
                         <tbody>
                             <tr><td>&lt;2</td><td>Kondisi tempat kerja aman</td></tr>
                             <tr><td>3 - 6</td><td>Perlu pengamatan lebih lanjut</td></tr>
                             <tr><td>≥7</td><td>Berbahaya</td></tr>
                         </tbody>
                     </table>
                </div>
                 <div class="notes">
                    * Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja.
                    <br>** Interpretasi Hasil Penilaian Daftar Periksa Potensi Bahaya Ergonomi Mengacu kepada Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja Bagian 5.1.
                </div>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>
</body>
</html>