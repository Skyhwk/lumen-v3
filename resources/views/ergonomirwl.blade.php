<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian (DRAFT)</title>
    <style>
        /* --- CSS Anda --- */
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            background-color: white;
        }

        .container {
            width: 100%;
            position: relative;
            box-sizing: border-box;
            border: 1px solid #000;
            padding: 11px;
        }

        h1 {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 15px;
        }

        .header-text {
            font-size: 10pt;
            text-align: left;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table,
        th,
        td {
            border: 1px solid #000;
        }

        th,
        td {
            padding: 4px;
            text-align: center;
            font-size: 10pt;
            vertical-align: middle;
        }

        td.text-left {
            text-align: left;
        }

        .table-title {
            text-align: left;
            font-weight: bold;
            padding: 4px;
            font-size: 11pt;
            background-color: #f0f0f0;
            margin-top: 5px;
            margin-bottom: 0;
        }

        /* Atur margin */

        /* CSS untuk layout float */
        .content-layout {
            width: 100%;
            display: block;
        }

        .column-left {
            width: 60%;
            float: left;
            padding-right: 10px;
            box-sizing: border-box;
        }

        .column-right {
            width: 39%;
            float: right;
            box-sizing: border-box;
            margin-top: 20px;
            /* Margin atas asli dari kolom kanan */
        }

        .bottom-row {
            clear: both;
            width: 100%;
            display: block;
            margin-top: 10px;
        }

        .bottom-column {
            float: left;
            box-sizing: border-box;
        }

        .bottom-column:nth-child(1) {
            width: 65%;
            padding-right: 10px;
        }

        .bottom-column:nth-child(2) {
            width: 35%;
        }

        /* CSS Info/Footer/Watermark dll */
        .info-header {
            font-weight: bold;
            margin-top: 8px;
            margin-bottom: 3px;
            font-size: 10pt;
            clear: both;
        }

        .info-label {
            font-weight: normal;
            width: 120px;
            float: left;
            font-size: 10pt;
        }

        .info-note {
            font-size: 10pt;
            margin-top: 5px;
            line-height: 1.2;
        }

        .footer-note {
            font-size: 9pt;
            margin-top: 15px;
            font-style: italic;
            clear: both;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        /* CSS PENTING untuk header tabel berulang */
        thead {
            display: table-header-group;
        }

        tbody {
            display: table-row-group;
        }

        tfoot {
            display: table-footer-group;
        }

        /* Jika Anda pakai tfoot */

        /* Perbaikan kecil untuk spasi info (dari revisi sebelumnya) */
        .info-line {
            margin-bottom: 3px;
            font-size: 10pt;
            min-height: 1.2em;
        }

        .info-line span {
            display: inline-block;
            vertical-align: top;
        }

        .info-line .info-label {
            width: 120px;
            float: none;
        }

        .info-line .info-separator {
            width: 5px;
            text-align: center;
        }

        .info-line .info-value {
            /* Biarkan sisa lebar */
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>LAPORAN HASIL PENGUJIAN (DRAFT)</h1>

        <div class="content-layout clearfix">
            <div class="column-left">
                <div class="table-title">DATA VARIABEL PENGUKURAN</div>
                <table cellpadding="4" cellspacing="0">
                    <thead>
                        <tr>
                            <td style="width:5%;">No</td>
                            <td style="width:30%;">VARIABEL PENGUKURAN</td>
                            <td style="width:10%;">AWAL</td>
                            <td style="width:10%;">AKHIR</td>
                            <td style="width:45%;">KETERANGAN</td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td class="text-left">Jarak Tangan Horizontal (cm)</td>
                            <td></td>
                            <td></td>
                            <td class="text-left">Jarak horizontal/proyeksi tangan yang memegang beban dengan titik
                                pusat tubuh</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td class="text-left">Jarak Tangan Vertikal (cm)</td>
                            <td></td>
                            <td></td>
                            <td class="text-left">Jarak vertikal posisi tangan yang memegang beban terhadap lantai</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td class="text-left">Sudut Asimetris (°)</td>
                            <td></td>
                            <td></td>
                            <td class="text-left">Sudut asimetri gerakan yang dibentuk antara bagian dan kaki</td>
                        </tr>
                    </tbody>
                </table>

                <div class="table-title">DATA PENGAMBIL</div>
                <table cellpadding="4" cellspacing="0">
                    <thead>
                        <tr>
                            <td style="width:5%;">No</td>
                            <td style="width:30%;">VARIABEL PENGUKURAN</td>
                            <td style="width:15%;">HASIL</td>
                            <td style="width:50%;">KETERANGAN</td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td class="text-left">Jarak Vertikal (cm)</td>
                            <td></td>
                            <td class="text-left">Jarak perpindahan beban secara vertikal antara tempat asal sampai
                                tujuan</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td class="text-left">Berat Beban (kg)</td>
                            <td></td>
                            <td class="text-left">Jumlah beban atau barang material yang dibombong/diangkat/dipindahkan
                                pekeria</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td class="text-left">Frekuensi (jumlah angkat/menit)</td>
                            <td></td>
                            <td class="text-left">Jumlah pengangkatan beban setiap menit</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td class="text-left">Durasi Waktu Kerja</td>
                            <td></td>
                            <td class="text-left">Jumlah waktu aktual durasi pekerjaan dalam hitungan jam</td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td class="text-left">Kondisi Objek</td>
                            <td></td>
                            <td class="text-left">Kondisi handling objek, terdiri dari pilihan (Bagus/Sedang/Jelek)</td>
                        </tr>
                    </tbody>
                </table>

                <div class="table-title">DATA VARIABEL PEKERJA</div>
                <table cellpadding="4" cellspacing="0">
                    <thead>
                        <tr>
                            <td style="width:5%;">No</td>
                            <td style="width:65%;">DATA VARIABEL</td>
                            <td style="width:15%;">AWAL</td>
                            <td style="width:15%;">AKHIR</td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td class="text-left">Konstanta Beban</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td class="text-left">Pengali Horizontal</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td class="text-left">Pengali Vertikal</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td class="text-left">Pengali Jarak</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td class="text-left">Pengali Asimetris</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td class="text-left">Pengali Frekuensi</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td class="text-left">Pengali Kopling</td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <div class="table-title">HASIL AKHIR</div>
                <table cellpadding="4" cellspacing="0">
                    <thead>
                        <tr>
                            <td style="width:5%;">No</td>
                            <td style="width:65%;">JENIS HASIL</td>
                            <td style="width:15%;">AWAL</td>
                            <td style="width:15%;">AKHIR</td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td class="text-left">Recommended Weight Limit (RWL)</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td class="text-left">Lifting Index</td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <div class="bottom-row clearfix">
                    <div class="bottom-column">
                        <div class="table-title">KESIMPULAN HASIL ANALISA RWL</div>
                        <table cellpadding="4" cellspacing="0" style="margin-top:0;">
                            <tbody>
                                <tr>
                                    <td height="60" style="vertical-align: top; text-align: left;"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="bottom-column">
                        <div class="table-title">DESKRIPSI SINGKAT PEKERJAAN</div>
                        <table cellpadding="4" cellspacing="0" style="margin-top:0;">
                            <tbody>
                                <tr>
                                    <td height="60" style="vertical-align: top; text-align: left;"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
            <div class="column-right">
                <div style="padding-top:0;">
                    <table cellpadding="4" cellspacing="0">
                        <thead>
                            <tr>
                                <td>No. DIP</td>
                                <td>No. Sampel</td>
                                <td>Jenis Sampel</td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td></td>
                                <td></td>
                                <td>Ergonomi</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="padding: 5px;">
                    <div class="info-header">Informasi Pelanggan</div>
                    <div class="info-line"><span class="info-label">Nama Pelanggan</span><span
                            class="info-separator">:</span><span class="info-value"></span></div>
                    <div class="info-line"><span class="info-label">Alamat / Lokasi</span><span
                            class="info-separator">:</span><span class="info-value"></span></div>
                    <div class="info-line"><span class="info-label">Sampling</span><span
                            class="info-separator">:</span><span class="info-value"></span></div>

                    <div class="info-header">Informasi Sampling</div>
                    <div class="info-line"><span class="info-label">Tanggal Sampling</span><span
                            class="info-separator">:</span><span class="info-value"></span></div>
                    <div class="info-line"><span class="info-label">Periode Analisa</span><span
                            class="info-separator">:</span><span class="info-value"></span></div>

                    <div class="info-header">Data Individu/Pekerja yang Diukur</div>
                    <div class="info-line"><span class="info-label">Nama Pekerja</span><span
                            class="info-separator">:</span><span class="info-value"></span></div>
                    <div class="info-line"><span class="info-label">Jenis Pekerjaan</span><span
                            class="info-separator">:</span><span class="info-value"></span></div>
                </div>

                <div style="margin-bottom: 10px; margin-top:20px;">
                    <div style="text-align: center; font-weight: bold; padding: 3px;">Tabel Klasifikasi Tingkat Risiko
                        Berdasarkan Nilai LI**</div>
                    <table style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <td>Nilai Lifting Index</td>
                                <td>Tingkat Risiko</td>
                                <td>Deskripsi Perhitungan</td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>&lt;1</td>
                                <td>Rendah</td>
                                <td class="text-left" style="font-size: 9pt;">Nilai ini masih dalam kategori selamat
                                    bagi sebagian besar pekerja, sehingga tidak perlu ada tindakan perbaikan pekerjaan.
                                    Namun pekerja dengan keterbatasan perlu mendapat perhatian khusus.</td>
                            </tr>
                            <tr>
                                <td>1 &lt; SI &lt; 3</td>
                                <td>Sedang</td>
                                <td class="text-left" style="font-size: 9pt;">Nilai ini tentunya akan meningkatkan
                                    risiko terhadap sebagian pekerja. Sehingga perlu dilakukan perbaikan atau
                                    perancangan kembali pada penanganan material yang membutuhkan material handling
                                    dengan LI antara 1 dan 3.</td>
                            </tr>
                            <tr>
                                <td>&gt;3</td>
                                <td>Tinggi</td>
                                <td class="text-left" style="font-size: 9pt;">Pekerjaan berisiko pada sebagian besar
                                    pekerja. Sebagian besar operator tidak dapat melakukan pekerjaan dengan aman bila
                                    nilai LI melebihi 3. Dalam hal ini perbaikan secara administratif saja tidak cukup,
                                    namun solusi yang terbaik adalah dengan mendesain ulang sistem kerja.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="info-note">
                    * Metode Analisa Mengacu kepada Peraturan Menteri Ketenagakerjaan Republik Indonesia Nomor 5 Tahun
                    2018.<br>
                    ** Tabel Klasifikasi Tingkat Risiko Mengacu kepada Peraturan Menteri Ketenagakerjaan Republik
                    Indonesia Nomor 5 Tahun 2018.
                </div>
            </div>
        </div>
        <div class="footer-note">*hasil uji ini hanya berlaku untuk sampel yang diuji, sertifikat tidak boleh
            diduplikat sebagian dengan tanpa izin tertulis dari pihak laboratorium.</div>
    </div>
</body>

</html>
