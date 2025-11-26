<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pemeriksaan Potensi Bahaya Ergonomi</title>
    <style>
        /* CSS Global untuk Halaman */


        /* Styling Body Utama (Area Konten Kiri) */
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin-right: 118mm; 
        }
         .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .konten-kiri {
            float: left; /* Membuat elemen ini 'mengambang' ke kiri */
            width: 159mm; /* Lebar pasti untuk konten kiri */
        }
        .konten-kanan {
            float: right; /* Membuat elemen ini 'mengambang' ke kanan */
            width: 110mm; /* Lebar pasti untuk konten kanan */
        }

        /* Styling Umum untuk Konten (Baik di Kiri maupun Kanan) */
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px; text-align: left; vertical-align: top; font-size: 12px; }
        th { font-weight: bold; text-align: center; background-color: #e8e8e8; }
        .section { padding: 8px; margin-bottom: 0; page-break-inside: avoid; }
        .section-titleP { font-weight: bold; font-size: 12px;}
        .info-table { border: none; }
        .info-table td { border: none; padding: 1px 0; font-size: 12px; }
        .lhp-info-table td {font-size: 12px; }
        .table-potensi-bahaya td[rowspan] { background-color: #f5f5f5; text-align: center; vertical-align: middle; font-weight: bold; }
        .total-score-table td, .rekap-table td { font-weight: bold; background-color: #f0f0f0; }
        .signature-section { text-align: center; font-size: 10px; margin-top: 10px; page-break-inside: avoid; }
        .signature-text { margin-top: 40px; }
        .signature-text strong { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="clearfix">
        <div class="konten-kiri">
            <div class="sectionP">
                <table class="table-potensi-bahaya">
                    <thead>
                        <tr>
                            <th rowspan=4>I. Daftar Periksa Potensi Bahaya Tubuh Bagian Atas</th>
                        </tr>
                        <tr>
                            <th>No</th>
                            <th>Kategori</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        
                    </tbody>
                </table>
            </div>
            <div class="sectionP">
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
            <div class="sectionP">
                <div class="section-titleP">III. Daftar Periksa Pengamatan Beban Secara Manual</div>
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
            <div class="sectionP"><table class="total-score-table"><tr><td>Skor Langkah Akhir</td><td style="width: 25%"></td></tr></table></div>
            <div class="sectionP"><div class="section-titleP">IV. Rekapitulasi Penilaian Potensi Bahaya</div><table class="rekap-table"><tr><td>Total Skor Akhir :</td><td style="width: 25%"></td></tr></table></div>
            <div class="sectionP"><div class="section-titleP">V. Kesimpulan</div><div style="min-height: 50px; border: 1px solid #ddd; padding: 5px;"></div></div>
        </div>
        <div class="konten-kanan">
            <table class="lhp-info-table">
                            <thead>
                                <tr>
                                    <th>No. LHP</th>
                                    <th>No. Sampel</th>
                                    <th>Jenis Sampel</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{$personal->no_lhp}}</td>
                                    <td>{{$personal->no_sampel}}</td>
                                    <td>ERGONOMI</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="sectionP">
                            <div class="section-titleP">Informasi Pelanggan</div>
                            <table class="info-table">
                                <tr>
                                    <td style="width:25%">Nama Pelanggan</td>
                                    <td style="width:3%">:</td>
                                    <td style="width:72%;text-align:start;">{{ strtoupper($personal->nama_pelanggan) }}</td>
                                </tr>
                                <tr>
                                    <td style="width:25%" >Alamat / Lokasi</td>
                                    <td style="width:3%">:</td>
                                    <td style="width: 72%;text-align:start;">{{ $personal->alamat_pelanggan }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="sectionP">
                            <div class="section-titleP">Informasi Sampling</div>
                            <table class="info-table">
                                <tr>
                                    <td style="width:25%">Tanggal Sampling</td>
                                    <td style="width:3%">:</td>
                                    <td style="width: 72%;text-align:start;">{{ $personal->tanggal_sampling }}</td>
                                </tr>
                                <tr>
                                    <td style="width:25%">Metode Analisa</td>
                                    <td style="width:3%">:</td>
                                    <td style="width: 72%;text-align:start;">Observasi Potensi Bahaya Ergonomi SNI 9011:2021</td>
                                </tr>
                            </table>
                        </div>
                        <div class="sectionP">
                                <div class="section-titleP">Data Individu/Pekerja yang Diukur</div>
                                <table class="info-table">
                                    <tr>
                                        <td style="width:35%">Nama</td>
                                        <td style="width:5%">:</td>\
                                        <td style="width: 72%;text-align:start;">{{ $personal->nama_pekerja }}</td>
                                    </tr>
                                    <tr>
                                        <td style="width:25%">Usia</td>
                                        <td style="width:3%">:</td>
                                        <td style="width: 72%;text-align:start;">{{ $personal->usia }} Tahun</td>
                                    </tr>
                                    <tr>
                                        <td style="width:25%">Jenis Pekerjaan</td>
                                        <td style="width:3%">:</td>
                                        <td style="width: 72%;text-align:start;">{{$personal->aktivitas_ukur}}</td>
                                    </tr>
                                    <tr>
                                        <td style="width:25%">Lama Bekerja</td>
                                        <td style="width:3%">:</td>
                                        <td style="width: 72%;text-align:start;">{{ $personal->lama_kerja }} Tahun</td>
                                    </tr>
                                </table>
                        </div>
                        <div class="sectionP">
                                <table class="uraian-tugas-table">
                                <thead>
                                    <tr>
                                        <th style="width:10%">No.</th>
                                        <th>Uraian Tugas Singkat</th>
                                        <th>Waktu/Durasi Kerja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @for($i = 1; $i <= 2; $i++)
                                    <tr><td>{{ $i }}</td><td></td><td></td></tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>
                        <div class="sectionP">
                            <div class="section-titleP">Interpretasi Hasil Penilaian**</div>
                            <table class="interpretasi-table">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">Skor</th>
                                        <th style="width: 75%;">Interpretasi</th>
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
                        
                        <div class="sectionP">
                            <table style="border: 0;">
                                <tr>
                                    <td style="border: 0; font-size: 9px; line-height: 1.3;">
                                        * Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja.
                                        <br>** Interpretasi Hasil Penilaian Daftar Periksa Potensi Bahaya Ergonomi Mengacu kepada Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja Bagian 5.1.
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="signature-section">
                            @if($ttd != null)
                                @if($ttd->qr_path != null)
                                    <table class="signature-table">
                                        <tr>
                                            <td class="signature-left"></td>
                                            <td class="signature-right">
                                                <div class="signature-date">
                                                   Tangerang, {{ $ttd->tanggal }}
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