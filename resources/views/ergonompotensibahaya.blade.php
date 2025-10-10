<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pemeriksaan Potensi Bahaya Ergonomi</title>
    <style>
        /* CSS Global untuk Halaman */
        @page {
            header: html_LayoutHeader; /* Terapkan header layout ke semua halaman */
            margin-top: 10mm;
            margin-bottom: 0;
            margin-left: 10mm;
            margin-right: 0;
        }

        /* Styling Body Utama (Area Konten Kiri) */
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            margin-right: 118mm; 
        }
        .konten-kiri {
            width: 159mm; /* Lebar pasti untuk konten kiri agar tidak tumpah */
        }

        /* Styling Umum untuk Konten (Baik di Kiri maupun Kanan) */
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px; text-align: left; vertical-align: top; font-size: 8.5pt; }
        th { font-weight: bold; text-align: center; background-color: #e8e8e8; }
        .section { padding: 8px; margin-bottom: 0; page-break-inside: avoid; }
        .section-title { font-weight: bold; font-size: 9.5pt; padding: 5px; margin: -8px -8px 8px -8px; border-bottom: 2px solid #333; background-color: #e8e8e8; }
        .info-table { border: none; }
        .info-table td { border: none; padding: 1px 0; }
        .table-potensi-bahaya td[rowspan] { background-color: #f5f5f5; text-align: center; vertical-align: middle; font-weight: bold; }
        .total-score-table td, .rekap-table td { font-weight: bold; background-color: #f0f0f0; }
        .signature-section { text-align: center; font-size: 8pt; margin-top: 10px; page-break-inside: avoid; }
        .signature-text { margin-top: 40px; }
        .signature-text strong { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>

    <table style="width: 100%; border-collapse: separate; border-spacing: 8mm 0;">
        <tr>
            <td style="width: 58%; border: none; vertical-align: top;">
                
            </td>
            
            <td style="width: 110mm; border: none; vertical-align: top;">
                
                <table class="lhp-info-table">
                    <thead><tr><th>No. LHP</th><th>No. Sampel</th><th>Jenis Sampel</th></tr></thead>
                    <tbody><tr><td>{{$personal->no_lhp}}</td><td>{{$personal->no_sampel}}</td><td>Ergonomi Potensi Bahaya</td></tr></tbody>
                </table>
                <div style="height: 8px;"></div>
                <div class="section">
                    <div class="section-title">Informasi Pelanggan</div>
                    <table class="info-table">
                        <tr><td style="width:35%">Nama Pelanggan</td><td style="width:5%">:</td><td>{{ strtoupper($personal->nama_pelanggan) }}</td></tr>
                        <tr><td>Alamat / Lokasi</td><td>:</td><td>{{ $personal->alamat_pelanggan }}</td></tr>
                    </table>
                </div>
                <div class="section">
                    <div class="section-title">Informasi Sampling</div>
                    <table class="info-table">
                        <tr><td style="width:35%">Tanggal</td><td style="width:5%">:</td><td>{{ $personal->tanggal_sampling }}</td></tr>
                        <tr><td>Periode Analisis</td><td>:</td><td>{{ $personal->periode_analisis }}</td></tr>
                        <tr><td>Metode Analisa</td><td>:</td><td>Observasi Potensi Bahaya Ergonomi SNI 9011:2021</td></tr>
                    </table>
                </div>
                <div class="section">
                        <div class="section-title">Data Individu/Pekerja yang Diukur</div>
                        <table class="info-table">
                            <tr><td style="width:35%">Nama</td><td style="width:5%">:</td><td>{{ $personal->nama_pekerja }}</td></tr>
                            <tr><td>Usia</td><td>:</td><td>{{ $personal->usia }} Tahun</td></tr>
                            <tr><td>Jenis Pekerjaan</td><td>:</td><td>{{$personal->aktivitas_ukur}}</td></tr>
                            <tr><td>Lama Bekerja</td><td>:</td><td>{{ $personal->lama_kerja }} Tahun</td></tr>
                        </table>
                </div>
                <div class="section">
                        <table class="uraian-tugas-table">
                        <thead><tr><th style="width:10%">No.</th><th>Uraian Tugas Singkat</th><th>Waktu/Durasi Kerja</th></tr></thead>
                        <tbody>
                            @for($i = 1; $i <= 5; $i++)
                            <tr><td>{{ $i }}</td><td></td><td></td></tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
                <div class="section">
                    <div class="section-title">Interpretasi Hasil Penilaian**</div>
                    <table class="interpretasi-table">
                        <thead><tr><th style="width: 25%;">Skor</th><th style="width: 75%;">Interpretasi</th></tr></thead>
                        <tbody>
                            <tr><td>&lt;2</td><td>Kondisi tempat kerja aman</td></tr>
                            <tr><td>3 - 6</td><td>Perlu pengamatan lebih lanjut</td></tr>
                            <tr><td>≥7</td><td>Berbahaya</td></tr>
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
                    @if($ttd != null)
                        <div class="signature-section">
                            <div class="signature-date">Tangerang Selatan, {{ $ttd->tanggal }}</div>
                                @if($ttd->qr_path != null)
                                <img src="{{ $ttd->qr_path }}" width="40" height="40" alt="qr-code" style="margin: 5px auto;">
                                @else
                                <div style="height: 40px;"></div>
                                @endif
                            <div class="signature-text">
                                <strong>{{$ttd->nama_karyawan}}</strong>
                                <span style="font-size:7.5pt;">{{$ttd->jabatan_karyawan}}</span>
                            </div>
                        </div>
                @endif
            </td>
        </tr>
    </table>
<div class="konten-kiri">
    <div class="section">
        <div class="section-title">I. Daftar Periksa Potensi Bahaya Tubuh Bagian Atas</div>
        <table class="table-potensi-bahaya">
            <thead><tr><th style="width: 24%">Kategori</th><th>Potensi Bahaya</th><th style="width:10%">Skor</th></tr></thead>
            <tbody>
                @foreach($groupedAtas as $kategori => $rows)
                    @foreach($rows as $i => $row)
                        <tr>
                            @if($i === 0)<td rowspan="{{ count($rows) }}">{{ $kategori }}</td>@endif
                            <td>{{ $row['potensi'] }}</td>
                            <td style="font-weight: bold; text-align: center;">{{ $row['skor'] }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="section">
        <div class="section-title">II. Daftar Periksa Potensi Bahaya Tubuh Bagian Bawah</div>
        <table class="table-potensi-bahaya">
                <thead><tr><th style="width: 24%">Kategori</th><th>Potensi Bahaya</th><th style="width:10%">Skor</th></tr></thead>
            <tbody>
                @foreach($groupedBawah as $kategori => $rows)
                    @foreach($rows as $i => $row)
                        <tr>
                            @if($i === 0)<td rowspan="{{ count($rows) }}">{{ $kategori }}</td>@endif
                            <td>{{ $row['potensi'] }}</td>
                            <td style="font-weight: bold; text-align: center;">{{ $row['skor'] }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
        <table class="total-score-table">
            <tr>
                <td>Total Skor I dan II</td>
                <td style="width: 25%"></td>
            </tr>
        </table>
    </div>
    <div class="section">
        <div class="section-title">III. Daftar Periksa Pengamatan Beban Secara Manual</div>
        <table>
            <thead>
                <tr>
                    <th>Jarak Pengangkatan</th>
                    <th>Berat Beban</th>
                    <th>Skor</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Skor Langkah 1</td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        <table style="margin-top: 5px;"><thead><tr><th>Faktor Risiko</th><th>Pengangkatan</th><th>Skor</th></tr></thead><tbody><tr><td>Skor Langkah 2</td><td></td><td></td></tr></tbody></table>
    </div>
    <div class="section"><table class="total-score-table"><tr><td>Skor Langkah Akhir</td><td style="width: 25%"></td></tr></table></div>
    <div class="section"><div class="section-title">IV. Rekapitulasi Penilaian Potensi Bahaya</div><table class="rekap-table"><tr><td>Total Skor Akhir :</td><td style="width: 25%"></td></tr></table></div>
    <div class="section"><div class="section-title">V. Kesimpulan</div><div style="min-height: 50px; border: 1px solid #ddd; padding: 5px;"></div></div>
</div>
</body>
</html>