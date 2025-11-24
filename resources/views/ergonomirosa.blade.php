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
    <!-- Main Content Container -->
    <div class="main-container">
        <!-- Left Section -->
        <div class="left-section">
            <!-- Section A -->
            <table class="skor-table" style="table-layout: fixed; width: 100%; border-collapse: collapse;">
                <tr>
                    <th rowspan="2" style="width: 8%;">No</th>
                    <th rowspan="2" style="width: 30%;">Jenis Skoring</th>
                    <th colspan="4" style="width: 62%;">Skor Section A</th>
                </tr>
                <tr>
                    <td class="text-center" colspan=2 style="width: 24%;">Section A</td>
                    <td class="text-center" style="width: 28%;">Durasi</td>
                    <td class="text-center" style="width: 10%;">Total (Section A + Durasi)</td>
                </tr>
                <tr>
                    <td class="text-center" style="width: 8%;">1.</td>
                    <td style="width: 30%;">Tinggi Kursi & Lebar Kursi</td>
                    <td class="text-center" style="width: 10%;">{{$pengukuran->skor_total_tinggi_kursi_dan_lebar_dudukan}}</td>
                    <td class="text-center" rowspan=2 style="width: 14%;">{{$pengukuran->total_section_a}}</td>
                    <td class="text-center" rowspan=2 style="width: 28%;">{{$pengukuran->skor_durasi_kerja_bagian_kursi}}</td>
                    <td class="text-center" rowspan=2 style="width: 10%;">{{($pengukuran->total_section_a + $pengukuran->skor_durasi_kerja_bagian_kursi )}}</td>
                </tr>
                <tr>
                    <td class="text-center" style="width: 8%;">2.</td>
                    <td style="width: 30%;">Sandaran Lengan & Punggung</td>
                    <td class="text-center" style="width: 10%;">{{$pengukuran->skor_total_sandaran_lengan_dan_punggung}}</td>
                </tr>
            </table>

            <!-- Section B -->
            <table class="skor-table" style="table-layout: fixed; width: 100%; border-collapse: collapse;">
                <tr>
                    <th rowspan="2" style="width: 8%;">No</th>
                    <th rowspan="2" style="width: 30%;">Jenis Skoring</th>
                    <th colspan="4" style="width: 62%;">Skor Section B</th>
                </tr>
                <tr>
                    <td class="text-center" style="width: 10%;">Section B</td>
                    <td class="text-center" style="width: 14%;">Durasi</td>
                    <td class="text-center" style="width: 28%;">Total (Section B + Durasi)</td>
                    <td class="text-center" style="width: 10%;">Skor Section B</td>
                </tr>
                <tr>
                    <td class="text-center" style="width: 8%;">1.</td>
                    <td style="width: 30%;">Monitor</td>
                    <td class="text-center" style="width: 10%;">{{$pengukuran->skor_monitor}}</td>
                    <td class="text-center" style="width: 14%;">{{$pengukuran->skor_durasi_kerja_monitor}}</td>
                    <td class="text-center" style="width: 28%;">{{($pengukuran->skor_monitor + $pengukuran->skor_durasi_kerja_monitor)}}</td>
                    <td rowspan="2" class="text-center" style="width: 10%;">{{$pengukuran->total_section_b}}</td>
                </tr>
                <tr>
                    <td class="text-center" style="width: 8%;">2.</td>
                    <td style="width: 30%;">Telepon</td>
                    <td class="text-center" style="width: 10%;">{{$pengukuran->skor_telepon}}</td>
                    <td class="text-center" style="width: 14%;">{{$pengukuran->skor_durasi_kerja_telepon}}</td>
                    <td class="text-center" style="width: 28%;">{{($pengukuran->skor_telepon + $pengukuran->skor_durasi_kerja_telepon )}}</td>
                </tr>
            </table>

            <!-- Section C -->
            <table class="skor-table">
                <tr>
                    <th rowspan="2" style="width: 8%;">No</th>
                    <th rowspan="2" style="width: 30%;">Jenis Skoring</th>
                    <th colspan="4" style="width: 62%;">Skor Section C</th>
                </tr>
                <tr>
                    <td class="text-center" style="width: 10%;" >Section C</td>
                    <td class="text-center" style="width: 14%;" >Durasi</td>
                    <td class="text-center" style="width: 28%;" >Total (Section C + Durasi)</td>
                    <td class="text-center" style="width: 10%;" >Skor Section C</td>
                </tr>
                <tr>
                    <td class="text-center" style="width: 8%;">1.</td>
                    <td style="width: 30%;">Mouse</td>
                    <td class="text-center" style="width: 10%;">{{$pengukuran->skor_mouse}}</td>
                    <td class="text-center" style="width: 14%;">{{$pengukuran->skor_durasi_kerja_mouse}}</td>
                    <td class="text-center" style="width: 28%;">{{($pengukuran->skor_mouse + $pengukuran->skor_durasi_kerja_mouse)}}</td>
                    <td rowspan="2" class="text-center" style="width: 10%;">{{$pengukuran->total_section_c}}</td>
                </tr>
                <tr>
                    <td class="text-center">2.</td>
                    <td>Keyboard</td>
                    <td class="text-center">{{$pengukuran->skor_keyboard}}</td>
                    <td class="text-center">{{$pengukuran->skor_durasi_kerja_keyboard}}</td>
                    <td class="text-center">{{($pengukuran->skor_keyboard + $pengukuran->skor_durasi_kerja_keyboard )}}</td>
                </tr>
            </table>

            <!-- Final Score -->
            <table class="skor-table">
                <tr>
                    <td width="40%" class="text-center font-bold">Skor Section D (Section B & Section C)</td>
                    <td width="8%" rowspan="2" style="font-size: 14pt; border:none;"></td>
                    <td width="27%" class="text-center font-bold">Skor ROSA</td>
                    <td width="8%" rowspan="2"  style="font-size: 14pt; border:none;">→</td>
                    <td width="17%" rowspan="2" class="text-center font-bold" style="font-size: 12pt;">{{$pengukuran->final_skor_rosa}}</td>
                </tr>
                <tr>
                    <td class="text-center">{{$pengukuran->total_section_d}}</td>
                    <td class="text-center font-bold">Skoring Section A & Section D</td>
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
                    <td class="text-center">3 - 4</td>
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
                        {{$pengukuran->result}}
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
                            <th>JNS SAMPEL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $personal->no_lhp }}</td>
                            <td>{{ $personal->no_sampel }}</td>
                            <td>ERGONOMI</td>
                        </tr>
                    </tbody>
                </table>

                <div style="padding: 4px;">
                    <div class="info-header">Informasi Pelanggan</div>
                    <table class="info-table">
                        <tr>
                            <td style="width: 25%; text-align:start;">Nama Pelanggan</td>
                            <td style="width: 3%;">:</td>
                            <td style="width: 72%;text-align:start;">{{ strtoupper($personal->nama_pelanggan) }}</td>
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
                            <td style="text-align:start;">{{$personal->divisi}}</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Lama Bekerja</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">{{ $personal->lama_kerja }}</td>
                        </tr>
                    </table>
                    <div style="margin-bottom: 2px; font-size: 9pt;">
                        <table class="info-table">
                            <tr>
                                <td style="width: 25%; text-align:start;">Jenis Analisa</td>
                                <td style="width: 3%;">:</td>
                                <td style="width: 72%;text-align:start;">Pengumpulan Data (Pengukuran & Skoring)</td>
                            </tr>
                        </table>
                    </div>

                    <div style="margin-bottom: 2px; font-size: 9pt;">
                        <table class="info-table">
                            <tr>
                                <td style="width: 25%; text-align:start;">Metode Analisa*</td>
                                <td style="width: 3%;">:</td>
                                <td style="width: 72%;text-align:start;">Pengamatan Langsung - ROSA (Rapid Office Restrain Assessment)</td>
                            </tr>
                        </table>
                    </div>
                </div>

            <!-- Notes -->
            <div class="notes-section">
                <strong>*</strong> Metode Analisis Mengacu kepada Development and Evaluation of an Office Ergonomic Risk Checklist: The Rapid Office Strain Assessment (ROSA) by Michael Sonne, Dino L. Villalta, and M. Andrews, 2012.
            </div>

            <!-- Signature Section -->
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