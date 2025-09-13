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
            padding: 4px;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }

        .section-bagian-bawah {
            border: 1px solid #000;
            padding: 4px;
            margin-bottom: 8px;
            page-break-before: always;
            page-break-inside: avoid;
        }

        .section-title {
            font-weight: bold;
            padding: 2px 4px;
            margin: -4px -4px 4px -4px;
            border-bottom: 1px solid #000;
        }

        .table-potensi-bahaya {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            table-layout: fixed;
            page-break-inside: auto;
        }

         /* Fixed width untuk kolom agar konsisten */
        .table-potensi-bahaya th:nth-child(1),
        .table-potensi-bahaya td:nth-child(1) {
            width: 25%; /* Kolom Kategori */
        }

        .table-potensi-bahaya th:nth-child(2),
        .table-potensi-bahaya td:nth-child(2) {
            width: 65%; /* Kolom Potensi Bahaya */
        }

        .table-potensi-bahaya th:nth-child(3),
        .table-potensi-bahaya td:nth-child(3) {
            width: 10%; /* Kolom Skor */
            text-align: center;
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
            font-weight: bold;
            text-align: center;
        }

        .text-input-space {
            text-align: left !important;
            margin: 0 !important;
            padding: 0 !important;
            min-height: 1.5em;
        }

        .multi-line-input {
            width: 100%;
            border: 1px solid #000;
            padding: 4px;
            min-height: 40px;
        }

        .interpretasi-table td { 
            text-align: center; 
        }
        
        .interpretasi-table td:last-child { 
            text-align: left; 
        }

        .uraian-tugas-table td { 
            height: 1.8em; 
        }

        /* Signature di dalam layout table */
        .signature-in-column {
            text-align: right;
            margin-top: 15px;
            font-size: 8px;
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
            font-size: 7px;
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
            font-size: 8px;
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
            margin-bottom: 6px;
        }

        .info-table td {
            border: 0;
            padding: 0px 2px;
            font-size: 8pt;
            vertical-align: top;
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
    </style>
</head>
<body>
    <div class="page-container">
        <!-- DIV main-header-title LAPORAN HASIL PENGUJIAN -->
        <table class="header-table">
            <tr>
                <td class="left-cell">
                    <img src="{{public_path('img/isl_logo.png')}}" alt="ISL" class="header-logo">
                </td>
                <td class="center-cell">
                    <span class="header-title">LAPORAN HASIL PENGUJIAN</span>
                </td>
                <td class="right-cell">
                    <img src="{{public_path('img/logo_kan.png')}}" alt="KAN" class="header-logo">
                </td>
            </tr>
        </table>
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
                                                    <td rowspan="{{ count($rows) }}">{{ $kategori }}</td>
                                                @endif
                                                <td>{{ $row['potensi'] }}</td>
                                                <td>{{ $row['skor'] }}</td>
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
                                                    <td rowspan="{{ count($rows) }}">{{ $kategori }}</td>
                                                @endif
                                                <td>{{ $row['potensi'] }}</td>
                                                <td>{{ $row['skor'] }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
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
                        <div class="section-title" style="margin-bottom: 3px;">Informasi Pelanggan</div>
                        <table class="info-table">
                            <tr>
                                <td style="width: 90px;">Nama Pelanggan</td>
                                <td style="width: 3%;">:</td>
                                <td>
                                    <div class="text-input-space">{{ $personal->nama_pelanggan }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td>Alamat / Lokasi</td>
                                <td style="width: 3%;">:</td>
                                <td>
                                    <div class="text-input-space">{{ $personal->alamat_pelanggan }}</div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="section">
                        <div class="section-title" style="margin-bottom: 3px;">Informasi Sampling</div>
                       <table class="info-table">
                            <tr>
                                <td style="width: 90px;">Tanggal</td>
                                <td style="width: 3%;">:</td>
                                <td style="text-align: left;">
                                    {{ $personal->tanggal_sampling }}
                                </td>
                            </tr>
                            <tr>
                                <td>Periode</td>
                                <td style="width: 3%;">:</td>
                                <td style="text-align: left;">
                                    {{ $personal->periode_analis }}
                                </td>
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
                        <div class="section-title" style="margin-bottom: 3px;">Data Individu/Pekerja yang Diukur</div>
                        <table class="info-table">
                            <tr>
                                <td style="width: 90px;">Nama Pekerja</td>
                                <td style="width: 3%;">:</td>
                                <td><div class="text-input-space">{{ $personal->nama_pekerja }}</div></td>
                            </tr>
                            <tr>
                                <td>Posisi/Jabatan</td>
                                <td style="width: 3%;">:</td>
                                <td><div class="text-input-space">{{ $personal->jabatan }}</div></td>
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
                                    <td class="centered-text">1</td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="centered-text">2</td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="centered-text">3</td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="centered-text">4</td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                    <td>
                                        <div class="text-input-space"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="centered-text">5</td>
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
                    <div  class="section">
                        <table>
                            <tr>
                                <td>
                                    * Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja.
                                    <br>** Interpretasi Hasil Penilaian Daftar Periksa Potensi Bahaya Ergonomi Mengacu kepada Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja Bagian 5.1.
                                </td>
                            </tr>
                        </table>
                    </div>
                    <!-- SIGNATURE DI DALAM KOLOM KANAN -->
                     <table style="margin-top:30px">
                        <tr>
                            <td style="width:40%"></td>
                            <td style="width:40%"></td>
                            <td style="width:20%">
                                <div class="signature-in-column">
                                    <div class="signature-date">Jakartax,</div>
                                    <div class="signature-name">Nama Penanggung Jawab</div>
                                    <div class="signature-title">Jabatan</div>
                                </div>
                            </td>
                        </tr>
                     </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>