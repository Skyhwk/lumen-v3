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
                {{-- DIAGNOSA PENJUMLAHAN --}}
                <table style="border-collapse: collapse; width: 100%; border: 2px solid black; font-size: 12px;">
                    <tbody>
                        <tr style="background-color: #ffffff;">
                            <td colspan="4" style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                I. Hasil Penilaian Potensi Bahaya Tubuh Bagian Atas
                            </td>
                        </tr>
                        <tr style="text-align: center; font-weight: bold;">
                            <th style="border: 1px solid black; padding: 5px; width: 15%;">No.</th>
                            <th style="border: 1px solid black; padding: 5px; width: 25%;">Kategori</th>
                            <th style="border: 1px solid black; padding: 5px;">Potensi Bahaya</th>
                            <th style="border: 1px solid black; padding: 5px; width: 80px;">Skor</th>
                        </tr>
                        @php 
                            $nomorUrut = 1;
                            $title ='Total Skor I';
                            $totalBawah=null;
                            $totalAtas=null;
                            $skorLangkahAkhir =null;
                        @endphp
                        @if($skorDataAtas != [] || $skorDataAtas != null)
                            @php
                                $totalAtas = collect($skorDataAtas)->sum(function($item) {
                                    // Pakai (float) agar aman, baik datanya int maupun float
                                    return (float) ($item['skor'] ?? 0); 
                                });
                            @endphp
                           
                            @foreach(collect($skorDataAtas)->sortBy('index') as $key => $value)
                                <tr>
                                    {{-- 2. Gunakan $loop->iteration untuk penomoran tabel yang selalu urut (1, 2, 3..) --}}
                                    <td style="border: 1px solid black; padding: 5px; text-align: center;">
                                        {{ $nomorUrut++ }} 
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
                                    
                                    <td style="border: 1px solid black; padding: 5px; text-align: center;">
                                        {{ $value['skor'] }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                        @if($skorDataBawah != [] || $skorDataBawah != null)
                            @php $title ='Total Skor I Dan II'; @endphp
                            @php
                                $totalBawah = collect($skorDataBawah)->sum(function($item) {
                                    // Pakai (float) agar aman, baik datanya int maupun float
                                    return (float) ($item['skor'] ?? 0); 
                                });
                            @endphp
                            <tr style="background-color: #ffffff;">
                                <td colspan="4" style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                    II. Hasil Penilaian Potensi Bahaya Tubuh Bagian Punggung & Bawah
                                </td>
                            </tr>
                            @foreach(collect($skorDataBawah)->sortBy('index') as $key => $value)
                                <tr>
                                    {{-- 2. Gunakan $loop->iteration untuk penomoran tabel yang selalu urut (1, 2, 3..) --}}
                                    <td style="border: 1px solid black; padding: 5px; text-align: center;">
                                        {{ $nomorUrut++ }} 
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
                                    
                                    <td style="border: 1px solid black; padding: 5px; text-align: center;">
                                        {{ $value['skor'] }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                        <tr>
                            <td colspan="3" style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">{{$title}}</td>
                            <td style="border: 1px solid black; padding: 5px; text-align: center; font-weight: bold;">
                                {{$totalAtas + $totalBawah}}
                                @php $totalSkorIdanII = $totalAtas + $totalBawah @endphp
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="sectionP">
                @if(optional($manualHandling)->posisi_angkat_beban != null && optional($manualHandling)->estimasi_berat_benda != null && !empty($faktorResiko))
                    <table style="border-collapse: collapse; width: 100%; border: 2px solid black; font-size: 13px;">
                        <thead>
                            <tr style="background-color: #ffffff;">
                                <td colspan="4" style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                    III. Daftar Periksa Pengamatan Beban Secara Manual
                                </td>
                            </tr>
                        </thead>
                        <tbody>
                            @if(optional($manualHandling)->posisi_angkat_beban != null & optional($manualHandling)->estimasi_berat_benda != null)
                                <tr>
                                    <td style="border: 1px solid black; padding: 10px; vertical-align: center; font-weight: bold; text-align: center; width: 15%;">
                                        Skor Langkah 1
                                    </td>
                                    <td style="border: 1px solid black; padding: 5px;text-align: center; vertical-align: top;">
                                        <div style="padding: 5px; margin-top:3px; font-weight: bold;  width: 100%; box-sizing: border-box;">
                                            {{$nomorUrut++}}. Jarak Pengangkatan
                                        </div>
                                        <hr style="border: 1px solid black;">
                                        <div style="padding: 10px;">
                                            {{ $manualHandling->posisi_angkat_beban }}
                                        </div>
                                    </td>

                                    <td style="border: 1px solid black; padding: 5px; vertical-align: center; font-weight: bold; text-align: center; width: 25%;">
                                        <div style="padding: 5px; margin-top:3px; font-weight: bold; text-align: center; width: 100%; box-sizing: border-box;">
                                            Berat Beban
                                        </div>
                                        <hr style="border: 1px solid black;">
                                        <div style="padding: 10px; text-align: center; font-weight: bold;">
                                            {{-- Membersihkan kata 'Berat benda' agar sisa angkanya saja --}}
                                            {{ trim(str_ireplace(['Berat benda', 'Sekitar'], '', $manualHandling->estimasi_berat_benda)) }}
                                        </div>
                                    </td>

                                    <td style="border: 1px solid black; padding: 5px; vertical-align: center; font-weight: bold; text-align: center; width: 10%;">
                                        <div style="padding: 5px; margin-top:3px; background-color: #f2f2f2; font-weight: bold; text-align: center; width: 100%; box-sizing: border-box;">
                                            Skor
                                        </div>
                                        <hr style="border: 1px solid black;">
                                        <div style="padding: 10px; text-align: center; font-weight: bold;">
                                            {{ ($hasilResikoBeban != null) ? $hasilResikoBeban['poin'] : 0 }}
                                        </div>
                                    </td>
                                </tr>
                            @endif
                            {{-- 1. HITUNG TOTAL DATA DI AWAL --}}
                            @php
                                // Ratakan array (flatten) lalu hitung total itemnya
                                $totalData = collect($faktorResiko)->flatten(1)->count();
                                
                                // Tambah 1 karena baris pertama ini (Header) juga ikut dihitung dalam rowspan
                                $totalRowspan = $totalData + 1;
                                $totalSkorLangkah =0;
                            
                            @endphp
                            <tr>
                                <td rowspan={{$totalRowspan}} style="border: 1px solid black; padding: 10px; font-weight: bold; text-align: center;">
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
                            @if(!empty($faktorResiko))
                                @foreach($faktorResiko as $kategori => $listItem)
                                    {{-- Loop kedua untuk membuka index 0, 1, 2, dst --}}
                                    @foreach($listItem as $item)
                                        <tr>
                                            <td style="border: 1px solid black; padding: 5px;">
                                                {{-- Gunakan $item, bukan $value --}}
                                                {{ $nomorUrut++ }}. &nbsp; {{ $item['raw_text'] }}
                                            </td>
                                            <td style="border: 1px solid black; padding: 5px; text-align: center;">
                                                {{ $item['keterangan'] }}
                                            </td>
                                            <td style="border: 1px solid black; padding: 5px; text-align: center;">
                                                {{ $item['skor'] }}
                                            </td>
                                        </tr>
                                        @php
                                            $totalSkorLangkah += $item['skor'];
                                        @endphp
                                    @endforeach
                                @endforeach
                            @endif
                            <tr>
                                <td colspan="3" style="border: 1px solid black; padding: 10px; text-align: center; font-weight: bold;">
                                    Skor Langkah Akhir
                                </td>
                                <td style="border: 1px solid black; padding: 10px; text-align: center; font-weight: bold;">
                                    
                                    {{$totalSkorLangkah + ($hasilResikoBeban != null) ? $hasilResikoBeban['poin'] : 0}}
                                    @php $skorLangkahAkhir = $totalSkorLangkah + ($hasilResikoBeban != null) ? $hasilResikoBeban['poin'] : 0; @endphp
                                </td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="sectionP">
                <table style="border-collapse: collapse; width: 100%; border: 2px solid black; font-size: 13px; margin-bottom: 20px;">
                    <thead>
                        <tr style="background-color: #ffffff;">
                            <td colspan="3" style="border: 1px solid black; padding: 5px; font-weight: bold; text-decoration: underline;">
                                IV. Rekapitulasi Hasil Pengukuran Potensi Bahaya
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
                                {{$totalSkorIdanII + $skorLangkahAkhir}}
                                @php $totalSkorAkhir =$totalSkorIdanII + $skorLangkahAkhir @endphp
                            </td>
                        </tr>
                    </tbody>
                </table>
                @php
                    
                    $grandTotal = $totalSkorAkhir;


                    // --- 2. TENTUKAN INTERPRETASI (Berdasarkan Tabel Gambar) ---
                    // Referensi: Gambar tabel interpretasi skor
                    
                    $skorBulat = round($grandTotal); // Bulatkan angka biar pas dengan kategori

                    // Default Value (Jaga-jaga)
                    $pesan   = '-';
                    $warna   = 'black';
                    $bgWarna = 'white';

                    // Logika IF-ELSE Sesuai Tabel
                    if ($skorBulat >= 7) {
                        // SKOR >= 7 : BERBAHAYA
                        $pesan   = 'Berbahaya';
                        $warna   = 'red';
                        $bgWarna = '#ffebee'; // Merah muda pudar
                    } 
                    elseif ($skorBulat >= 3) {
                        // SKOR 3 - 6 : PERLU PENGAMATAN
                        $pesan   = 'Perlu pengamatan lebih lanjut';
                        $warna   = '#ff8f00'; // Oranye gelap
                        $bgWarna = '#fff8e1'; // Kuning pudar
                    } 
                    else {
                        // SKOR <= 2 : AMAN
                        $pesan   = 'Kondisi tempat kerja aman';
                        $warna   = 'green';
                        $bgWarna = '#e8f5e9'; // Hijau pudar
                    }
                @endphp
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
                                dapat disimpulkan bahwa Rekapitulasi Hasil Pengukuran Potensi Bahaya memiliki hasil interpretasi tingkat risiko :
                                <b>{{$pesan}}</b>
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
                <!-- informasi sampling -->
            <div class="sectionP">
                <div class="section-titleP">Informasi Pelanggan</div>
                <table class="info-table">
                    <tr>
                        <td style="width:25%">Nama Pelanggan</td>
                        <td style="width:3%">:</td>
                        <td style="width:72%;text-align:start;">{{ strtoupper($personal->nama_pelanggan) }}</td>
                    </tr>
                    <tr>
                        <td style="width:25%" >Alamat / Lokasi Sampling</td>
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
                        <!-- <tr>
                            <td style="width:25%">Jenis Pekerjaan</td>
                            <td style="width:3%">:</td>
                            <td style="width: 72%;text-align:start;">{{$personal->aktivitas_ukur}}</td>
                        </tr> -->
                        <tr>
                            <td style="width:25%">Lama Bekerja</td>
                            <td style="width:3%">:</td>
                            <td style="width: 72%;text-align:start;">{{ $personal->lama_kerja }} Tahun</td>
                        </tr>
                    </table>
            </div>
            <!-- aktivitas -->
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
                        {{-- Loop data, tapi kalau kosong otomatis lari ke @empty --}}
                        @forelse($personal->aktifitas_k3->uraian as $item)
                            <tr>
                                <td style="text-align: center;">{{ $loop->iteration }}</td>
                                <td>
                                    {{ $item->Uraian }}
                                </td>
                                <td>
                                        {{ $item->jam }} Jam : {{ $item->menit }} Menit
                                </td>
                            </tr>
                        @empty
                            {{-- Bagian ini jalan otomatis kalau data kosong --}}
                            <tr>
                                <td class="text-center">1</td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td class="text-center">2</td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endforelse
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
                            <br>**Interpretasi Hasil Pengukuran Daftar Periksa Potensi Bahaya Ergonomi Mengacu kepada Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja Bagian 5.1.
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