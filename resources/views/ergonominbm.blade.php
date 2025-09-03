<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian</title>
    <style>
        /* CSS dengan font size yang konsisten - Layout Fixed */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 10px; /* Base font size yang konsisten */
            width: 100%;
            min-width: 800px; /* Minimum width untuk mempertahankan layout */
        }

        .header {
            text-align: center;
            margin-bottom: 5px;
        }

        .header h1 {
            font-size: 12px; /* Dikurangi untuk konsistensi */
            font-weight: bold;
            margin: 5px 0;
            text-decoration: underline;
        }

        .company-name {
            font-weight: bold;
            font-size: 10px; /* Konsisten dengan base */
            text-align: left;
            margin-bottom: 10px;
        }

        .section-title {
            font-weight: bold;
            margin: 10px 0 5px 0;
            font-size: 10px; /* Konsisten dengan base */
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px; /* Sedikit lebih kecil untuk tabel */
            margin-bottom: 10px;
            table-layout: fixed; /* Fixed table layout */
        }

        th,
        td {
            border: 1px solid black;
            padding: 3px 5px;
            text-align: center;
            vertical-align: middle;
            font-size: 9px; /* Konsisten untuk semua sel tabel */
        }

        thead {
            display: table-header-group;
        }

        tbody {
            display: table-row-group;
        }

        .body-map {
            width: 80px;
            height: auto;
            margin: 5px auto;
            display: block;
        }

        .info-section {
            margin-bottom: 10px;
        }

        .info-section p {
            margin: 3px 0;
            font-size: 9px; /* Konsisten */
        }

        .info-label {
            font-weight: normal;
            width: 120px;
            float: left;
            font-size: 9px; /* Konsisten, tidak lagi 10pt */
        }

        .info-value {
            display: inline-block;
            font-size: 9px; /* Konsisten */
        }

        .customer-info,
        .sampling-info,
        .worker-info {
            margin-left: 0;
            margin-bottom: 10px;
        }

        .customer-info h4,
        .sampling-info h4,
        .worker-info h4 {
            margin: 5px 0 2px 0;
            font-size: 10px; /* Konsisten */
            font-weight: bold;
        }

        .risk-table {
            margin-top: 5px;
        }

        .left-section p {
            font-weight: bold;
            text-align: justify;
            margin-bottom: 5px;
            font-size: 9px; /* Konsisten */
        }

        .table-note {
            font-size: 8px; /* Tetap kecil untuk catatan */
            margin-top: 3px;
            font-style: italic;
        }

        .job-description {
            margin-top: 10px;
        }

        .job-description th {
            width: 30%;
            text-align: left;
            vertical-align: top;
            font-size: 9px; /* Konsisten */
        }

        .job-description td {
            vertical-align: top;
            font-size: 9px; /* Konsisten */
        }

        .conclusion-box {
            border: 1px solid black;
            padding: 5px;
            min-height: 30px;
            margin-top: 5px;
            margin-bottom: 10px;
            font-size: 9px; /* Konsisten */
        }

        .conclusion-box .section-title {
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 10px; /* Konsisten */
        }

        /* Fixed Layout - Tidak Responsif */
        .left-section {
            width: 60%;
            float: left;
            box-sizing: border-box;
            min-width: 60%;
            max-width: 60%;
        }

        .right-section {
            width: 39%;
            float: right;
            box-sizing: border-box;
            min-width: 39%;
            max-width: 39%;
        }

        /* Pastikan layout tetap fixed untuk print */
        @media print {
            .left-section {
                width: 60% !important;
                min-width: 60% !important;
                max-width: 60% !important;
            }
            
            .right-section {
                width: 39% !important;
                min-width: 39% !important;
                max-width: 39% !important;
            }
        }

        .result-header {
            text-align: center;
            font-weight: bold;
            margin: 5px 0;
            font-size: 9px; /* Konsisten dengan tabel */
        }

        /* Styling untuk tabel nested SEBELUM/SESUDAH */
        .nested-table-container {
            padding: 0;
        }

        .nested-table {
            width: 100%;
            margin: 0;
            border: none;
        }

        .nested-table td {
            border: 1px solid black;
            width: 50%;
            text-align: center;
            font-weight: bold;
            padding: 3px;
            font-size: 9px; /* Konsisten */
        }

        .total-score {
            font-weight: bold;
            text-align: center;
            margin-top: 5px;
            font-size: 9px; /* Konsisten */
        }

        .content-container {
            width: 100%;
            min-width: 800px; /* Pastikan layout minimum */
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        
        .info-header {
            font-weight: bold;
            margin-top: 8px;
            margin-bottom: 3px;
            font-size: 10px; /* Konsisten, tidak lagi 10pt */
            clear: both;
        }

        /* Styling khusus untuk informasi di sisi kanan */
        .right-section div {
            font-size: 9px; /* Base untuk right section */
        }

        .right-section span {
            font-size: 9px; /* Konsisten untuk semua span */
        }

        /* Styling untuk div dengan margin-bottom di right section */
        .right-section div[style*="margin-bottom: 3px"] {
            margin-bottom: 3px;
            font-size: 9px; /* Konsisten, tidak lagi 10pt */
        }

        /* Styling untuk signature section yang disesuaikan dengan landscape A4 */
        .signature-section {
            width: 100%;
            margin-top: 15px;
            clear: both;
        }

        .signature-table {
            width: 100%;
            border: none !important;
            font-family: Arial, sans-serif;
            font-size: 8px;
            table-layout: fixed;
        }

        .signature-table td {
            border: none !important;
            padding: 2px;
            vertical-align: top;
        }

        .signature-left {
            width: 65%;
        }

        .signature-right {
            width: 35%;
            text-align: center;
        }

        .signature-date {
            margin-bottom: 8px;
            font-size: 8px;
        }

        .signature-qr {
            width: 60px;
            height: 60px;
            margin: 5px auto;
            display: block;
        }

        .signature-text {
            margin-top: 3px;
            font-size: 7px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>LAPORAN HASIL PENGUJIAN</h1>
    </div>
    <div class="content-container clearfix">
        <div class="left-section">
            <div class="section-title">A. KELUHAN SISTEM MUSCULOSKETALX</div>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2">NO.</th>
                        <th rowspan="2">BAGIAN</th>
                        <th colspan="2">Terdapat Keluhan</th>
                        <th rowspan="2" colspan="2">PETA BAGIAN TUBUH</th>
                        <th>NO.</th>
                        <th>BAGIAN</th>
                        <th colspan="2">Terdapat Keluhan</th>
                    </tr>
                    <tr>
                        <th>Sebelum</th>
                        <th>Sesudah</th>
                        <th></th>
                        <th></th>
                        <th>Sebelum</th>
                        <th>Sesudah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>0.</td>
                        <td style="text-align: left;">Leher atas</td>
                        <td>0</td>
                        <td>1</td>
                        <td rowspan="12" colspan="2" style="padding:0; vertical-align: top;"> 
                            <img src="https://via.placeholder.com/80x120.png?text=BODY+MAP" alt="Body Map" class="body-map">
                        </td>
                        <td>1.</td>
                        <td style="text-align: left;">Tengkuk</td>
                        <td>0</td>
                        <td>2</td>
                    </tr>
                    <tr>
                        <td>2.</td>
                        <td style="text-align: left;">Bahu kiri</td>
                        <td>1</td>
                        <td>2</td>
                        <td>3.</td>
                        <td style="text-align: left;">Bahu kanan</td>
                        <td>0</td>
                        <td>1</td>
                    </tr>
                    <tr>
                        <td>4.</td>
                        <td style="text-align: left;">Lengan atas kiri</td>
                        <td>0</td>
                        <td>1</td>
                        <td>5.</td>
                        <td style="text-align: left;">Punggung</td>
                        <td>2</td>
                        <td>3</td>
                    </tr>
                    <tr>
                        <td>6.</td>
                        <td style="text-align: left;">Lengan atas kanan</td>
                        <td>1</td>
                        <td>2</td>
                        <td>7.</td>
                        <td style="text-align: left;">Pinggang</td>
                        <td>2</td>
                        <td>3</td>
                    </tr>
                    <tr>
                        <td>8.</td>
                        <td style="text-align: left;">Pinggul</td>
                        <td>0</td>
                        <td>1</td>
                        <td>9.</td>
                        <td style="text-align: left;">Pantat</td>
                        <td>0</td>
                        <td>0</td>
                    </tr>
                    <tr>
                        <td>10.</td>
                        <td style="text-align: left;">Siku kiri</td>
                        <td>0</td>
                        <td>1</td>
                        <td>11.</td>
                        <td style="text-align: left;">Siku kanan</td>
                        <td>1</td>
                        <td>2</td>
                    </tr>
                    <tr>
                        <td>12.</td>
                        <td style="text-align: left;">Lengan bawah kiri</td>
                        <td>0</td>
                        <td>0</td>
                        <td>13.</td>
                        <td style="text-align: left;">Lengan bawah kanan</td>
                        <td>1</td>
                        <td>2</td>
                    </tr>
                    <tr>
                        <td>14.</td>
                        <td style="text-align: left;">Pergelangan tangan kiri</td>
                        <td>1</td>
                        <td>2</td>
                        <td>15.</td>
                        <td style="text-align: left;">Pergelangan tangan kanan</td>
                        <td>2</td>
                        <td>3</td>
                    </tr>
                    <tr>
                        <td>16.</td>
                        <td style="text-align: left;">Tangan kiri</td>
                        <td>0</td>
                        <td>1</td>
                        <td>17.</td>
                        <td style="text-align: left;">Tangan kanan</td>
                        <td>1</td>
                        <td>2</td>
                    </tr>
                    <tr>
                        <td>18.</td>
                        <td style="text-align: left;">Paha kiri</td>
                        <td>0</td>
                        <td>0</td>
                        <td>19.</td>
                        <td style="text-align: left;">Paha kanan</td>
                        <td>0</td>
                        <td>1</td>
                    </tr>
                    <tr>
                        <td>20.</td>
                        <td style="text-align: left;">Lutut kiri</td>
                        <td>1</td>
                        <td>2</td>
                        <td>21.</td>
                        <td style="text-align: left;">Lutut kanan</td>
                        <td>1</td>
                        <td>2</td>
                    </tr>
                    <tr>
                        <td>22.</td>
                        <td style="text-align: left;">Betis kiri</td>
                        <td>0</td>
                        <td>1</td>
                        <td>23.</td>
                        <td style="text-align: left;">Betis kanan</td>
                        <td>0</td>
                        <td>1</td>
                    </tr>
                    <tr>
                        <td>24.</td>
                        <td style="text-align: left;">Pergelangan kaki kiri</td>
                        <td>0</td>
                        <td>0</td>
                        <td colspan="2" class="result-header">HASIL AKHIR</td>
                        <td>25.</td>
                        <td style="text-align: left;">Pergelangan kaki kanan</td>
                        <td>0</td>
                        <td>1</td>
                    </tr>
                    <tr>
                        <td>26.</td>
                        <td style="text-align: left;">Kaki kiri</td>
                        <td>0</td>
                        <td>0</td>
                        <td colspan="2" class="nested-table-container">
                            <table class="nested-table">
                                <tr>
                                    <td>SEBELUM</td>
                                    <td>SESUDAH</td>
                                </tr>
                            </table>
                        </td>
                        <td>27.</td>
                        <td style="text-align: left;">Kaki kanan</td>
                        <td>0</td>
                        <td>1</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="total-score">TOTAL SKOR KIRI</td>
                        <td>8</td>
                        <td>15</td>
                        <td class="nested-table-container">
                            18
                        </td>
                        <td class="nested-table-container">
                            35
                        </td>
                        <td colspan="2" class="total-score">TOTAL SKOR KANAN</td>
                        <td>10</td>
                        <td>20</td>
                    </tr>
                    <tr>
                      <td rowspan="3" colspan="2">KESIMPULAN AKHIR KELUHAN SISTEM MUSCULOSKETAL</td>
                      <td colspan="9" height="40"></td>
                    </tr>
                </tbody>
            </table>

            <div class="section-title" style="margin-top: 15px;">B. KELUHAN SUBJEKTIF</div>
            <table>
                <thead>
                    <tr>
                        <th>JENIS PENGUKURAN</th>
                        <th>TOTAL SKOR</th>
                        <th>TINGKAT RISIKO</th>
                        <th>KATEGORI RISIKO</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: left;">SEBELUM BEKERJA</td>
                        <td>18</td>
                        <td>0</td>
                        <td>Rendah</td>
                    </tr>
                    <tr>
                        <td style="text-align: left;">SETELAH BEKERJA</td>
                        <td>35</td>
                        <td>1</td>
                        <td>Sedang</td>
                    </tr>
                    <tr>
                     <td>KESIMPULAN AKHIR KELUHAN SUBJEKTIF</td>
                     <td colspan="3" height="40"></td>
                    </tr>
                </tbody>
            </table>
            <div class="job-description">
                <table>
                        <tr>
                            <th>DESKRIPSI SINGKAT PEKERJAAN PEKERJA</th>
                            <td colspan="2" height="60" style="vertical-align: top; text-align:left;">
                                Pekerjaan operator komputer dengan posisi duduk selama 8 jam per hari
                            </td>
                        </tr>
                </table>
            </div>
        </div>
        <div class="right-section">
            <div style="margin-top: 30px;">
                <table>
                    <thead>
                        <tr>
                            <th>No. LHP</th>
                            <th>No. Sampel</th>
                            <th>Jenis Sampel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>LHP/001/2025</td>
                            <td>S001</td>
                            <td>Lingkungan</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="padding: 5px;">
                    <div class="info-header">Informasi Pelanggan</div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Nama Pelanggan</span>
                        <span>: PT. Contoh Perusahaan </span>
                    </div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Alamat / Lokasi Sampling</span>
                        <span>: Jakarta Selatan </span>
                    </div>

                    <div class="info-header">Informasi Sampling</div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Tanggal Sampling</span>
                        <span>: 01 September 2025 </span>
                    </div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Periode Analisa</span>
                        <span>: 01-03 September 2025 </span>
                    </div>

                    <div class="info-header">Data Individu/Pekerja yang Diukur</div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Nama Pekerja</span>
                        <span>: John Doe </span>
                    </div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Jenis Pekerjaan</span>
                        <span>: Operator Komputer</span>
                    </div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Jenis Analisa</span>
                        <span>: Pengumpulan Data (Pengukuran & Skoring)</span>
                    </div>

                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Metode Analisa*</span>
                        <span>: Kuesioner Nordic Body Map</span>
                    </div>

                    <div class="info-header">Informasi Data Individu/Pekerja yang Diukur</div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Nama</span>
                        <span>: John Doe </span>
                    </div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Usia</span>
                        <span>: 28 Tahun</span>
                    </div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Lama Bekerja</span>
                        <span>: 3 Tahun</span>
                    </div>
            </div>
            <div class="risk-table">
                <p>**Tabel Acuan Skor Risiko dan Tindakan Perbaikan</p>
                <table>
                    <thead>
                        <tr>
                            <th>Total Skor Keluhan Individu</th>
                            <th>Tingkat Risiko</th>
                            <th>Kategori Risiko</th>
                            <th>Tindakan Perbaikan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>0-20</td>
                            <td>0</td>
                            <td>Rendah</td>
                            <td style="text-align: left;">Belum diperlukan adanya tindakan perbaikan</td>
                        </tr>
                        <tr>
                            <td>21-41</td>
                            <td>1</td>
                            <td>Sedang</td>
                            <td style="text-align: left;">Mungkin diperlukan tindakan dikemudian hari</td>
                        </tr>
                        <tr>
                            <td>42-62</td>
                            <td>2</td>
                            <td>Tinggi</td>
                            <td style="text-align: left;">Diperlukan tindakan segera</td>
                        </tr>
                        <tr>
                            <td>63-84</td>
                            <td>3</td>
                            <td>Sangat Tinggi</td>
                            <td style="text-align: left;">Diperlukan tindakan menyeluruh sesegera mungkin</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Signature Section yang disesuaikan -->
            <div class="signature-section">
                <table class="signature-table">
                    <tr>
                        <td class="signature-left"></td>
                        <td class="signature-right">
                            <div class="signature-date">
                                Jakarta, 03 September 2025
                            </div>
                            <img src="https://via.placeholder.com/60x60.png?text=QR" class="signature-qr" alt="QR Code" />
                            <div class="signature-text">(Tanda Tangan Digital)</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>