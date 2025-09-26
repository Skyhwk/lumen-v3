<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian</title>
   
   <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 9px;
            background-color: #f9f9f9;
        }

        .page-container {
            width: 100%;
            margin: 0;
            background-color: #fff;
            padding: 8px;
            border: none;
        }

        .main-header-title {
            text-align: center;
            font-weight: bold;
            font-size: 1.5em;
            margin-bottom: 10px;
            text-decoration: underline;
        }

        .two-column-layout {
            width: 100%;
            margin-bottom: 10px;
            display: table;
            table-layout: fixed;
        }

        .column {
            display: table-cell;
            vertical-align: top;
            padding: 0;
        }

        .column-left {
            width: 55%;
            padding-right: 5px;
        }

        .column-right {
            width: 44%;
            padding-left: 5px;
        }

        .section {
            border: 1px solid #000;
            padding: 5px;
            background-color: #fff;
            margin-bottom: 8px;
        }

        .section-title {
            font-weight: bold;
            background-color: #e0e0e0;
            padding: 2px 4px;
            margin: -5px -5px 4px -5px;
            border-bottom: 1px solid #000;
            font-size: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #000;
            padding: 2px;
            text-align: left;
            vertical-align: top;
            font-size: 8px;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }

        .text-input-space {
            width: 100%;
            border: 1px solid #ccc;
            padding: 1px;
            min-height: 1.2em;
            background-color: #fff;
            font-size: 8px;
        }

        .multi-line-input {
            width: 100%;
            border: 1px solid #000;
            padding: 3px;
            min-height: 30px;
            background-color: #fff;
            font-size: 8px;
        }

        .footer-text {
            font-size: 7px;
            margin-top: 10px;
            border-top: 1px solid #ccc;
            padding-top: 5px;
            display: flex;
            justify-content: space-between;
        }

        .signature-block {
            margin-top: 10px;
            text-align: right;
        }

        .signature-block .signature-name {
            margin-top: 20px;
            font-weight: bold;
            text-decoration: underline;
        }

        .interpretasi-table td { 
            text-align: center; 
            font-size: 8px;
        }
        .interpretasi-table td:last-child { 
            text-align: left; 
        }

        .uraian-tugas-table td { 
            height: 1.5em; 
        }

        /* Container gabungan antara gambar & daftar */
        .image-placeholder-container {
            width: 100%;
            margin-top: 8px;
            min-height: 250px;
            position: relative;
        }

        /* Placeholder Gambar Tubuh */
        .image-placeholder {
            width: 120px;
            height: 240px;
            border: 1px solid #000;
            text-align: center;
            font-size: 8px;
            line-height: 1.3;
            padding: 3px;
            float: left;
            margin-right: 10px;
            box-sizing: border-box;
            position: relative;
        }

        .body-map {
           width: 100%;
           height: 100%;
           object-fit: contain;
        }

        /* Daftar bagian tubuh */
        .body-parts-list-container {
            margin-left: 130px;
            width: auto;
        }

        /* Styling untuk tabel daftar bagian tubuh */
        .body-parts-list {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
            margin-bottom: 8px;
        }

        .body-parts-list td {
            padding: 2px 3px;
            border: 1px solid #000;
            vertical-align: middle;
        }

        .body-parts-list td:first-child {
            width: 70%;
            text-align: left;
        }

        .body-parts-list td:last-child {
            width: 30%;
            text-align: center;
        }

        .input-line {
            font-weight: bold;
            text-align: center;
            font-size: 8px;
        }

        /* Styling untuk section dalam container */
        .body-parts-list-container .section {
            margin-top: 8px;
            margin-bottom: 6px;
        }

        .body-parts-list-container .section-title {
            text-align: left;
            font-size: 7px;
            margin-bottom: 3px;
        }

        .body-parts-list-container .section div:not(.section-title) {
            font-size: 7px;
            line-height: 1.2;
            text-align: justify;
            padding: 3px;
            border: 1px solid #000;
        }

        /* Clearfix untuk memastikan container tidak collapse */
        .image-placeholder-container::after {
            content: "";
            display: table;
            clear: both;
        }

        /* Styling khusus untuk konten dalam box */
        .analysis-content {
            min-height: 40px;
            padding: 3px;
            border: 1px solid #000;
            font-size: 7px;
            line-height: 1.2;
        }

        .conclusion-content {
            min-height: 25px;
            padding: 3px;
            border: 1px solid #000;
            font-size: 7px;
            line-height: 1.2;
        }

        /* Info table di kolom kanan */
        .lhp-info-table {
            margin-bottom: 8px;
        }

        .lhp-info-table th, .lhp-info-table td {
            font-size: 7px;
            padding: 2px;
        }

        .info-table {
            margin-bottom: 5px;
        }

        .info-table td {
            font-size: 7px;
            padding: 1px;
        }

        .bold {
            font-weight: bold;
            margin-bottom: 3px;
            font-size: 8px;
        }

        .notes {
            font-size: 6px;
            line-height: 1.2;
            margin-top: 5px;
            text-align: justify;
        }

        /* Signature section yang lebih kompak */
        .signature-section {
            margin-top: 8px;
            font-size: 7px;
        }

        .signature-table {
            width: 100%;
            border: none;
            margin-top: 5px;
        }

        .signature-table td {
            border: none;
            padding: 0;
        }

        .signature-left {
            width: 50%;
        }

        .signature-right {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }

        .signature-date {
            font-size: 7px;
            margin-bottom: 3px;
        }

        .signature-text {
            font-size: 7px;
            line-height: 1.1;
        }

        .signature-text strong {
            font-weight: bold;
        }

        .signature-text span {
            font-weight: normal;
        }

        /* Tabel klasifikasi risiko */
        .risk-table {
            margin-top: 5px;
        }

        .risk-table th, .risk-table td {
            font-size: 7px;
            padding: 2px;
            text-align: center;
        }

        .risk-table td:last-child {
            text-align: left;
        }

        /* Penyesuaian untuk label kolom */
        .label-column {
            width: 60%;
            font-size: 8px;
        }

        /* Centered text utility */
        .centered-text {
            text-align: center;
        }

        /* Responsive adjustments */
        @media print {
            .page-container {
                padding: 5px;
            }
            
            .section {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- DIV main-header-title LAPORAN HASIL PENGUJIAN -->
        <div class="two-column-layout">
            <!-- KIRI -->
            <div class="column column-left">
                <div class="section">
                    <div class="section-title">HASIL ANALISIS SURVEI AWAL GANGGUAN OTOT DAN RANGKA</div>
                    <table class="info-table">
                        <tr>
                            <td class="label-column">1. Tangan Dominan</td>
                            <td> <div class="text-input-space">{{ $pengukuran->Identitas_Umum->{'Tangan Dominan'} }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-column">2. Mata Kerja</td>
                            <td> <div class="text-input-space">{{ $pengukuran->Identitas_Umum->{'Masa Kerja'} }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-column">3. Merasakan Kelelahan Mental Setelah Bekerja</td>
                            <td> <div class="text-input-space">{{ $pengukuran->Identitas_Umum->{'Lelah Mental'} }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-column">4. Merasakan Kelelahan Fisik Setelah Bekerja</td>
                            <td> <div class="text-input-space">{{ $pengukuran->Identitas_Umum->{'Lelah Fisik'} }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-column">5. Merasakan Ketidaknyamanan/Nyeri/Sakit Dalam Satu Tahun Terakhir
                            </td>
                            <td> <div class="text-input-space">{{ $pengukuran->Identitas_Umum->{'Rasa Sakit'} }}</div>
                            </td>
                        </tr>
                    </table>
                    <div class="bold">KESIMPULAN SURVEI AWAL</div>
                    <div class="text-input-space" style="min-height: 30px;">Pekerja memiliki risiko bahaya ergonomi
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">HASIL ANALISIS SURVEI LANJUTAN GANGGUAN OTOT DAN RANGKA</div>
                    <div class="image-placeholder-container">
                        <div class="image-placeholder">
                            <img src="{{ public_path('dokumen/img_ergo/gotrak/anatomygontrak.png') }}" alt="Body Map" class="body-map">
                        </div>
                        <div class="body-parts-list-container">
                            <table class="body-parts-list">
                                <tr>
                                    <td><span>1 = Leher</span></td>
                                    <td>
                                        <div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->sakit_leher !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->sakit_leher->Poin : 0 }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><span>2 = Bahu</span></td>
                                    <td>
                                        <div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->sakit_bahu !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->sakit_bahu->Poin : 0 }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><span>3 = Punggung Atas</span></td>
                                    <td>
                                        <div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->Sakit_Punggung_Atas !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->Sakit_Punggung_Atas->Poin : 0 }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><span>4 = Lengan</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->sakit_lengan !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->sakit_lengan->Poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>5 = Siku</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->sakit_siku !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->sakit_siku->Poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>6 = Punggung Bawah</span></td>
                                    <td><div class="input-line">{{( $pengukuran->Keluhan_Bagian_Tubuh->Sakit_Punggung_Bawah !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->Sakit_Punggung_Bawah->Poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>7 = Tangan</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->sakit_tangan !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->sakit_tangan->Poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>8 = Pinggul</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->sakit_pinggul !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->sakit_pinggul->Poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>9 = Paha</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->sakit_paha !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->sakit_paha->Poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>10 = Lutut</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->sakit_lutut !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->sakit_lutut->Poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>11 = Betis</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->sakit_betis !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->sakit_betis->Poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>12 = Kaki</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->sakit_kaki !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->sakit_kaki->Poin : 0 }}</div></td>
                                </tr>
                            </table>
                            
                            <div class="section">
                                <div class="section-title">ANALISIS POTENSI BAHAYA</div>
                                <div class="analysis-content">Pengukuran ergonomi pada pekerja atas nama Jaja Harudin pada divisi/departemen shift powder memiliki keluhan sering pada bagian</div>
                            </div>
                            
                            <div class="section">
                                <div class="section-title">KESIMPULAN SURVEI LANJUTAN</div>
                                <div class="conclusion-content">Pengukuran ergonomi pada pekerja atas nama Jaja Harudin pada divisi/departemen shift powder memiliki tingkat risiko keluhan sebagai berikut:</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- KANAN -->
            <div class="column column-right">
                <table class="lhp-info-table info-table">
                    <tbody>
                        <tr>
                            <th>No. LHP</th>
                            <th>No. Sampel</th>
                            <th>Jenis Sampel</th>
                        </tr>
                        <tr>
                            <td>{{ $personal->no_lhp }}</td>
                            <td>{{ $personal->no_sampel }}</td>
                            <td>Ergonomi - Gontrak</td>
                        </tr>
                    </tbody>
                </table>

                <div class="section">
                    <span class="bold">Informasi Pelanggan</span>
                    <table class="info-table">
                        <tr>
                            <td style="width: 120px;">Nama Pelanggan</td>
                            <td> <div class="text-input-space">{{ $personal->nama_pelanggan }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>Alamat / Lokasi Sampling</td>
                            <td> <div class="text-input-space">{{ $personal->alamat_pelanggan }}</div>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="section">
                    <span class="bold">Informasi Sampling</span>
                    <table class="info-table">
                        <tr>
                            <td style="width: 120px;">Metode Sampling</td>
                            <td> SNI 9011:2021</td>
                        </tr>
                        <tr>
                            <td>Tanggal Sampling</td>
                            <td> <div class="text-input-space">{{ $personal->tanggal_sampling }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>Periode Analisis</td>
                            <td> <div class="text-input-space">{{ $personal->periode_analisis }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>Jenis Analisis</td>
                            <td> Kuesioner</td>
                        </tr>
                        <tr>
                            <td>Metode Analisis*</td>
                            <td> Identifikasi Keluhan Gangguan Otot dan Rangka</td>
                        </tr>
                    </table>
                </div>

                <div class="section">
                    <span class="bold">Data Individu/Pekerja yang Diukur</span>
                    <table class="info-table" style="margin-bottom: 10px;">
                        <tr>
                            <td style="width: 120px;">Nama</td>
                            <td> <div class="text-input-space">{{ $personal->nama_pekerja }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>Posisi/Jabatan</td>
                            <td> <div class="text-input-space">{{ $personal->jabatan }}</div>
                            </td>
                        </tr>
                    </table>

                    <table>
                        <thead>
                            <tr>
                                <th style="width: 10%;">No.</th>
                                <th style="width: 60%;">Uraian Tugas Singkat</th>
                                <th style="width: 30%;">Waktu/Durasi Kerja (Jam/Minggu)</th>
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
                        </tbody>
                    </table>
                </div>

                <div class="section">
                    <div class="section-title">Tingkat Risiko Keluhan Gangguan Otot dan Rangka **</div>
                    <table>
                        <thead>
                            <tr>
                                <th class="centered-text">Skor Keluhan</th>
                                <th class="centered-text">Tingkat Risiko Keluhan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="centered-text">1 - 4</td>
                                <td>Tingkat risiko rendah</td>
                            </tr>
                            <tr>
                                <td class="centered-text">5 - 7</td>
                                <td>Tingkat risiko sedang</td>
                            </tr>
                            <tr>
                                <td class="centered-text">8 - 16</td>
                                <td>Tingkat risiko tinggi</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="notes">
                        * Metode Analisis Mengacu kepada Standar Nasional Indonesia Nomor 9011 Tahun 2021 Pengukuran dan
                        Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja
                        <br>** Tabel Klasifikasi Tingkat Risiko Mengacu kepada Standar Nasional Indonesia Nomor 9011
                        Tahun 2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja
                    </div>
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
            <div style="clear: both;"></div>
        </div>
    </div>
</body>
</html>