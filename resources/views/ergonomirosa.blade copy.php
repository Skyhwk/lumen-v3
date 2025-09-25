<!DOCTYPE html>
<html>
<head>
    <style>
        @page {
            margin-top: 10mm;
            margin-bottom: 10mm;
            margin-left: 10mm;
            margin-right: 10mm;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt; /* sebelumnya 7pt */
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }

        
        table {
            border-collapse: collapse;
            font-size: 8pt; /* naik dari 7pt */
        }
        
        .main-table {
            width: 100%;
            page-break-inside: avoid;
            border: none !important;
        }
        
        .header-company {
             font-size: 8pt;
            text-align: left;
            font-family: Arial, sans-serif;
        }
        
        .header-title {
            font-size: 12px;
            text-align: center;
            font-weight: bold;
            font-family: Arial, sans-serif;
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
        
        .skor-table {
            width: 100%;
            margin-bottom: 3px;
            font-size: 6pt;
        }
        
        .skor-table td, .skor-table th {
           font-size: 8pt;
           padding: 2px 3px;
        }
        
        .info-table {
            width: 100%;
            margin-bottom: 5px;
            font-size: 6pt;
        }
        
        .info-table td, .info-table th {
            font-size: 8pt;
           padding: 2px 3px;
        }
        
        .bordered {
            border: 1px solid #000;
        }
        
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
            height: 120px;
            vertical-align: top;
            text-align: justify;
            padding: 3px;
        }
        
        .no-border {
            border: 0;
        }
        
        .spacer {
            height: 2px;
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
    <!-- Header -->
    <div class="main-header">
        LAPORAN HASIL PENGUJIAN
    </div>
    
    <div class="spacer"></div>
    
    <!-- Main Content -->
    <table class="main-table">
        <tr>
            <td width="65%" class="vertical-top">
                <!-- Section A -->
                <table class="skor-table ">
                    <tr>
                        <th rowspan="2" width="5%">No</th>
                        <th rowspan="2" width="25%">Jenis Skoring</th>
                        <th colspan="4" width="70%">Skor Section A</th>
                    </tr>
                    <tr>
                        <td colspan="2" class="text-center">Section A</td>
                        <td class="text-center">Durasi</td>
                        <td class="text-center">Total (Section A + Durasi)</td>
                    </tr>
                    <tr>
                        <td class="text-center">1.</td>
                        <td>Tinggi Kursi & Lebar Kursi</td>
                        <td class="text-center">{{ $pengukuran->skor_total_tinggi_kursi_dan_lebar_dudukan }}</td>
                        <td rowspan="2" class="text-center">{{ 0 }}</td>
                        <td rowspan="2" class="text-center">&nbsp;</td>
                        <td rowspan="2" class="text-center">{{ 0 }}</td>
                    </tr>
                    <tr>
                        <td class="text-center">2.</td>
                        <td>Sandaran Lengan & Punggung</td>
                        <td class="text-center">{{ $pengukuran->skor_total_sandaran_lengan_dan_punggung }}</td>
                    </tr>
                </table>

                <!-- Section B -->
                <table class="skor-table ">
                    <tr>
                        <th rowspan="2" width="5%">No</th>
                        <th rowspan="2" width="25%">Jenis Skoring</th>
                        <th colspan="4" width="70%">Skor Section B</th>
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
                        <td class="text-center">{{ $pengukuran->skor_monitor }}</td>
                        <td class="text-center">{{ $pengukuran->skor_durasi_kerja_monitor }}</td>
                        <td class="text-center">{{ $pengukuran->total_skor_monitor }}</td>
                        <td rowspan="2" class="text-center">{{ 0 }}</td>
                    </tr>
                    <tr>
                        <td class="text-center">2.</td>
                        <td>Telepon</td>
                        <td class="text-center">{{ $pengukuran->skor_telepon }}</td>
                        <td class="text-center">{{ $pengukuran->skor_durasi_kerja_telepon }}</td>
                        <td class="text-center">{{ $pengukuran->total_skor_telepon }}</td>
                    </tr>
                </table>

                <!-- Section C -->
                <table class="skor-table ">
                    <tr>
                        <th rowspan="2" width="5%">No</th>
                        <th rowspan="2" width="25%">Jenis Skoring</th>
                        <th colspan="4" width="70%">Skor Section C</th>
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
                        <td class="text-center">{{ $pengukuran->skor_mouse }}</td>
                        <td class="text-center">{{ $pengukuran->skor_durasi_kerja_mouse }}</td>
                        <td class="text-center">{{ $pengukuran->total_skor_mouse }}</td>
                        <td rowspan="2" class="text-center">{{ 0 }}</td>
                    </tr>
                    <tr>
                        <td class="text-center">2.</td>
                        <td>Keyboard</td>
                        <td class="text-center">{{ $pengukuran->skor_keyboard }}</td>
                        <td class="text-center">{{ $pengukuran->skor_durasi_kerja_keyboard }}</td>
                        <td class="text-center">{{ $pengukuran->total_skor_keyboard }}</td>
                    </tr>
                </table>

                <!-- Final Score -->
                <table class="skor-table ">
                    <tr>
                        <td width="33%" class="text-center font-bold">Skor Section A (Section B & Section C)</td>
                        <td width="5%" rowspan="2" class="no-border">&nbsp;</td>
                        <td width="27%" class="text-center font-bold">Skor ROSA</td>
                        <td width="5%" rowspan="2" class="no-border text-center" style="font-size: 14pt;">&rarr;</td>
                        <td width="30%" rowspan="2" class="text-center">{{ $pengukuran->final_skor_rosa }}</td>
                    </tr>
                    <tr>
                        <td class="text-center">{{ 0 }}</td>
                        <td class="text-center font-bold">Skoring Section A & Section D</td>
                    </tr>
                </table>

                <!-- Risk Categories -->
                <table class="skor-table ">
                    <tr>
                        <td width="27%" class="text-center">Skor Akhir</td>
                        <td class="text-center">Kategori Risiko</td>
                        <td width="51%" class="text-center">Tindakan</td>
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
                <table class="skor-table ">
                    <tr>
                        <td class="kesimpulan-cell">
                            <strong>Kesimpulan:</strong><br><br>
                            {{ $pengukuran->result }}
                        </td>
                    </tr>
                </table>
            </td>
            
            <td width="35%" class="vertical-top">
                <!-- LHP Info -->
                <table class="info-table ">
                    <tr>
                        <th width="30%" class="text-center">No. LHP</th>
                        <th width="30%" class="text-center">No. Sampel</th>
                        <th width="40%" class="text-center">Jenis Sampel</th>
                    </tr>
                    <tr>
                        <td class="text-center">{{ $personal->no_lhp }}</td>
                        <td class="text-center">{{ $personal->no_sampel }}</td>
                        <td class="text-center">{{ $personal->jenis_sampel }}</td>
                    </tr>
                </table>

                <div class="spacer"></div>

                <!-- Customer Info -->
                <table class="info-table">
                    <tr>
                        <th colspan="3" class="text-left font-bold"><u>Informasi Pelanggan</u></th>
                    </tr>
                    <tr>
                        <td width="35%" class="text-left">Nama Pelanggan</td>
                        <td width="5%" class="text-center">:</td>
                        <td width="60%" class="text-left">{{ $personal->nama_pelanggan }}</td>
                    </tr>
                    <tr>
                        <td class="text-left vertical-top">Alamat / Lokasi Sampling</td>
                        <td class="text-center vertical-top">:</td>
                        <td class="text-left vertical-top">{{ $personal->alamat_pelanggan }}</td>
                    </tr>
                </table>

                <div class="spacer"></div>

                <!-- Sampling Info -->
                <table class="info-table">
                    <tr>
                        <th colspan="3" class="text-left font-bold"><u>Informasi Sampling</u></th>
                    </tr>
                    <tr>
                        <td width="35%" class="text-left">Tanggal Sampling</td>
                        <td width="5%" class="text-center">:</td>
                        <td width="60%" class="text-left">{{ $personal->tanggal_sampling }}</td>
                    </tr>
                    <tr>
                        <td class="text-left">Periode Analisis</td>
                        <td class="text-center">:</td>
                        <td class="text-left">{{ $personal->periode_analisis }}</td>
                    </tr>
                    <tr>
                        <td class="text-left">Jenis Analisis</td>
                        <td class="text-center">:</td>
                        <td class="text-left">Pengumpulan Data (Pengukuran & Skoring)</td>
                    </tr>
                    <tr>
                        <td class="text-left vertical-top">Metode Analisis<sup style="font-size: 5pt;">*</sup></td>
                        <td class="text-center vertical-top">:</td>
                        <td class="text-left vertical-top">Pengamatan Langsung - ROSA (Rapid Office Restrain Assessment)</td>
                    </tr>
                </table>

                <div class="spacer"></div>

                <!-- Worker Info -->
                <table class="info-table">
                    <tr>
                        <th colspan="3" class="text-left font-bold"><u>Data Individu/Pekerja yang Diukur</u></th>
                    </tr>
                    <tr>
                        <td width="35%" class="text-left">Nama Pekerja</td>
                        <td width="5%" class="text-center">:</td>
                        <td class="text-left">{{ $personal->nama_pekerja }}</td>
                    </tr>
                    <tr>
                        <td class="text-left vertical-top">Jenis Pekerja</td>
                        <td class="text-center vertical-top">:</td>
                        <td class="text-left vertical-top">{{ $personal->aktivitas_ukur }}</td>
                    </tr>
                </table>

                <div class="spacer"></div>

                <!-- Notes -->
                <table class="info-table">
                    <tr>
                        <td width="5%" class="text-right vertical-top"><sup style="font-size: 5pt;">*</sup></td>
                        <td width="95%" class="text-left" style="font-size: 5pt;">
                            Metode Analisis Mengacu kepada Development and Evaluation of an Office
                            Ergonomic Risk Checklist: The Rapid Office Strain Assessment (ROSA)
                            by Michael Sonne, Dino L. Villalta, and M. Andrews, 2012.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>