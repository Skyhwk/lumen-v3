<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian REBA</title>
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 8pt;
            background-color: white;
            line-height: 1.1;
            padding: 10px;
        }

        .container {
            width: 100%;
            clear: both;
        }

        /* Header */
        .main-header {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            text-decoration: underline;
            margin-bottom: 8px;
            padding: 5px 0;
            clear: both;
        }

        /* Main content wrapper */
        .main-content {
            clear: both;
            overflow: hidden;
            margin-bottom: 8px;
        }

        /* Column layouts */
        .column-left {
            float: left;
            width: 30%;
            padding-right: 3px;
        }

        .column-center {
            float: left;
            width: 30%;
            padding: 0 1.5px;
        }

        .column-right {
            float: right;
            width: 38%;
            padding-left: 3px;
        }

        /* Bottom section */
        .bottom-section {
            clear: both;
            overflow: hidden;
            margin-top: 8px;
        }

        .bottom-left {
            float: left;
            width: 60%;
            padding-right: 3px;
        }

        .bottom-right {
            float: right;
            width: 40%;
            padding-left: 3px;
        }

        /* Table styles */
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 8px;
            font-size: 8pt;
        }

        th, td {
            border: 1px solid #000;
            padding: 1px 2px;
            vertical-align: top;
            font-size: 8pt;
            line-height: 1.1;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
            padding: 2px;
        }

        .section-header {
            font-weight: bold;
            font-size: 8pt;
            text-decoration: underline;
            text-align: left;
            margin: 3px 0 2px 0;
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

        .final-score {
            background-color: #e0e0e0;
            font-weight: bold;
        }

        /* Image optimization */
        td img {
            max-width: 100%;
            max-height: 30px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        /* Specific table adjustments */
        .score-table th:first-child { width: 8%; }
        .score-table th:nth-child(2) { width: 67%; }
        .score-table th:last-child { width: 25%; }

        .header-info-table th { width: 33.33%; }
        
        .reference-table th:nth-child(1) { width: 15%; }
        .reference-table th:nth-child(2) { width: 12%; }
        .reference-table th:nth-child(3) { width: 23%; }
        .reference-table th:nth-child(4) { width: 50%; }

        /* Compact spacing */
        .image-row td:first-child {
            text-align: center;
            vertical-align: middle;
        }

        .image-row td:nth-child(2) {
            height: 30px;
            padding: 1px;
        }

        .image-row td:last-child {
            text-align: center;
            vertical-align: middle;
        }

        .label-row td {
            text-align: center;
            font-size: 7pt;
            padding: 1px;
        }

        /* Footer notes */
        .footer-notes td:first-child {
            width: 2%;
            text-align: right;
            vertical-align: top;
            font-size: 7pt;
        }

        .footer-notes td:last-child {
            width: 98%;
            text-align: left;
            font-size: 7pt;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="main-header">
            LAPORAN HASIL PENGUJIAN
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Column 1: Skor A (30%) -->
            <div class="column-left">
                <table class="score-table">
                    <tr>
                        <th>No.</th>
                        <th>Jenis Skor A</th>
                        <th>Nilai</th>
                    </tr>
                    
                    <!-- Leher -->
                    <tr class="image-row">
                        <td rowspan="2">1</td>
                        <td>
                            <img src="{{ public_path('dokumen/img_ergo/reba/reba_leher.jpg') }}" alt="Posisi Leher"
                            style="object-fit: contain;">
                        </td>
                        <td rowspan="2">{{ $pengukuran->skor_leher }}</td>
                    </tr>
                    <tr class="label-row">
                        <td><u>Leher</u></td>
                    </tr>
                    
                    <!-- Badan -->
                    <tr class="image-row">
                        <td rowspan="2">2</td>
                        <td>
                            <img src="{{ public_path('dokumen/img_ergo/reba/reba_badan.jpg') }}" alt="Posisi Badan"
                            style="object-fit: contain;">
                        </td>
                        <td rowspan="2">{{ $pengukuran->skor_badan }}</td>
                    </tr>
                    <tr class="label-row">
                        <td><u>Badan</u></td>
                    </tr>
                    
                    <!-- Kaki -->
                    <tr class="image-row">
                        <td rowspan="2">3</td>
                        <td>
                            <img src="{{ public_path('dokumen/img_ergo/reba/reba_kaki.jpg') }}" alt="Posisi Kaki"
                            style="object-fit: contain;">
                        </td>
                        <td rowspan="2">{{ $pengukuran->skor_kaki }}</td>
                    </tr>
                    <tr class="label-row">
                        <td><u>Kaki</u></td>
                    </tr>
                    
                    <!-- Skor Beban -->
                    <tr class="image-row">
                        <td rowspan="2">4</td>
                        <td>
                            <img src="{{ public_path('dokumen/img_ergo/reba/reba_skor_beban.jpg') }}" alt="Skor Beban"
                            style="object-fit: contain;" width="17%" height="45px">
                        </td>
                        <td rowspan="2">{{ $pengukuran->skor_beban }}</td>
                    </tr>
                    <tr class="label-row">
                        <td><u>Skor Beban</u></td>
                    </tr>
                </table>
                
                <!-- Korelasi Nilai -->
                <div class="section-header">Korelasi Nilai dengan Tabel Acuan</div>
                <table>
                    <tr>
                        <th style="width: 8%;">No.</th>
                        <th style="width: 67%;">Jenis Nilai</th>
                        <th style="width: 25%;">Hasil</th>
                    </tr>
                    <tr>
                        <td>1</td>
                        <td style="text-align: left; padding-left: 4px;">Tabel A</td>
                        <td>{{ $pengukuran->nilai_tabel_a}}</td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td style="text-align: left; padding-left: 4px;">Skor A</td>
                        <td>{{ $pengukuran->total_skor_a }}</td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td style="text-align: left; padding-left: 4px;">Tabel B</td>
                        <td>{{ $pengukuran->nilai_tabel_b }}</td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td style="text-align: left; padding-left: 4px;">Skor B</td>
                        <td>{{ $pengukuran->total_skor_b }}</td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td style="text-align: left; padding-left: 4px;">Tabel C</td>
                        <td>{{ $pengukuran->nilai_tabel_c }}</td>
                    </tr>
                    <tr class="final-score">
                        <td colspan="2" style="font-weight: bold;">Final Skor REBA</td>
                        <td style="font-weight: bold;">{{ $pengukuran->final_skor_reba }}</td>
                    </tr>
                </table>
            </div>
            
            <!-- Column 2: Skor B (30%) -->
            <div class="column-center">
                <table class="score-table">
                    <tr>
                        <th>No.</th>
                        <th>Jenis Skor B</th>
                        <th>Nilai</th>
                    </tr>
                    
                    <!-- Lengan Atas -->
                    <tr class="image-row">
                        <td rowspan="2">5</td>
                        <td>
                            <img src="{{ public_path('dokumen/img_ergo/reba/reba_lengan_atas.jpg') }}"
                            alt="Posisi Lengan Atas" style="object-fit: contain;">
                        </td>
                        <td rowspan="2">{{ $pengukuran->skor_lengan_atas }}</td>
                    </tr>
                    <tr class="label-row">
                        <td><u>Lengan Atas</u></td>
                    </tr>
                    
                    <!-- Lengan Bawah -->
                    <tr class="image-row">
                        <td rowspan="2">6</td>
                        <td>
                            <img src="{{ public_path('dokumen/img_ergo/reba/reba_lengan_bawah.jpg') }}"
                            alt="Posisi Lengan Bawah" style="object-fit: contain;" width="15%" height="45px">
                        </td>
                        <td rowspan="2">{{ $pengukuran->skor_lengan_bawah }}</td>
                    </tr>
                    <tr class="label-row">
                        <td><u>Lengan Bawah</u></td>
                    </tr>
                    
                    <!-- Pergelangan Tangan -->
                    <tr class="image-row">
                        <td rowspan="2">7</td>
                        <td>
                            <img src="{{ public_path('dokumen/img_ergo/reba/reba_pergelangan_tangan.jpg') }}"
                            alt="Posisi Pergelangan Tangan" style="object-fit: contain;" width="15%"
                            height="45px">
                        </td>
                        <td rowspan="2">{{ $pengukuran->skor_pergelangan_tangan }}</td>
                    </tr>
                    <tr class="label-row">
                        <td><u>Pergelangan Tangan</u></td>
                    </tr>
                    
                    <!-- Kondisi Pegangan -->
                    <tr class="image-row">
                        <td rowspan="2">8</td>
                        <td>
                            <img src="{{ public_path('dokumen/img_ergo/reba/reba_kondisi_pegangan.jpg') }}"
                            alt="Kondisi Pegangan" style="object-fit: contain;" width="15%" height="45px">
                        </td>
                        <td rowspan="2">{{ $pengukuran->skor_pegangan }}</td>
                    </tr>
                    <tr class="label-row">
                        <td><u>Kondisi Pegangan</u></td>
                    </tr>
                    
                    <!-- Aktivitas Otot -->
                    <tr class="image-row">
                        <td rowspan="2">9</td>
                        <td>
                            <img src="{{ public_path('dokumen/img_ergo/reba/reba_aktivitas_otot.jpg') }}"
                            alt="Aktivitas Otot" style="object-fit: contain;" width="15%" height="45px">
                        </td>
                        <td rowspan="2">{{ $pengukuran->skor_aktivitas_otot }}</td>
                    </tr>
                    <tr class="label-row">
                        <td><u>Aktivitas Otot</u></td>
                    </tr>
                </table>
                
                <!-- Tingkat Risiko -->
                <table style="margin-top: 8px;">
                    <tr class="final-score">
                        <td colspan="2" style="font-weight: bold;">Tingkat Risiko</td>
                        <td style="font-weight: bold;">{{ $pengukuran->tingkat_resiko }}</td>
                    </tr>
                    <tr class="final-score">
                        <td colspan="2" style="font-weight: bold;">Kategori Risiko</td>
                        <td style="font-weight: bold;">{{ $pengukuran->kategori_resiko }}</td>
                    </tr>
                    <tr class="final-score">
                        <td colspan="2" style="font-weight: bold;">Tindakan</td>
                        <td style="font-weight: bold; font-size: 7pt;">{{ $pengukuran->tindakan }}</td>
                    </tr>
                </table>
            </div>
            
            <!-- Column 3: Info Header & Customer (40%) -->
            <div class="column-right">
                <!-- Header Info -->
                <table class="header-info-table">
                    <tr>
                        <th>No. LHP</th>
                        <th>No. Sampel</th>
                        <th>Jenis Sampel</th>
                    </tr>
                    <tr>
                        <td>{{ $personal->no_lhp }}</td>
                        <td>{{ $personal->no_sampel }}</td>
                        <td>{{ $personal->jenis_sampel }}</td>
                    </tr>
                </table>
                
                <!-- Informasi Pelanggan -->
                <div class="section-header">Informasi Pelanggan</div>
                <table class="info-table">
                    <tr>
                        <td style="width: 25%;">Nama Pelanggan</td>
                        <td style="width: 3%;">:</td>
                        <td style="width: 72%;">{{ strtoupper($personal->nama_pelanggan) }}</td>
                    </tr>
                    <tr>
                        <td>Alamat / Lokasi Sampling</td>
                        <td>:</td>
                        <td>{{ $personal->alamat_pelanggan }}</td>
                    </tr>
                </table>
                
                <!-- Informasi Sampling -->
                <table class="info-table">
                    <tr>
                        <td style="width: 25%;">Tanggal Sampling</td>
                        <td style="width: 3%;">:</td>
                        <td style="width: 72%;">{{ $personal->tanggal_sampling }}</td>
                    </tr>
                    <tr>
                        <td>Periode Analisis</td>
                        <td>:</td>
                        <td>{{ $personal->periode_analisis }}</td>
                    </tr>
                    <tr>
                        <td>Jenis Analisis</td>
                        <td>:</td>
                        <td>Rapid Assessment (Form Penilaian Cepat)</td>
                    </tr>
                    <tr>
                        <td>Metode Analisis*</td>
                        <td>:</td>
                        <td>Pengamatan Langsung - Rapid Entire Body Assessment</td>
                    </tr>
                </table>
                
                <!-- Data Individu -->
                <div class="section-header">Data Individu/Pekerja yang Diukur</div>
                <table class="info-table">
                    <tr>
                        <td style="width: 25%;">Nama</td>
                        <td style="width: 3%;">:</td>
                        <td style="width: 72%;">{{ $personal->nama_pekerja }}</td>
                    </tr>
                    <tr>
                        <td>Usia</td>
                        <td>:</td>
                        <td>{{ $personal->usia }} Tahun</td>
                    </tr>
                    <tr>
                        <td>Lama Bekerja</td>
                        <td>:</td>
                        <td>{{ $personal->lama_kerja }} Tahun</td>
                    </tr>
                </table>
                
                <!-- Tabel Acuan -->
                <div class="section-header">Tabel Acuan Skor Risiko dan Tindakan Perbaikan**</div>
                <table class="reference-table">
                    <tr>
                        <th>SKOR REBA</th>
                        <th>TINGKAT RISIKO</th>
                        <th>KATEGORI RISIKO</th>
                        <th>TINDAKAN</th>
                    </tr>
                    <tr>
                        <td style="font-size: 7pt;">1</td>
                        <td style="font-size: 7pt;">0</td>
                        <td style="font-size: 7pt;">Sangat Rendah</td>
                        <td style="font-size: 7pt;">Tidak ada tindakan yang diperlukan</td>
                    </tr>
                    <tr>
                        <td style="font-size: 7pt;">2-3</td>
                        <td style="font-size: 7pt;">1</td>
                        <td style="font-size: 7pt;">Rendah</td>
                        <td style="font-size: 7pt;">Mungkin diperlukan tindakan</td>
                    </tr>
                    <tr>
                        <td style="font-size: 7pt;">4-7</td>
                        <td style="font-size: 7pt;">2</td>
                        <td style="font-size: 7pt;">Sedang</td>
                        <td style="font-size: 7pt;">Diperlukan tindakan</td>
                    </tr>
                    <tr>
                        <td style="font-size: 7pt;">8-10</td>
                        <td style="font-size: 7pt;">3</td>
                        <td style="font-size: 7pt;">Tinggi</td>
                        <td style="font-size: 7pt;">Diperlukan tindakan segera</td>
                    </tr>
                    <tr>
                        <td style="font-size: 7pt;">11-15</td>
                        <td style="font-size: 7pt;">4</td>
                        <td style="font-size: 7pt;">Sangat Tinggi</td>
                        <td style="font-size: 7pt;">Diperlukan tindakan sesegera mungkin</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Bottom Section -->
        <div class="bottom-section">
            <div class="bottom-left">
                <table>
                    <tr>
                        <td style="width: 35%; text-align: center; font-weight: bold; vertical-align: middle; height: 40px;">
                            KESIMPULAN AKHIR KONDISI ERGONOMI BERDASARKAN HASIL PENILAIAN CEPAT SELURUH TUBUH (REBA)
                        </td>
                        <td style="width: 65%; text-align: justify; vertical-align: top; font-size: 8pt;">
                            Berdasarkan hasil pengujian REBA, pekerja menunjukkan tingkat risiko TINGGI dengan skor 9. Kondisi ergonomi pekerja memerlukan tindakan perbaikan segera untuk mencegah terjadinya gangguan muskuloskeletal. Postur kerja yang tidak ergonomis dapat menyebabkan cedera dan penurunan produktivitas.
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center; font-weight: bold; vertical-align: middle; height: 35px;">
                            DESKRIPSI SINGKAT PEKERJAAN PEKERJA
                        </td>
                        <td style="text-align: justify; vertical-align: top; font-size: 8pt;">
                            Pekerja melakukan aktivitas mengangkat dan memindahkan material dengan posisi membungkuk, leher menunduk, dan lengan terangkat. Aktivitas dilakukan berulang selama 6-8 jam per hari dengan beban rata-rata 10-15 kg.
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="bottom-right">
                <!-- Footer Notes -->
                <table class="footer-notes" style="margin-top: 20px;">
                    <tr>
                        <td>*</td>
                        <td>
                            Metode Analisis Mengacu kepada Jenis Metode yang Direkomendasikan Pada Pedoman Teknis Penerapan K3 Penjelasan Tambahan Menteri Ketenagakerjaan Nomor 5 Tahun 2018.
                        </td>
                    </tr>
                    <tr>
                        <td>**</td>
                        <td>
                            Tabel Acuan Skor Risiko mengacu kepada Handbook Human Factors and by Neville Stanton et al, 2005.
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>