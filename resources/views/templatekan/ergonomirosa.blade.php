<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 10mm 12mm 10mm;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 0;
            line-height: 1.2;
            width: 277mm; /* A4 landscape width minus margins */
            max-width: 277mm;
        }

        /* Layout Container */
        .main-container {
            width: 100%;
            display: flex;
            gap: 8px;
            min-height: 190mm; /* A4 landscape height minus margins */
        }

        .left-section {
            flex: 0 0 65%;
            width: 65%;
        }

        .right-section {
            flex: 0 0 33%;
            width: 33%;
            padding-left: 8px;
        }

        /* Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: fixed;
        }

        .header-table td {
            border: none;
            padding: 8px;
            vertical-align: middle;
            height: 50px;
        }

        .header-table .left-cell {
            width: 33.33%;
            text-align: left;
        }

        .header-table .center-cell {
            width: 33.33%;
            text-align: center;
        }

        .header-table .right-cell {
            width: 33.33%;
            text-align: right;
        }

        .header-logo {
            height: 45px;
            width: auto;
            display: block;
        }

        .main-header {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            text-decoration: underline;
            margin-bottom: 8px;
            padding: 5px 0;
            clear: both;
        }

        /* Table Styling */
        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 8pt;
        }
        
        .skor-table {
            width: 100%;
            margin-bottom: 4px;
        }
        
        .skor-table td, .skor-table th {
            font-size: 8pt;
            padding: 3px 4px;
            border: 1px solid #000;
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
        
        /* Utility Classes */
        .text-center {
            text-align: center;
        }
        
        .text-left {
            text-align: left;
        }
        
        .text-right {
            text-align: right;
        }
        
        .vertical-top {
            vertical-align: top;
        }
        
        .font-bold {
            font-weight: bold;
        }
        
        .kesimpulan-cell {
            height: 100px;
            vertical-align: top;
            text-align: justify;
            padding: 8px;
        }
        
        .no-border {
            border: 0;
        }

        /* Compact table styling */
        .compact-table {
            margin-bottom: 6px;
            font-size: 7pt;
        }

        .compact-table td, .compact-table th {
            font-size: 7pt;
            padding: 2px 3px;
        }

        .section-header {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: left;
            font-size: 7pt;
        }

        /* Responsive fallback */
        @media print {
            body {
                width: 277mm;
            }
            
            .main-container {
                display: block;
            }
            
            .left-section {
                float: left;
                width: 65%;
            }
            
            .right-section {
                float: right;
                width: 33%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="left-cell">
                <img src="{{public_path('img/isl_logo.png')}}" alt="ISL" class="header-logo">
            </td>
            <td class="center-cell">
                <div class="main-header">LAPORAN HASIL PENGUJIAN</div>
            </td>
            <td class="right-cell">
                <img src="{{public_path('img/logo_kan.png')}}" alt="KAN" class="header-logo">
            </td>
        </tr>
    </table>

    <!-- Main Content Container -->
    <div class="main-container">
        <!-- Left Section -->
        <div class="left-section">
            <!-- Section A -->
            <table class="skor-table">
                <tr>
                    <th rowspan="2" width="8%">No</th>
                    <th rowspan="2" width="30%">Jenis Skoring</th>
                    <th colspan="4" width="62%">Skor Section A</th>
                </tr>
                <tr>
                    <td class="text-center">Section A</td>
                    <td class="text-center">Durasi</td>
                    <td class="text-center">Total (Section A + Durasi)</td>
                    <td class="text-center">Skor Section A</td>
                </tr>
                <tr>
                    <td class="text-center">1.</td>
                    <td>Tinggi Kursi & Lebar Kursi</td>
                    <td class="text-center">3</td>
                    <td rowspan="2" class="text-center">2</td>
                    <td rowspan="2" class="text-center">5</td>
                    <td rowspan="2" class="text-center">5</td>
                </tr>
                <tr>
                    <td class="text-center">2.</td>
                    <td>Sandaran Lengan & Punggung</td>
                    <td class="text-center">4</td>
                </tr>
            </table>

            <!-- Section B -->
            <table class="skor-table">
                <tr>
                    <th rowspan="2" width="8%">No</th>
                    <th rowspan="2" width="30%">Jenis Skoring</th>
                    <th colspan="4" width="62%">Skor Section B</th>
                </tr>
                <tr>
                    <td class="text-center">Section B</td>
                    <td class="text-center">Durasi</td>
                    <td class="text-center">Total (Section B + Durasi)</td>
                    <td class="text-center">Skor Section B</td>
                </tr>
                <tr>
                    <td class="text-center">1.</td>
                    <td>Monitor</td>
                    <td class="text-center">2</td>
                    <td class="text-center">1</td>
                    <td class="text-center">3</td>
                    <td rowspan="2" class="text-center">4</td>
                </tr>
                <tr>
                    <td class="text-center">2.</td>
                    <td>Telepon</td>
                    <td class="text-center">1</td>
                    <td class="text-center">2</td>
                    <td class="text-center">3</td>
                </tr>
            </table>

            <!-- Section C -->
            <table class="skor-table">
                <tr>
                    <th rowspan="2" width="8%">No</th>
                    <th rowspan="2" width="30%">Jenis Skoring</th>
                    <th colspan="4" width="62%">Skor Section C</th>
                </tr>
                <tr>
                    <td class="text-center">Section C</td>
                    <td class="text-center">Durasi</td>
                    <td class="text-center">Total (Section C + Durasi)</td>
                    <td class="text-center">Skor Section C</td>
                </tr>
                <tr>
                    <td class="text-center">1.</td>
                    <td>Mouse</td>
                    <td class="text-center">2</td>
                    <td class="text-center">1</td>
                    <td class="text-center">3</td>
                    <td rowspan="2" class="text-center">3</td>
                </tr>
                <tr>
                    <td class="text-center">2.</td>
                    <td>Keyboard</td>
                    <td class="text-center">1</td>
                    <td class="text-center">1</td>
                    <td class="text-center">2</td>
                </tr>
            </table>

            <!-- Final Score -->
            <table class="skor-table">
                <tr>
                    <td width="40%" class="text-center font-bold">Skor Section A (Section B & Section C)</td>
                    <td width="8%" class="no-border text-center" style="font-size: 14pt;">→</td>
                    <td width="27%" class="text-center font-bold">Skor ROSA</td>
                    <td width="8%" class="no-border text-center" style="font-size: 14pt;">→</td>
                    <td width="17%" class="text-center font-bold" style="font-size: 12pt;">4</td>
                </tr>
                <tr>
                    <td class="text-center">5</td>
                    <td class="no-border"></td>
                    <td class="text-center font-bold">Skoring Section A & Section D</td>
                    <td class="no-border"></td>
                    <td class="no-border"></td>
                </tr>
            </table>

            <!-- Risk Categories -->
            <table class="skor-table">
                <tr>
                    <th width="20%" class="text-center">Skor Akhir</th>
                    <th width="25%" class="text-center">Kategori Risiko</th>
                    <th width="55%" class="text-center">Tindakan</th>
                </tr>
                <tr>
                    <td class="text-center">1 - 2</td>
                    <td class="text-center">Rendah</td>
                    <td class="text-left">Mungkin perlu dilakukan tindakan</td>
                </tr>
                <tr>
                    <td class="text-center">3 - 5</td>
                    <td class="text-center">Sedang</td>
                    <td class="text-left">Diperlukan tindakan karena rawan terkena cedera</td>
                </tr>
                <tr>
                    <td class="text-center">>5</td>
                    <td class="text-center">Tinggi</td>
                    <td class="text-left">Diperlukan tindakan secara ergonomis sesegera mungkin</td>
                </tr>
            </table>

            <!-- Kesimpulan -->
            <table class="skor-table">
                <tr>
                    <td class="kesimpulan-cell">
                        <strong>Kesimpulan:</strong><br><br>
                        Berdasarkan penilaian ROSA, skor akhir adalah 4 yang termasuk dalam kategori risiko sedang. Diperlukan tindakan perbaikan ergonomis untuk mengurangi risiko cedera muskuloskeletal pada pekerja.
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Right Section -->
        <div class="right-section">
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
                            <td>{{ $personal->no_lhp }}</td>
                            <td>{{ $personal->no_sampel }}</td>
                            <td>{{ $personal->jenis_sampel }}</td>
                        </tr>
                    </tbody>
                </table>

                <div style="padding: 4px;">
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
                            <td>Periode Analisis</td>
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
                    <div style="margin-bottom: 2px; font-size: 9pt;">
                        <span class="info-label">Jenis Analisa</span>
                        <span>: Pengumpulan Data (Pengukuran & Skoring)</span>
                    </div>

                    <div style="margin-bottom: 2px; font-size: 9pt;">
                        <span class="info-label">Metode Analisa*</span>
                        <span>: Pengamatan Langsung - ROSA (Rapid Office Restrain Assessment)</span>
                    </div>

                    <div class="info-note">
                        * Metode Analisa Mengacu kepada Jenis Metode yang Direkomendasikan pada
                        Pedoman Teknis Pemeriksaan K3 Pengelolaan Tambahan Peraturan Menteri
                        Ketenagakerjaan RI No.5 Tahun 2018.<br>
                        ** Tabel Acuan Skor Risiko mengacu kepada Handbook Human Factors and Ergonomic
                    </div>
                </div>

            <!-- Notes -->
            <div class="notes-section">
                <strong>*</strong> Metode Analisis Mengacu kepada Development and Evaluation of an Office Ergonomic Risk Checklist: The Rapid Office Strain Assessment (ROSA) by Michael Sonne, Dino L. Villalta, and M. Andrews, 2012.
            </div>

            <!-- Signature Section -->
            <div class="signature-section">
                <div class="signature-container">
                    <div class="signature-date">Jakarta, 04 September 2025</div>
                    <div class="signature-line"></div>
                    <div class="signature-text">(Tanda Tangan Digital)</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>