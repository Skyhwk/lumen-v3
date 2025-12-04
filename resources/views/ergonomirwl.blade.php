<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian</title>
    <style>
        /* --- CSS Anda --- */
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        .container {
            width: 100%;
            position: relative;
            box-sizing: border-box;
            padding: 11px;
        }

        h1 {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 5px;
        }

        .header-text {
            font-size: 10px;
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
            font-size: 10px;
            vertical-align: middle;
        }

        td.text-left {
            text-align: left;
        }

        .table-title {
            text-align: left;
            font-weight: bold;
            padding: 4px;
            font-size: 11px;
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
            width: 59%;
            float: left;
            padding-right: 10px;
            box-sizing: border-box;
        }

        .column-right {
            width: 39%;
            float: right;
            box-sizing: border-box;
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
            font-size: 10px;
            clear: both;
        }

        .info-label {
            font-weight: normal;
            width: 120px;
            float: left;
            font-size: 10px;
        }

        .info-note {
            font-size: 10px;
            margin-top: 5px;
            line-height: 1.2;
        }

        .footer-note {
            font-size: 9px;
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
            font-size: 10px;
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
        /* info bio */
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
    <div class="container">
        <div class="content-layout clearfix">
            <div class="column-left">
                <div class="table-title">Komponen</div>
                <table cellpadding="4" cellspacing="0" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <td style="width:5%;">No</td>
                            <td style="width:55%;">VARIABEL PENGUKURAN</td>
                            <td style="width:20%;">Satuan</td>
                            <td style="width:20%;">Hasil</td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td class="text-left">Jarak Angkut Vertikal</td>
                            <td>cm</td>
                            <td>{{ $pengukuran->jarak_vertikal }}</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td class="text-left">Berat Beban Angkat</td>
                            <td>kg</td>
                            <td>{{ $pengukuran->berat_beban }}</td>
                            
                        </tr>
                        <tr>
                            <td>3</td>
                            <td class="text-left">Frekuensi Angkat</td>
                            <td>kali/menit</td>
                            <td>{{$pengukuran->frekuensi_jumlah_angkatan}}</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td class="text-left">Durasi Angkat</td>
                            <td>Jam</td>
                            <td>{{$pengukuran->durasi_jam_kerja}}</td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td class="text-left">Klasifikasi Pegangan</td>
                            <td>-</td>
                            <td>{{$pengukuran->kopling_tangan}}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="table-title">VARIABEL PEMBEBANAN</div>
                <table cellpadding="4" cellspacing="0" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <td style="width:5%;">No</td>
                            <td style="width:55%;">Data Variabel</td>
                            <td style="width:20%;">Asal</td>
                            <td style="width:20%;">TUJUAN</td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td class="text-left">Load Constant (LC)</td>
                            <td>{{$pengukuran->konstanta_beban_awal}}</td>
                            <td class="text-left">{{$pengukuran->konstanta_beban_akhir}}</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td class="text-left">Horizontal Multiplier (HM)</td>
                            <td>{{$pengukuran->pengali_horizontal_awal}}</td>
                            <td class="text-left">{{$pengukuran->pengali_horizontal_akhir}}</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td class="text-left">Vertical Multiplier (VM)</td>
                            <td>{{$pengukuran->pengali_vertikal_awal}}</td>
                            <td class="text-left">{{$pengukuran->pengali_vertikal_akhir}}</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td class="text-left">Distance Multiplier (DM)</td>
                            <td>{{$pengukuran->pengali_jarak_awal}}</td>
                            <td class="text-left">{{$pengukuran->pengali_jarak_akhir}}</td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td class="text-left">Asymmetric Multiplier (AM)</td>
                            <td>{{$pengukuran->pengali_asimetris_awal}}</td>
                            <td class="text-left">{{$pengukuran->pengali_asimetris_akhir}}</td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td class="text-left">Frequency Multiplier (FM)</td>
                            <td>{{$pengukuran->pengali_frekuensi_awal}}</td>
                            <td class="text-left">{{$pengukuran->pengali_frekuensi_akhir}}</td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td class="text-left">Coupling Multiplier (CM)</td>
                            <td>{{$pengukuran->pengali_kopling_awal}}</td>
                            <td class="text-left">{{$pengukuran->pengali_kopling_akhir}}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="table-title">HASIL PERHITUNGAN</div>
                <table cellpadding="4" cellspacing="0" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <td style="width:5%;">No</td>
                            <td style="width:55%;">JENIS HASIL</td>
                            <td style="width:20%;">AWAL</td>
                            <td style="width:20%;">TUJUAN</td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td class="text-left">Recommended Weight Limit (RWL)</td>
                            <td>{{$pengukuran->nilai_beban_rwl_awal}}</td>
                            <td>{{$pengukuran->nilai_beban_rwl_akhir}}</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td class="text-left">Lifting Index (LI)</td>
                            <td>{{$pengukuran->lifting_index_awal}}</td>
                            <td>{{$pengukuran->lifting_index_akhir}}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="bottom-row clearfix">
                    <div class="bottom-column">
                        <div class="table-title">KESIMPULAN HASIL ANALISA RWL</div>
                        <table cellpadding="4" cellspacing="0" style="margin-top:0;">
                            <tbody>
                                <tr>
                                    <td style="width:5%;">1.</td>
                                    <td style="vertical-align: top; text-align: left;">
                                        Berat Beban Aktual = {{$pengukuran->berat_beban}} kg, Berat Beban Disarankan (RWL) = {{$pengukuran->nilai_beban_rwl_awal}} kg - {{$pengukuran->nilai_beban_rwl_akhir}} kg
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:5%;">2.</td>
                                    <td style="vertical-align: top; text-align: left;">
                                        LI Asal yaitu {{$pengukuran->lifting_index_awal}},masuk tingkat resiko <b>{{$pengukuran->result_li_awal['tingkatResiko']}}</b>,{{$pengukuran->result_li_awal['result']}}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:5%;">3.</td>
                                    <td style="vertical-align: top; text-align: left;">
                                        LI Tujuan yaitu {{$pengukuran->lifting_index_akhir}},masuk tingkat resiko <b>{{$pengukuran->result_li_akhir['tingkatResiko']}}</b>,{{$pengukuran->result_li_akhir['result']}}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="bottom-column">
                        <div class="table-title">DESKRIPSI SINGKAT PEKERJAAN</div>
                        <table cellpadding="4" cellspacing="0" style="margin-top:0;">
                            <tbody>
                                <tr>
                                    <td style="vertical-align: top; text-align: left;">
                                        {{$personal->divisi}}
                                        @if($personal->aktivitas_ukur != null && $personal->aktivitas_ukur != '')
                                            ,<br> {{$personal->aktivitas_ukur}}
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
            <div class="column-right">
                <div class="table-title">&nbsp;</div>
                <div style="padding-top:0;">
                    <table cellpadding="4" cellspacing="0">
                        <thead>
                            <tr>
                                <td>No. LHP</td>
                                <td>No. Sampel</td>
                                <td>Jenis Sampel</td>
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
                </div>

                <div style="padding: 2px;">
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
                            <td style="width: 72%;text-align:start;">{{ $personal->alamat_pelanggan }}</td>
                        </tr>
                    </table>
                    <!-- informasi sampling -->
                    <div class="info-header">Informasi Sampling</div>
                    <table class="info-table">
                        <tr>
                            <td style="width: 25%; text-align:start;">Tanggal Sampling</td>
                            <td style="width: 3%;">:</td>
                            <td style="width: 72%;text-align:start; ">{{$personal->tanggal_sampling}}</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Jenis Analisa</td>
                            <td style="width: 3%;">:</td>
                            <td style="width: 72%;text-align:start;">Pengumpulan Data (Pengukuran dan Perhitungan)</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Metode Analisa*</td>
                            <td style="width: 3%;">:</td>
                            <td style="width: 72%;text-align:start;">Recommended Weight Limit (RWL) Observasi Sistem</td>
                        </tr>
                    </table>
                    <!-- individu -->
                    <div class="info-header">Data Individu/Pekerja yang Diukur</div>
                    <table class="info-table">
                        <tr>
                            <td style="width: 25%; text-align:start;">Nama Pekerja</td>
                            <td style="width: 3%;">:</td>
                            <td style="width: 72%;text-align:start; ">{{ $personal->nama_pekerja }}</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Usia</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">{{ $personal->usia }} Tahun</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Lama Berkerja</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">
                                {{$personal->lama_kerja}}
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="margin-bottom: 10px; margin-top:8px;">
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
                                <td class="text-left" style="font-size: 9px;">Tindakan ada masalah dengan pekerjaan mengakat, maka tidak di perlukan perbaikan terhadap pekerjaan, tetapi tetap terus mendapatkan perhatian sehingga nilai LI dapat di pertahankan < 1</td>
                            </tr>
                            <tr>
                                <td>1 - &lt; 3</td>
                                <td>Sedang</td>
                                <td class="text-left" style="font-size: 9px;">Ada beberapa masalah dari beberapa parameter anggkat, sehingga perlu di lakukan pengecekan dan perbaikan dan redesain segera pada parameter yang menyebabkan nilai LI sedang, Upayakan perbaikan sehingga nilai LI < 1.</td>
                            </tr>
                            <tr>
                                <td>&gt;3</td>
                                <td>Tinggi</td>
                                <td class="text-left" style="font-size: 9px;">Terdapat banyak permesalahan pada parameter angkat,sehingga perlu dilakukan pengecekan dan perbaikan sesegera mungkin secara menyeluruh terhadap parameter-parameter yang menyebabkan nilai LI tinggi. Upayakan perbaikan sehingga nilai LI < 1.</td>
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
                <table style="width: 100%; margin-top: 10px; border: none;">
                        <tr>
                        <td style="width: 50%; border: none;"></td>

                        <td style="width: 50%; border: none; text-align: center; vertical-align: top;">
                            
                            <div style="margin-bottom: 5px;">
                                Tangerang, {{ $ttd->tanggal ?? '13 Agustus 2025' }}
                            </div>

                            @if($ttd && $ttd->qr_path)
                                <br><br>
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
    </div>
</body>

</html>
