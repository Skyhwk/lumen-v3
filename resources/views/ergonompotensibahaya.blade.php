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
            font-size: 11px; /* Diperbesar dari 9px */
            line-height: 1.2;
        }

        .page-container {
            width: 100%;
        }

        .main-header-title {
            text-align: center;
            font-weight: bold;
            font-size: 1.5em;
            margin-bottom: 15px;
            text-decoration: underline;
        }

        /* Layout utama menggunakan table - lebih stabil untuk mPDF */
        .main-layout-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px;
            table-layout: fixed;
        }

        .main-layout-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .column-left-cell {
            width: 55%;
        }

        .column-right-cell {
            width: 45%;
        }
        
        .section {
            border: 1px solid #000;
            padding: 6px; /* Diperbesar dari 4px */
            margin-bottom: 10px; /* Diperbesar dari 8px */
            page-break-inside: avoid;
        }

        .section-bagian-bawah {
            border: 1px solid #000;
            padding: 6px; /* Diperbesar dari 4px */
            margin-bottom: 10px;
            page-break-before: always;
            page-break-inside: avoid;
        }

        .section-title {
            font-weight: bold;
            padding: 4px 6px; /* Diperbesar */
            margin: -6px -6px 6px -6px; /* Disesuaikan dengan padding section */
            border-bottom: 1px solid #000;
            background-color: #f5f5f5; /* Tambahan background untuk kontras */
            font-size: 12px; /* Font khusus untuk title */
        }

        .table-potensi-bahaya {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px; /* Diperbesar dari 5px */
            table-layout: fixed;
            page-break-inside: auto;
            font-size: 10px; /* Font khusus untuk tabel */
        }

         /* Fixed width untuk kolom agar konsisten */
        .table-potensi-bahaya th:nth-child(1),
        .table-potensi-bahaya td:nth-child(1) {
            width: 25%; /* Kolom Kategori */
            font-weight: bold; /* Tambahan styling untuk kategori */
        }

        .table-potensi-bahaya th:nth-child(2),
        .table-potensi-bahaya td:nth-child(2) {
            width: 65%; /* Kolom Potensi Bahaya */
        }

        .table-potensi-bahaya th:nth-child(3),
        .table-potensi-bahaya td:nth-child(3) {
            width: 10%; /* Kolom Skor */
            text-align: center;
            font-weight: bold; /* Tambahan untuk skor */
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px; /* Diperbesar dari 5px */
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #000;
            padding: 4px; /* Diperbesar dari 3px */
            text-align: left;
            vertical-align: top;
            line-height: 1.3;
        }

        th {
            font-weight: bold;
            text-align: center;
            background-color: #f0f0f0; /* Background untuk header */
            font-size: 10px;
        }

        .text-input-space {
            text-align: left !important;
            margin: 0 !important;
            padding: 2px !important; /* Tambahan padding */
            min-height: 1.8em; /* Diperbesar */
            border-bottom: 1px dotted #ccc; /* Garis bantuan */
        }

        .multi-line-input {
            width: 100%;
            border: 1px solid #000;
            padding: 6px; /* Diperbesar dari 4px */
            min-height: 50px; /* Diperbesar dari 40px */
            line-height: 1.4;
            font-size: 10px;
        }

        .interpretasi-table td { 
            text-align: center; 
            font-size: 10px;
        }
        
        .interpretasi-table td:last-child { 
            text-align: left; 
        }

        .uraian-tugas-table td { 
            height: 2.2em; /* Diperbesar dari 1.8em */
            font-size: 10px;
        }

        /* Signature di dalam layout table */
        .signature-in-column {
            text-align: right;
            margin-top: 15px;
            font-size: 9px; /* Diperbesar dari 8px */
            border: 1px solid #fcfcfcff;
            padding-top: 8px;
        }

        .signature-date {
            margin-bottom: 5px;
        }

        .signature-name {
            margin-top: 25px;
            font-weight: bold;
            text-decoration: underline;
        }

        .signature-title {
            font-size: 8px; /* Diperbesar dari 7px */
            margin-top: 2px;
        }

         /* Alternatif untuk memastikan signature di pojok kanan */
        .signature-container {
            display: block;
            width: 100%;
            text-align: right;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
        }

        .signature-content {
            display: inline-block;
            text-align: center;
            font-size: 9px; /* Diperbesar dari 8px */
        }

        /* HEADER TABEL */
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
            margin-bottom: 8px; /* Diperbesar dari 6px */
            font-size: 10px;
        }

        .info-table td, .info-table th {
            border: 0;
            padding: 2px 4px; /* Diperbesar dari 0px 2px */
            font-size: 10px; /* Diperbesar dari 8pt */
            vertical-align: top;
        }

        .info-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            border: 0;
            padding: 4px;
        }

        .info-table td:first-child {
            border: 0;
            text-align: center;
            padding: 4px;
        }

        .info-table td:nth-child(2) {
            border: 0;
            text-align: center;
            padding: 4px;
        }

        .info-table td:nth-child(3) {
            border: 0;
            text-align: center;
            padding: 4px;
        }

        /* Styling khusus untuk total score table */
        .total-score-table {
            margin-top: -1px;
            border-top: 2px solid #000; /* Buat border lebih tebal */
        }

        .total-score-table td {
            font-weight: bold;
            background-color: #f8f8f8;
            font-size: 11px;
        }

        /* Manual load section improvements */
        .manual-load-section table {
            margin-bottom: 2px;
        }

        .manual-load-section table:first-of-type {
            margin-bottom: -1px;
        }

        /* Rekap table styling */
        .rekap-table td {
            font-weight: bold;
            font-size: 11px;
            background-color: #f8f8f8;
        }

        /* CSS khusus untuk mPDF - layout tetap */
        @media print {
            .column-right {
                position: fixed !important;
                right: 10px !important;
                top: 80px !important; /* Sesuaikan dengan posisi awal */
                width: 390px !important;
            }
            
            .column-left {
                width: 500px !important;
            }
        }

        /* Styling untuk rowspan kategori */
        .table-potensi-bahaya td[rowspan] {
            background-color: #f9f9f9;
            text-align: center;
            vertical-align: middle;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Main Layout Table -->
        <table class="main-layout-table">
            <tr>
                <td class="column-left-cell">
                    <!-- KONTEN KIRI -->
                    <div class="section">
                        <!-- Tabel bagian atas -->
                        <table class="table-potensi-bahaya">
                            <thead>
                                <tr>
                                    <th>Kategori</th>
                                    <th>Potensi Bahaya</th>
                                    <th>Skor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($groupedAtas as $kategori => $rows)
                                    @foreach($rows as $i => $row)
                                        <tr>
                                            {{-- tampilkan kategori hanya sekali dengan rowspan --}}
                                            @if($i === 0)
                                                <td style="background-color: #f9f9f9; text-align: center; vertical-align: middle; font-weight: bold;"  rowspan="{{ count($rows) }}">{{ $kategori }}</td>
                                            @endif
                                            <td>{{ $row['potensi'] }}</td>
                                            <td style="text-align: center; font-weight: bold;">{{ $row['skor'] }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="section-bagian-bawah">
                        <div class="section-title">II. Daftar Periksa Potensi Bahaya Tubuh Bagian Bawah</div>
                        <!-- Tabel bagian bawah -->
                        <table class="table-potensi-bahaya">
                            <thead>
                                <tr>
                                    <th>Kategori</th>
                                    <th>Potensi Bahaya</th>
                                    <th>Skor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($groupedBawah as $kategori => $rows)
                                    @foreach($rows as $i => $row)
                                        <tr>
                                            @if($i === 0)
                                                <td style="background-color: #f9f9f9; text-align: center; vertical-align: middle; font-weight: bold;"  rowspan="{{ count($rows) }}" rowspan="{{ count($rows) }}">{{ $kategori }}</td>
                                            @endif
                                            <td>{{ $row['potensi'] }}</td>
                                            <td style="text-align: center; font-weight: bold;">{{ $row['skor'] }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                        <table class="total-score-table">
                            <tr>
                                <td style="width: 70%;">Total Skor I dan II</td>
                                <td style="width: 30%;"><div class="text-input-space"></div></td>
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
                                <td style="width: 70%;">Skor Langkah Akhir</td>
                                <td style="width: 30%;"><div class="text-input-space"></div></td>
                            </tr>
                        </table>
                    </div>

                    <div class="section">
                        <div class="section-title">IV. Rekapitulasi Penilaian Potensi Bahaya</div>
                        <table class="rekap-table">
                            <tr>
                                <td style="width: 70%;">Total Skor Akhir :</td>
                                <td style="width: 30%;"><div class="text-input-space"></div></td>
                            </tr>
                        </table>
                    </div>

                    <div class="section">
                        <div class="section-title">V. Kesimpulan</div>
                        <div class="multi-line-input"></div>
                    </div>
                </td>

                <td class="column-right-cell">
                    <!-- KONTEN KANAN -->
                    <table class="lhp-info-table info-table">
                        <tbody>
                            <tr>
                                <th>No. LHP</th>
                                <th>No. Sampel</th>
                                <th>Jenis Sampel</th>
                            </tr>
                            <tr>
                                <td>{{$personal->no_lhp}}</td>
                                <td>{{$personal->no_sampel}}</td>
                                <td>Ergonomi</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <!-- Section informasi lainnya... -->
                    <div class="section">
                        <div class="section-title" style="margin-bottom: 6px;">Informasi Pelanggan</div>
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
                    </div>
                    
                    <div class="section">
                        <div class="section-title" style="margin-bottom: 6px;">Informasi Sampling</div>
                       <table class="info-table">
                           <tr>
                                <td style="width: 25%; text-align:start;">Tanggal</td>
                                <td style="width: 3%;">:</td>
                                <td style="width: 72%; text-align:start;">{{ $personal->tanggal_sampling }}</td>
                            </tr>
                            <tr>
                                <td>Periode Analisis</td>
                                <td style="width: 3%;">:</td>
                                <td style="text-align:start;">{{ $personal->periode_analisis }}</td>
                            </tr>
                            <tr>
                                <td>Metode Analisa</td>
                                <td style="width: 3%;">:</td>
                                <td style="text-align: left;">
                                    Observasi Potensi Bahaya Ergonomi SNI 9011:2021
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="section">
                        <div class="section-title" style="margin-bottom: 6px;">Data Individu/Pekerja yang Diukur</div>
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

                    <div class="section">
                        <table class="uraian-tugas-table">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Uraian Tugas Singkat</th>
                                    <th>Waktu/Durasi Kerja Tiap Tugas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="text-align: center;">1</td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center;">2</td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center;">3</td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center;">4</td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center;">5</td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">Interpretasi Hasil Penilaian Daftar Periksa Potensi Bahaya Ergonomi**</div>
                        <table class="interpretasi-table">
                            <thead>
                                <tr>
                                    <th>Skor</th>
                                    <th>Interpretasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>&lt;2</td>
                                    <td>Kondisi tempat kerja aman</td>
                                </tr>
                                <tr>
                                    <td>3 - 6</td>
                                    <td>Perlu pengamatan lebih lanjut</td>
                                </tr>
                                <tr>
                                    <td>≥7</td>
                                    <td>Berbahaya</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="section">
                        <table style="border: 0;">
                            <tr>
                                <td style="border: 0; font-size: 9px; line-height: 1.3;">
                                    * Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja.
                                    <br>** Interpretasi Hasil Penilaian Daftar Periksa Potensi Bahaya Ergonomi Mengacu kepada Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja Bagian 5.1.
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- SIGNATURE DI DALAM KOLOM KANAN -->
                     
                     @if($ttd != null)
                        @if($ttd->qr_path != null)
                            <table style="margin-top:20px; border: 0;">
                                <tr>
                                    <td style="width:40%; border: 0;"></td>
                                    <td style="width:40%; border: 0;"></td>
                                    <td style="width:20%; border: 0;">
                                        <div class="signature-section">
                                            <table style="border: 0;">
                                                <tr>
                                                    <td style="border: 0; text-align: center; font-size: 9px;">
                                                        <div class="signature-date">
                                                            {{ $ttd->tanggal }}
                                                        </div><br>
                                                        <div class="signature-text">
                                                            <img src="{{ $ttd->qr_path }}" width="25" height="25" alt="ttd">
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        @else
                            <table style="margin-top:20px; border: 0;">
                                <tr>
                                    <td style="width:40%; border: 0;"></td>
                                    <td style="width:40%; border: 0;"></td>
                                    <td style="width:20%; border: 0;">
                                        <div class="signature-section">
                                            <table style="border: 0;">
                                                <tr>
                                                    <td style="border: 0; text-align: center; font-size: 9px;">
                                                        <div class="signature-date">
                                                            {{ $ttd->tanggal }}
                                                        </div><br><br><br>
                                                        <div class="signature-text">
                                                            <strong>{{$ttd->nama_karyawan}}</strong><br>
                                                            <span>{{$ttd->jabatan_karyawan}}</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        @endif
                @endif
                </td>
            </tr>
        </table>
    </div>
</body>
</html>