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
            font-size: 7pt;
            margin: 0;
            padding: 0;
            line-height: 1.1;
        }
        
        table {
            border-collapse: collapse;
            font-size: 7pt;
        }
        
        .main-table {
            width: 100%;
            page-break-inside: avoid;
        }
        
        .header-company {
            font-size: 6pt;
            text-align: left;
            font-family: Arial, sans-serif;
        }
        
        .header-title {
            font-size: 10pt;
            text-align: center;
            font-weight: bold;
            font-family: Arial, sans-serif;
        }
        
        .skor-table {
            width: 100%;
            margin-bottom: 3px;
            font-size: 6pt;
        }
        
        .skor-table td, .skor-table th {
            padding: 1px 2px;
            border: 1px solid #000;
            font-size: 6pt;
            line-height: 1;
        }
        
        .info-table {
            width: 100%;
            margin-bottom: 5px;
            font-size: 6pt;
        }
        
        .info-table td, .info-table th {
            padding: 1px 2px;
            font-size: 6pt;
            line-height: 1;
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
    </style>
</head>
<body>
    <!-- Header -->
    <table class="main-table">
        <tr>
            <td colspan="4" class="header-company">PT INTI SURYA LABORATORIUM</td>
        </tr>
        <tr>
            <td colspan="4" class="header-title">
                <u>LAPORAN HASIL PENGUJIAN (DRAFT)</u>
            </td>
        </tr>
    </table>
    
    <div class="spacer"></div>
    
    <!-- Main Content -->
    <table class="main-table">
        <tr>
            <td width="65%" class="vertical-top">
                <!-- Section A -->
                <table class="skor-table bordered">
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
                <table class="skor-table bordered">
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
                <table class="skor-table bordered">
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
                <table class="skor-table bordered">
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
                <table class="skor-table bordered">
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
                <table class="skor-table bordered">
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
                <table class="info-table bordered">
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