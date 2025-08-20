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
            background-color: #f9f9f9;
        }

        .page-container {
            width: 100%;
            margin: auto;
            background-color: #fff;
            padding: 15px;
            border: 1px solid #ccc;
        }

        .main-header-title {
            text-align: center;
            font-weight: bold;
            font-size: 1.5em;
            margin-bottom: 15px;
            text-decoration: underline;
        }

        .two-column-layout {
            width: 100%;
            overflow: hidden;
            margin-bottom: 15px;
        }

        .column {
            float: left;
        }

        .column-left {
            width: 60%;
            padding-right: 10px;
        }

        .column-right {
            width: 30%;
        }

        .section {
            border: 1px solid #000;
            padding: 6px;
            background-color: #fff;
            margin-bottom: 10px;
        }

        .section-title {
            font-weight: bold;
            background-color: #e0e0e0;
            padding: 3px 6px;
            margin: -6px -6px 6px -6px;
            border-bottom: 1px solid #000;
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
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }

        .text-input-space {
            width: 100%;
            border: 1px solid #ccc;
            padding: 2px;
            min-height: 1.5em;
            background-color: #fff;
        }

        .multi-line-input {
            width: 100%;
            border: 1px solid #000;
            padding: 4px;
            min-height: 40px;
            background-color: #fff;
        }

        .footer-text {
            font-size: 0.85em;
            margin-top: 15px;
            border-top: 1px solid #ccc;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
        }

        .signature-block {
            margin-top: 15px;
            text-align: right;
        }

        .signature-block .signature-name {
            margin-top: 30px;
            font-weight: bold;
            text-decoration: underline;
        }

        .interpretasi-table td { text-align: center; }
        .interpretasi-table td:last-child { text-align: left; }

        .uraian-tugas-table td { height: 1.8em; }

        /* Container gabungan antara gambar & daftar */
        .image-placeholder-container {
            width: 100%;
            margin-top: 10px;
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }

        /* Placeholder Gambar Tubuh */
        .image-placeholder {
            width: 180px;
            height: 330px;
            border: 1px solid #000;
            text-align: center;
            font-size: 10px;
            line-height: 1.4;
            padding: 5px;
            flex-shrink: 0; /* Tambah jarak ke kanan */
        }

        /* Daftar bagian tubuh */
        .body-parts-list-container {
            flex: 1; 
        }

        .body-parts-list {
            list-style: none;
            padding-left: 0;
            margin: 0;
            font-size: 9px;
        }

        .body-parts-list li {
            margin-bottom: 5px;
            line-height: 1.2;
        }

        .body-parts-list li span {
            display: inline-block;
            width: 110px;
            white-space: nowrap;
        }

        .body-parts-list li .input-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            width: 60%;
            height: 14px;
            vertical-align: middle;
        }


    </style>
</head>
<body>
    <div class="page-container">
        <div class="main-header-title">LAPORAN HASIL PENGUJIAN</div>
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
                            Ruang untuk<br>Gambar Diagram Tubuh<br>(~180x380px)
                        </div>
                        <div class="body-parts-list-container">
                            <table class="body-parts-list">
                                <tr>
                                    <td><span>1 = Leher</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->sakit_leher !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->sakit_leher->Poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>2 = Bahu</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->sakit_bahu !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->sakit_bahu->Poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>3 = Punggung Atas</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->Keluhan_Bagian_Tubuh->Sakit_Punggung_Atas !== 'Tidak') ? $pengukuran->Keluhan_Bagian_Tubuh->Sakit_Punggung_Atas->Poin : 0 }}</div></td>
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
                                <div style="min-height: 100px;">Pengukuran ergonomi pada pekerja atas nama Jaja Harudin pada divisi/departemen shift powder memiliki keluhan sering pada bagian</div>
                            </div>
                            <div class="section">
                                <div class="section-title">KESIMPULAN SURVEI LANJUTAN</div>
                                <div  style="min-height: 20px;">Pengukuran ergonomi pada pekerja atas nama
                                    Jaja Harudin pada divisi/departemen shift powder memiliki tingkat risiko keluhan sebagai
                                    berikut:</div>
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
                            <td>Ergonomi</td>
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
                            <td> <div class="text-input-space">{{ $personal->periode_analis }}</div>
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
                </div>
            </div>

            <div style="clear: both;"></div>
        </div>
    </div>
</body>
</html>
