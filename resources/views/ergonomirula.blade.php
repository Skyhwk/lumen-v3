<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt; /* Reduced from 11pt */
            background-color: white;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            position: relative;
            box-sizing: border-box;
            padding: 8px; /* Reduced from 11px */
        }

        h1 {
            text-align: center;
            font-size: 12px; /* Reduced from 16pt */
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 5px;
            margin-top: 8px;
        }

        .company-name {
            font-weight: bold;
            text-align: left;
            margin-bottom: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px; /* Reduced from 10px */
        }

        table, th, td {
            border: 1px solid #000;
        }

        th, td {
            padding: 3px; /* Reduced from 4px */
            text-align: center;
            font-size: 9pt; /* Reduced from 10pt */
        }

        .text-left {
            text-align: left;
        }

        .table-title {
            text-align: center;
            font-weight: bold;
            padding: 3px;
            font-size: 9pt;
        }

        .table-secondary {
            background-color: #f0f0f0;
        }

        .table-layout {
            width: 100%;
            margin-bottom: 8px;
        }

        .table-main {
            width: 70%;
            float: left;
            padding-right: 5px;
            box-sizing: border-box;
        }

        .info-section {
            width: 29%;
            float: right;
            box-sizing: border-box;
        }

        .info-container {
            margin-top: 8px;
            margin-bottom: 8px;
        }

        .info-label {
            font-weight: normal;
            width: 110px; /* Reduced from 120px */
            float: left;
            font-size: 9pt;
        }

        .info-value {
            margin-left: 110px;
            font-size: 9pt;
        }

        .info-header {
            font-weight: bold;
            margin-top: 6px; /* Reduced from 8px */
            margin-bottom: 2px; /* Reduced from 3px */
            font-size: 9pt;
            clear: both;
        }

        .info-note {
            font-size: 7pt; /* Reduced from 8pt */
            margin-top: 4px;
            line-height: 1.1;
        }

        .arrow {
            text-align: center;
            font-size: 16pt; /* Reduced from 18pt */
            margin: 4px 0;
            display: inline-block;
            vertical-align: middle;
        }

        .note-box {
            border: 1px solid #000;
            padding: 6px; /* Reduced from 8px */
            margin-top: 8px;
            font-size: 9pt;
        }

        .watermark {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.1;
            z-index: -1;
            pointer-events: none;
            transform: rotate(-45deg);
            font-size: 100pt; /* Reduced from 120pt */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .skor-rula {
            width: 50%;
            float: left;
        }

        .box-arrow {
            margin-bottom: 8px;
            width: 100%;
            clear: both;
        }

        .empty-box {
            width: 55px; /* Reduced from 60px */
            height: 25px; /* Reduced from 30px */
            border: 1px solid #000;
            display: inline-block;
            vertical-align: middle;
            margin-left: 8px;
        }

        .footer-note {
            font-size: 7pt; /* Reduced from 8pt */
            text-align: center;
            margin-top: 8px;
            font-style: italic;
            position: absolute;
            bottom: 5px; /* Reduced from 10px */
            width: 95%;
        }

        .clearfix {
            clear: both;
        }

        .inline-block {
            display: inline-block;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>LAPORAN HASIL PENGUJIAN</h1>
        <!-- Main layout with table and information -->
        <div class="table-layout">
            <!-- Left side - Tables -->
            <div class="table-main">
                <!-- Tabel A -->
                <table cellpadding="3" cellspacing="0">
                    <tr>
                        <td rowspan="2">No</td>
                        <td rowspan="2">Jenis Skoring</td>
                        <td colspan="5" class="table-title">Skor Tabel A (Lengan dan Pergelangan Tangan)</td>
                    </tr>
                    <tr>
                        <td>Nilai</td>
                        <td>Skoring (1)</td>
                        <td>Beban (2)</td>
                        <td>Otot (3)</td>
                        <td>Total Skor Tabel A (1+2+3)</td>
                    </tr>
                    <tr>
                        <td>1</td>
                        <td class="text-left">Lengan Atas</td>
                        <td> {{$pengukuran->lengan_atas}} </td>
                        <td rowspan="4">{{$pengukuran->total_skor_A}}</td>
                        <td rowspan="4">{{$pengukuran->beban_A}}</td>
                        <td rowspan="4">{{$pengukuran->aktivitas_otot_A}}</td>
                        <td rowspan="4">{{$pengukuran->nilai_tabel_A}}</td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td class="text-left">Lengan Bawah</td>
                        <td>{{$pengukuran->lengan_bawah}}</td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td class="text-left">Pergelangan Tangan</td>
                        <td>{{$pengukuran->pergelangan_tangan}}</td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td class="text-left">Pergelangan Tangan Memuntir</td>
                        <td>{{$pengukuran->tangan_memuntir}}</td>
                    </tr>
                </table>

                <!-- Tabel B -->
                <table cellpadding="3" cellspacing="0">
                    <tr>
                        <td rowspan="2">No</td>
                        <td rowspan="2">Jenis Skoring</td>
                        <td colspan="5" class="table-title">Skor Tabel B (Leher, Badan, Kaki)</td>
                    </tr>
                    <tr>
                        <td>Nilai</td>
                        <td>Skoring (1)</td>
                        <td>Beban (2)</td>
                        <td>Otot (3)</td>
                        <td>Total Skor Tabel B (1+2+3)</td>
                    </tr>
                    <tr>
                        <td>1</td>
                        <td class="text-left">Leher</td>
                        <td>{{$pengukuran->leher}}</td>
                        <td rowspan="3">{{$pengukuran->total_skor_B}}</td>
                        <td rowspan="3">{{$pengukuran->beban_B}}</td>
                        <td rowspan="3">{{$pengukuran->aktivitas_otot_B}}</td>
                        <td rowspan="3">{{$pengukuran->nilai_tabel_B}}</td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td class="text-left">Badan</td>
                        <td>{{$pengukuran->badan}}</td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td class="text-left">Kaki</td>
                        <td>{{$pengukuran->kaki}}</td>
                    </tr>
                </table>

                <div style="width: 25%; float: left;">
                    <!-- Skor RULA Box -->
                    <table style="width: 100%; float: left;" cellpadding="3" cellspacing="0">
                        <tr>
                            <td>SKOR RULA</td>
                        </tr>
                        <tr>
                            <td>Skoring Tabel A & Tabel B</td>
                        </tr>
                    </table>
                </div>
                <div style="width: 10%; float: left; text-align: center;font-size: 16pt;">→</div>
                <div style="
                    width: 55px;
                    height: 25px;
                    border: 1px solid #000;
                    float: left;
                    margin-top: 4px;
                    text-align: center;
                    line-height: 25px;
                ">
                    {{$pengukuran->skor_rula}}
                </div>

                <div style="clear: both;"></div>

                <!-- Tabel Acuan Skor Risiko -->
                <table cellpadding="3" cellspacing="0">
                    <tr>
                        <td colspan="6" class="text-left">Tabel Acuan Skor Risiko dan Tindakan Penanganan**</td>
                    </tr>
                    <tr>
                        <td>Skor RULA</td>
                        <td>Tingkat Risiko</td>
                        <td>Kategori Risiko</td>
                        <td colspan="3">Tindakan</td>
                    </tr>
                    <tr>
                        <td>1 - 2</td>
                        <td>0</td>
                        <td>Rendah</td>
                        <td colspan="3">Tidak ada tindakan yang diperlukan</td>
                    </tr>
                    <tr>
                        <td>3 - 4</td>
                        <td>1</td>
                        <td>Sedang</td>
                        <td colspan="3">Mungkin diperlukan tindakan</td>
                    </tr>
                    <tr>
                        <td>5 - 6</td>
                        <td>2</td>
                        <td>Tinggi</td>
                        <td colspan="3">Diperlukan tindakan</td>
                    </tr>
                    <tr>
                        <td>7</td>
                        <td>3</td>
                        <td>Sangat Tinggi</td>
                        <td colspan="3">Diperlukan tindakan saat ini</td>
                    </tr>
                </table>

                <!-- Kesimpulan -->
                <div>
                    <div><strong>Kesimpulan:</strong></div>
                    <div class="note-box">
                       {{$pengukuran->result}}
                    </div>
                </div>
            </div>

            <!-- Right side - Info Section -->
            <div class="info-section">
                <table cellpadding="3" cellspacing="0">
                    <thead>
                        <tr>
                            <th>NO. LHP</th>
                            <th>NO. SAMPEL</th>
                            <th>#JNS SAMPEL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{$personal->no_lhp}}</td>
                            <td>{{$personal->no_sampel}}</td>
                            <td>Ergonomi - RULA</td>
                        </tr>
                    </tbody>
                </table>

                <div style="padding: 4px;">
                    <div class="info-header">Informasi Pelanggan</div>
                    <div style="margin-bottom: 2px; font-size: 9pt;">
                        <span class="info-label">Nama Pelanggan</span>
                        <span>: {{$personal->nama_pelanggan}} </span>
                    </div>
                    <div style="margin-bottom: 2px; font-size: 9pt;">
                        <span class="info-label">Alamat / Lokasi Sampling</span>
                        <span>: {{$personal->alamat_pelanggan}} </span>
                    </div>

                    <div class="info-header">Informasi Sampling</div>
                    <div style="margin-bottom: 2px; font-size: 9pt;">
                        <span class="info-label">Tanggal Sampling</span>
                        <span>: {{$personal->tanggal_sampling}} </span>
                    </div>
                    <div style="margin-bottom: 2px; font-size: 9pt;">
                        <span class="info-label">Periode Analisa</span>
                        <span>: </span>
                    </div>

                    <div class="info-header">Data Individu/Pekerja yang Diukur</div>
                    <div style="margin-bottom: 2px; font-size: 9pt;">
                        <span class="info-label">Nama Pekerja</span>
                        <span>: {{$personal->nama_pekerja}} </span>
                    </div>
                    <div style="margin-bottom: 2px; font-size: 9pt;">
                        <span class="info-label">Jenis Pekerjaan</span>
                        <span>: {{$personal->aktivitas_ukur}}</span>
                    </div>
                    <div style="margin-bottom: 2px; font-size: 9pt;">
                        <span class="info-label">Jenis Analisa</span>
                        <span>: Pengumpulan Data (Pengukuran & Skoring)</span>
                    </div>

                    <div style="margin-bottom: 2px; font-size: 9pt;">
                        <span class="info-label">Metode Analisa*</span>
                        <span>: Pengamatan Langsung - RULA</span>
                    </div>

                    <div class="info-note">
                        * Metode Analisa Mengacu kepada Jenis Metode yang Direkomendasikan pada
                        Pedoman Teknis Pemeriksaan K3 Pengelolaan Tambahan Peraturan Menteri
                        Ketenagakerjaan RI No.5 Tahun 2018.<br>
                        ** Tabel Acuan Skor Risiko mengacu kepada Handbook Human Factors and Ergonomic
                    </div>
                </div>
            </div>

            <div class="signature-section">
                <table class="signature-table">
                    <tr>
                        <td class="signature-left"></td>
                        <td class="signature-right">
                            <div class="signature-date">
                                Jakarta, 04 September 2025
                            </div><br>
                            <img src="{{public_path('qr_documents/ISL_STPS_25-VIII_5054.svg')}}" width="30px" height="30px" class="signature-qr" alt="QR Code" />
                            <div class="signature-text">(Tanda Tangan Digital)</div>
                        </td>
                    </tr>
                </table>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>
</body>
</html>
