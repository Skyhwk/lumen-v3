<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian</title>
    <style>
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
            padding:0;
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
            margin-top: 8px;
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
        /* header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: fixed;
        }

        .header-table td {
            border: none;
            padding: 10px;
            vertical-align: middle;
            height: 60px;
        }

        .header-table .left-cell {
            width: 33.33%;
            text-align: left;
            padding-left: 20px;
        }

        .header-table .center-cell {
            width: 33.33%;
            text-align: center;
        }

        .header-table .right-cell {
            width: 33.33%;
            text-align: right;
            padding-right: 50px;
        }
        .header-logo {
            height: 50px;
            width: auto;
            display: block;
        }
        .info-table {
            border: 0;
            margin-bottom: 6px;
        }

        .info-table td {
            border: 0;
            padding: 0px 2px;
            font-size: 8pt;
            vertical-align: top;
        }
    </style>
</head>
<body>
    <div class="content-container clearfix" >
        <div class="left-section">
            
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
                        <td>{{$pengukuran->sebelum->skor_leher_atas}}</td>
                        <td>{{$pengukuran->setelah->skor_leher_atas}}</td>
                        <td rowspan="12" colspan="2" style="padding:0; vertical-align: top;"> <img
                                src="{{ public_path('dokumen/img_ergo/nbm/anatomi.jpg') }}" alt="Body Map" class="body-map">
                        </td>
                        <td>1.</td>
                        <td style="text-align: left;">Tengkuk</td>
                        <td>{{$pengukuran->sebelum->skor_tengkuk}}</td>
                        <td>{{$pengukuran->setelah->skor_tengkuk}}</td>
                    </tr>
                    <tr>
                        <td>2.</td>
                        <td style="text-align: left;">Bahu kiri</td>
                        <td>{{$pengukuran->sebelum->skor_bahu_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_bahu_kiri}}</td>
                        <td>3.</td>
                        <td style="text-align: left;">Bahu kanan</td>
                        <td>{{$pengukuran->sebelum->skor_bahu_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_bahu_kanan}}</td>
                    </tr>
                    <tr>
                        <td>4.</td>
                        <td style="text-align: left;">Lengan atas kiri</td>
                        <td>{{$pengukuran->sebelum->skor_lengan_atas_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_lengan_atas_kiri}}</td>
                        <td>5.</td>
                        <td style="text-align: left;">Punggung</td>
                        <td>{{$pengukuran->sebelum->skor_lengan_atas_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_lengan_atas_kiri}}</td>
                    </tr>
                    <tr>
                        <td>6.</td>
                        <td style="text-align: left;">Lengan atas kanan</td>
                        <td>{{$pengukuran->sebelum->skor_lengan_atas_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_lengan_atas_kanan}}</td>
                        <td>7.</td>
                        <td style="text-align: left;">Pinggang</td>
                        <td>{{$pengukuran->sebelum->skor_pinggang}}</td>
                        <td>{{$pengukuran->setelah->skor_pinggang}}</td>
                    </tr>
                    <tr>
                        <td>8.</td>
                        <td style="text-align: left;">Pinggul</td>
                        <td>{{$pengukuran->sebelum->skor_pinggul}}</td>
                        <td>{{$pengukuran->setelah->skor_pinggul}}</td>
                        <td>9.</td>
                        <td style="text-align: left;">Pantat</td>
                        <td>{{$pengukuran->sebelum->skor_pantat}}</td>
                        <td>{{$pengukuran->setelah->skor_pantat}}</td>
                    </tr>
                    <tr>
                        <td>10.</td>
                        <td style="text-align: left;">Siku kiri</td>
                        <td>{{$pengukuran->sebelum->skor_siku_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_siku_kiri}}</td>
                        <td>11.</td>
                        <td style="text-align: left;">Siku kanan</td>
                        <td>{{$pengukuran->sebelum->skor_siku_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_siku_kanan}}</td>
                    </tr>
                    <tr>
                        <td>12.</td>
                        <td style="text-align: left;">Lengan bawah kiri</td>
                        <td>{{$pengukuran->sebelum->skor_lengan_bawah_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_lengan_bawah_kiri}}</td>
                        <td>13.</td>
                        <td style="text-align: left;">Lengan bawah kanan</td>
                        <td>{{$pengukuran->sebelum->skor_lengan_bawah_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_lengan_bawah_kanan}}</td>
                    </tr>
                    <tr>
                        <td>14.</td>
                        <td style="text-align: left;">Pergelangan tangan kiri</td>
                        <td>{{$pengukuran->sebelum->skor_pergelangan_tangan_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_pergelangan_tangan_kiri}}</td>
                        <td>15.</td>
                        <td style="text-align: left;">Pergelangan tangan kanan</td>
                        <td>{{$pengukuran->sebelum->skor_pergelangan_tangan_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_pergelangan_tangan_kanan}}</td>
                    </tr>
                    <tr>
                        <td>16.</td>
                        <td style="text-align: left;">Tangan kiri</td>
                        <td>{{$pengukuran->sebelum->skor_tangan_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_tangan_kiri}}</td>
                        <td>17.</td>
                        <td style="text-align: left;">Tangan kanan</td>
                        <td>{{$pengukuran->sebelum->skor_tangan_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_tangan_kanan}}</td>
                    </tr>
                    <tr>
                        <td>18.</td>
                        <td style="text-align: left;">Paha kiri</td>
                        <td>{{$pengukuran->sebelum->skor_paha_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_paha_kiri}}</td>
                        <td>19.</td>
                        <td style="text-align: left;">Paha kanan</td>
                        <td>{{$pengukuran->sebelum->skor_paha_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_paha_kanan}}</td>
                    </tr>
                    <tr>
                        <td>20.</td>
                        <td style="text-align: left;">Lutut kiri</td>
                        <td>{{$pengukuran->sebelum->skor_lutut_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_lutut_kiri}}</td>
                        <td>21.</td>
                        <td style="text-align: left;">Lutut kanan</td>
                        <td>{{$pengukuran->sebelum->skor_lutut_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_lutut_kanan}}</td>
                    </tr>
                    <tr>
                        <td>22.</td>
                        <td style="text-align: left;">Betis kiri</td>
                        <td>{{$pengukuran->sebelum->skor_betis_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_betis_kiri}}</td>
                        <td>23.</td>
                        <td style="text-align: left;">Betis kanan</td>
                        <td>{{$pengukuran->sebelum->skor_betis_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_betis_kanan}}</td>
                    </tr>
                    <tr>
                        <td>24.</td>
                        <td style="text-align: left;">Pergelangan kaki kiri</td>
                        <td>{{$pengukuran->sebelum->skor_pergelangan_kaki_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_pergelangan_kaki_kiri}}</td>
                        <td colspan="2" class="result-header">HASIL AKHIR</td>
                        <td>25.</td>
                        <td style="text-align: left;">Pergelangan kaki kanan</td>
                        <td>{{$pengukuran->sebelum->skor_pergelangan_kaki_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_pergelangan_kaki_kanan}}</td>
                    </tr>
                    <tr>
                        <td>26.</td>
                        <td style="text-align: left;">Kaki kiri</td>
                        <td>{{$pengukuran->sebelum->skor_kaki_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_kaki_kiri}}</td>
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
                        <td>{{$pengukuran->sebelum->skor_kaki_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_kaki_kanan}}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="total-score">TOTAL SKOR KIRI</td>
                        <td>{{$pengukuran->sebelum->skor_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_kiri}}</td>
                        <td class="nested-table-container">
                            {{$pengukuran->sebelum->total_skor}}
                        </td>
                        <td class="nested-table-container">
                            {{$pengukuran->setelah->total_skor}}
                        </td>
                        <td colspan="2" class="total-score">TOTAL SKOR KANAN</td>
                        <td>{{$pengukuran->sebelum->skor_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_kanan}}</td>
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
                        <td>{{$pengukuran->sebelum->total_skor}}</td>
                        <td>{{$pengukuran->sebelum->tingkat_resiko}}</td>
                        <td>{{$pengukuran->sebelum->kategori_risiko}}</td>
                    </tr>
                    <tr>
                        <td style="text-align: left;">SETELAH BEKERJA</td>
                        <td>{{$pengukuran->setelah->total_skor}}</td>
                        <td>{{$pengukuran->setelah->tingkat_resiko}}</td>
                        <td>{{$pengukuran->setelah->kategori_risiko}}</td>
                    </tr>
                    <tr>
                     <td>KESIMPULAN AKHIR KELUHAN SUBJEKTIF</td>
                     <td colspan="3" height="40">
                        Skor NBM setelah bekerja: {{$pengukuran->setelah->total_skor}}.
                        Tingkat risiko: {{$pengukuran->setelah->tingkat_risiko}} ({{$pengukuran->setelah->kategori_risiko}})
                        Tindakan perbaikan: {{$pengukuran->setelah->tindakan_perbaikan}}.
                     </td>
                    </tr>
                </tbody>
            </table>
            <div class="job-description">
                <table>
                        <tr>
                            <th>DESKRIPSI SINGKAT PEKERJAAN PEKERJA</th>
                            <td colspan="2" height="60" style="vertical-align: top; text-align:left;">
                                {{$personal->aktivitas}}
                            </td>
                        </tr>
                </table>
            </div>
            <ul style="font-family: Arial, sans-serif; font-size: 8px; text-align: left; list-style-type: none; padding-left: 10px; margin: 0;">
                <li>
                    <sup>*</sup> Metode Analisis Mengacu kepada Jenis Metode yang Direkomendasikan Pada Pedoman Teknis<br>
                    Penerapan K3 Penjelasan Tambahan Menteri Ketenagakerjaan Nomor 5 Tahun 2018.
                </li>
                <li>
                    <sup>**</sup> Tabel Acuan Skor Risiko mengacu kepada <i>Handbook Human Factors and<br>
                    Ergonomics Methods</i> by Neville Stanton et al, 2005.
                </li>
            </ul>
        </div>
        <div class="right-section">
            <div>
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
                            <td>{{$personal->no_lhp}}</td>
                            <td>{{$personal->no_sampel}}</td>
                            <td>Ergonomi NBM</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="padding: 5px;">
                    <div class="info-header">Informasi Pelanggan</div>
                    <table class="info-table">
                        <tr>
                            <td style="width: 25%; text-align:start;">Nama Pelanggan</td>
                            <td style="width: 3%;">:</td>
                            <td style="width: 72%;text-align:start; ">{{ strtoupper($personal->nama_pelanggan) }}</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Alamat / Lokasi Sampling</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">{{ $personal->alamat_pelanggan }}</td>
                        </tr>
                    </table>

                    <div class="info-header">Informasi Sampling</div>
                    <table class="info-table">
                        <tr>
                            <td style="width: 25%; text-align:start;">Tanggal</td>
                            <td style="width: 3%;">:</td>
                            <td style="width: 72%; text-align:start;">{{ $personal->tanggal_sampling }}</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">>Periode Analisis</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">{{ $personal->periode_analisis }}</td>
                        </tr>
                    </table>

                    <div class="info-header">Data Individu/Pekerja yang Diukur</div>
                    <table class="info-table">
                        <tr>
                            <td style="width: 25%; text-align:start;">Nama</td>
                            <td style="width: 3%;">:</td>
                            <td style="width: 72%;text-align:start;">{{ $personal->nama_pekerja }}</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Usia</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">{{ $personal->usia }} Tahun</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Jenis Pekerjaan</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">{{$personal->aktivitas_ukur}}</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Lama Bekerja</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">{{ $personal->lama_kerja }} Tahun</td>
                        </tr>
                    </table>
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
                @if($ttd != null)
                    @if($ttd->qr_path != null)
                        <table class="signature-table">
                            <tr>
                                <td class="signature-left"></td>
                                <td class="signature-right">
                                    <div class="signature-date">
                                        {{ $ttd->tanggal }}
                                    </div><br>
                                    <div class="signature-text">
                                            <img src="{{ $ttd->qr_path }}" width="25" height="25" alt="ttd">
                                    </div>
                                </td>
                            </tr>
                        </table>
                    @else
                        <table class="signature-table">
                            <tr>
                                <td class="signature-left"></td>
                                <td class="signature-right" style="text-align: center;">
                                    <div class="signature-date">
                                        Tangerang, 13 Agustus 2025
                                    </div><br><br><br>
                                    <div class="signature-text">
                                        <strong>(Abidah Walfathiyyah)</strong><br>
                                        <span>Technical Control Supervisor</span>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    @endif
                @endif
            </div>
        </div>
    </div>
</body>
</html>