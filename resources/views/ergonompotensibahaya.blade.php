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
        .sectionP{
            margin-bottom: 5px;
            /* border: 1px solid #000; */
            padding: 3px;}
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
                <table style="border-collapse: collapse; width: 100%; border: 2px solid black; font-size: 12px;">
                    <tbody>
                        <tr style="background-color: #ffffff;">
                            <td colspan="4" style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                I. Daftar Periksa Potensi Bahaya Tubuh Bagian Atas
                            </td>
                        </tr>
                        <tr style="text-align: center; font-weight: bold;">
                            <th style="border: 1px solid black; padding: 5px; width: 15%;">No.</th>
                            <th style="border: 1px solid black; padding: 5px; width: 25%;">Kategori</th>
                            <th style="border: 1px solid black; padding: 5px;">Potensi Bahaya</th>
                            <th style="border: 1px solid black; padding: 5px; width: 80px;">Skor</th>
                        </tr>
                        @php $nomorUrut = 1; @endphp
                        @if($skorDataAtas != [] || $skorDataAtas != null)
                            @foreach(collect($skorDataAtas)->sortBy('index') as $key => $value)
                                <tr>
                                    {{-- 2. Gunakan $loop->iteration untuk penomoran tabel yang selalu urut (1, 2, 3..) --}}
                                    <td style="border: 1px solid black; padding: 5px; text-align: center;">
                                        {{ $nomorUrut }} 
                                    </td>
                                    @if($value['label'] === 'faktor')
                                        <td colspan=2 style="border: 1px solid black; padding: 5px;">
                                            {{ $value['keterangan'] }}
                                        </td>
                                    @else
                                        <td style="border: 1px solid black; padding: 5px;">
                                            {{$value['label']}}
                                            {{-- Catatan: Jika ingin kategori dinamis, sebaiknya jangan di-hardcode "Postur Janggal" --}}
                                        </td>
                                        <td style="border: 1px solid black; padding: 5px;">
                                            {{ $value['keterangan'] }}
                                        </td>
                                    @endif
                                    
                                    <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">
                                        {{ $value['skor'] }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                        @if($skorDataBawah != [] || $skorDataBawah != null)
                            <tr style="background-color: #ffffff;">
                                <td colspan="4" style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                    II. Daftar Periksa Potensi Bahaya Tubuh Bagian Bawah
                                </td>
                            </tr>
                            @foreach(collect($skorDataBawah)->sortBy('index') as $key => $value)
                                <tr>
                                    {{-- 2. Gunakan $loop->iteration untuk penomoran tabel yang selalu urut (1, 2, 3..) --}}
                                    <td style="border: 1px solid black; padding: 5px; text-align: center;">
                                        {{ $nomorUrut }} 
                                    </td>
                                    @if($value['label'] === 'faktor')
                                        <td colspan=2 style="border: 1px solid black; padding: 5px;">
                                            {{ $value['keterangan'] }}
                                        </td>
                                    @else
                                        <td style="border: 1px solid black; padding: 5px;">
                                            {{$value['label']}}
                                            {{-- Catatan: Jika ingin kategori dinamis, sebaiknya jangan di-hardcode "Postur Janggal" --}}
                                        </td>
                                        <td style="border: 1px solid black; padding: 5px;">
                                            {{ $value['keterangan'] }}
                                        </td>
                                    @endif
                                    
                                    <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">
                                        {{ $value['skor'] }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                        <tr>
                            <td colspan="3" style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">Total Skor I dan II</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">3</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="sectionP">
                <table style="border-collapse: collapse; width: 100%; border: 2px solid black; font-size: 13px;">
                    <thead>
                        <tr style="background-color: #ffffff;">
                            <td colspan="4" style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                III. Daftar Periksa Pengamatan Beban Secara Manual
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="border: 1px solid black; padding: 10px; font-weight: bold; text-align: center; width: 15%;">
                                Skor Langkah 1
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center;">
                                <span style="font-weight: bold;">33. Jarak Pengangkatan</span><br>
                                <span style="color: red;">Pengangkatan dengan jarak dekat</span>
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; width: 20%;">
                                <b>Berat Beban</b><br>
                                <span style="color: red; font-weight: bold;">7 Kg</span>
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; width: 10%;">
                                <b>Skor</b><br>
                                <span style="font-weight: bold;">3</span>
                            </td>
                        </tr>

                        <tr>
                            <td rowspan="11" style="border: 1px solid black; padding: 10px; font-weight: bold; text-align: center;">
                                Skor Langkah 2
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">
                                Faktor Risiko
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">
                                Pengangkatan
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">
                                Skor
                            </td>
                        </tr>
                        
                        @foreach($faktorResiko as $kategori => $listItem)
                            {{-- Loop kedua untuk membuka index 0, 1, 2, dst --}}
                            @foreach($listItem as $item)
                                <tr>
                                    <td style="border: 1px solid black; padding: 5px;">
                                        {{-- Gunakan $item, bukan $value --}}
                                        {{ $nomorUrut++ }}. &nbsp; {{ $item['raw_text'] }}
                                    </td>
                                    <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">
                                        {{ $item['keterangan'] }}
                                    </td>
                                    <td style="border: 1px solid black; padding: 5px; text-align: center; color: red;">
                                        {{ $item['skor'] }}
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                        <tr>
                            <td colspan="3" style="border: 1px solid black; padding: 10px; text-align: center; font-weight: bold;">
                                Skor Langkah Akhir
                            </td>
                            <td style="border: 1px solid black; padding: 10px; text-align: center; font-weight: bold;">
                                6
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
            <div class="sectionP">
                <table style="border-collapse: collapse; width: 100%; border: 2px solid black; font-size: 13px; margin-bottom: 20px;">
                    <thead>
                        <tr style="background-color: #ffffff;">
                            <td colspan="3" style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                IV. Rekapitulasi Penilaian Potensi Bahaya
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="border: 1px solid black; padding: 5px; font-weight: bold; width: 150px;">
                                Total Skor Akhir
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; width: 30px;">
                                :
                            </td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">
                                9
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table style="border-collapse: collapse; width: 100%; border: 2px solid black; font-size: 13px;">
                    <thead>
                        <tr style="background-color: #ffffff;">
                            <td style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                V. Kesimpulan
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="border: 1px solid black; padding: 10px; line-height: 1.5;">
                                Berdasarkan hasil pengamatan daftar periksa potensi bahaya ergonomi pada jenis pekerjaan tersebut, 
                                dapat disimpulkan bahwa Rekapitulasi Penilaian Potensi Bahaya memiliki hasil interpretasi tingkat risiko : <br>
                                <b>Berbahaya</b>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
                            <td style="width:25%">Nama</td>
                            <td style="width:3%">:</td>\
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
                            <th style="width:13%">No.</th>
                            <th>Uraian Tugas Singkat</th>
                            <th>Waktu/Durasi Kerja</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for($i = 1; $i <= 2; $i++)
                            <tr>
                                <td style="text-align: center;" >{{ $i }}</td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
            <div class="sectionP">
                <div class="section-titleP">Interpretasi Hasil Penilaian**</div>
                <table class="interpretasi-table">
                    <thead>
                        <tr>
                            <th style="width: 10%; text-align: center;">Skor</th>
                            <th style="width: 75%;">Interpretasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: center;">&lt;2</td>
                            <td>Kondisi tempat kerja aman</td>
                        </tr>
                        <tr>
                            <td style="text-align: center;">3 - 6</td>
                            <td>Perlu pengamatan lebih lanjut</td>
                        </tr>
                        <tr>
                            <td style="text-align: center;">&ge;7</td>
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
                            <br>**Interpretasi Hasil Penilaian Daftar Periksa Potensi Bahaya Ergonomi Mengacu kepada Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja Bagian 5.1.
                        </td>
                    </tr>
                </table>
            </div>
            <table style="width: 100%; margin-top: 10px; border: none;">
                <tr>
                    <td style="width: 50%; border: none;"></td>

                    <td style="width: 50%; border: none; text-align: center; vertical-align: top;">
                        
                        <div style="margin-bottom: 5px;">
                            Tangerang, {{ $ttd->tanggal ?? '13 Agustus 2025' }}
                        </div>

                        @if($ttd && $ttd->qr_path)
                            <img src="{{ $ttd->qr_path }}" style="width: 50px; height: 50px; display: inline-block;" alt="QR TTD">
                        @else
                            <br><br><br>
                            <div style="font-weight: bold; text-decoration: underline;">
                                (Abidah Walfathiyyah)
                            </div>
                            <div>Technical Control Supervisor</div>
                        @endif

                    </td>
                </tr>
            </table>
        </div>  
    </div>
</body>
</html>