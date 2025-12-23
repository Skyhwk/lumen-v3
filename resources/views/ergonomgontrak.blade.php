<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian</title>
    <style>
        /* RESET & PAGE SETUP */
        * { box-sizing: border-box; }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.1;
            color: #000;
            margin: 0; padding: 0;
        }

        /* UTILITY */
        .bold { font-weight: bold; }
        .text-center { text-align: center; }
        .valign-top { vertical-align: top; }

        /* LAYOUT UTAMA */
        .main-layout-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            table-layout: fixed;
            border: none !important;
        }
        .col-kiri { width: 155mm; padding-right: 4mm; vertical-align: top; border: none !important; }
        .col-kanan { width: 110mm; vertical-align: top; border: none !important; }

        /* BOX STYLE */
        .section {
            margin-bottom: 5px;
            /* border: 1px solid #000; */
            padding: 3px;
            border: none !important;
        }
        .section-title {
            font-weight: bold;
            /* text-decoration: underline; */
            margin-bottom: 4px;
            font-size: 8pt;
            /* text-transform: uppercase; */
            /* background-color: #f2f2f2; */
            padding: 2px;
            text-align: left !important;
        }

        /* TABEL UMUM (Compact) */
        .data-table { width: 100%; border-collapse: collapse; border: none !important; text-align: left !important; }
        .data-table td { padding: 1px 2px; vertical-align: top; border: none !important; text-align: left !important;}
        .label-col { width: 40%; }
        .separator-col { width: 3%; text-align: center; }
        .value-col { width: 58%; }

        /* TABEL BORDERED (Compact Default) */
        .bordered-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }
        .bordered-table th, .bordered-table td {
            border: 1px solid #000;
            padding: 1px 2px;
            font-size: 8pt;
        }

        /* --- PERBAIKAN KHUSUS BODY MAP (DIBESARKAN) --- */
        .body-map-container { width: 100%; border-collapse: collapse; }
        
        /* 1. Area Gambar Diperlebar */
        .body-map-img-cell {
            width: 200px; /* Ukuran diperbesar agar tidak pecah */
            vertical-align: middle; /* Posisi vertikal tengah agar rapi */
            text-align: center;
            padding-right: 10px;
        }

        /* 2. Tabel Bagian Tubuh Dibuat Lebih Renggang (Spacious) */
        .spacious-table th, .spacious-table td {
            border: 1px solid #000;
            padding: 5px 4px; /* Padding diperbesar agar tabel lebih tinggi menyamai gambar */
            font-size: 9pt;   /* Font sedikit dibesarkan agar seimbang */
        }

        /* Header LHP & Lainnya */
        .lhp-header { width: 100%; border-collapse: collapse; margin-bottom: 5px;}
        .lhp-header th, .lhp-header td { border: 1px solid #000; padding: 2px; text-align: center; font-size: 12px; }
        .lhp-header th { background-color: #e0e0e0; font-weight: bold; }

        
        .box-conclusion {
            border: 1px solid #000;
            padding: 3px;
            min-height: 20px;
            font-size: 8pt;
            background-color: #fff;
            margin-top: 2px;
        }
        .main-title {
            text-align: center; font-weight: bold; font-size: 11pt;
            margin-bottom: 8px; text-decoration: underline; text-transform: uppercase;
        }
        .signature-box { width: 50mm;        /* Tentukan lebar agar tidak memenuhi baris */
    float: right;       /* Paksa elemen mengapung ke sisi KANAN */
    text-align: center; /* Isi teks (QR & Nama) tetap rata tengah didalam kotak ini */
    margin-top: 5px;
    margin-right: 5px; }
    </style>
</head>
<body>
    <table class="main-layout-table">
        <tr>
            <td class="col-kiri">
                
                <div class="section">
                    <div class="section-title">HASIL SURVEI KELUHAN GANGGUAN OTOT DAN RANGKA</div>
                    <table class="data-table">
                        <tr>
                            <td width="70%">1. Tangan Dominan</td>
                            <td width="2%">:</td>
                            <td class="value-col">{{ $pengukuran->identitas_umum->tangan_dominan }}</td>
                        </tr>
                        <tr>
                            <td width="70%">2. Masa Kerja</td>
                            <td width="2%">:</td>
                            <td class="value-col">{{ $pengukuran->identitas_umum->masa_kerja }}</td>
                        </tr>
                        <tr>
                            <td width="70%">3. Kelelahan Mental (Setelah Bekerja)</td>
                            <td width="2%">:</td>
                            <td class="value-col">{{ $pengukuran->identitas_umum->lelah_mental }}</td>
                        </tr>
                        <tr>
                            <td width="70%">4. Kelelahan Fisik (Setelah Bekerja)</td>
                            <td width="2%">:</td>
                            <td class="value-col">{{ $pengukuran->identitas_umum->lelah_fisik }}</td>
                        </tr>
                        <tr>
                            <td width="70%">5. Rasa Sakit/Nyeri/Ketidaknyamanan (1 Tahun Terakhir)</td>
                            <td width="2%">:</td>
                            <td class="value-col">{{ $pengukuran->identitas_umum->rasa_sakit }}</td>
                        </tr>
                    </table>
                </div>

                <div class="section">
                    
                    <table class="body-map-container">
                        <tr>
                            <td class="body-map-img-cell">
                                <img src="{{ public_path('dokumen/img_ergo/gotrak/anatomygontrak.png') }}" 
                                     style="width: 100%; max-width: 190px; height: auto;" alt="Body Map">
                            </td>
                            <td class="valign-top">
                                <table class="bordered-table spacious-table">
                                    <thead>
                                        <tr style="background-color: #f9f9f9;">
                                            <th style="text-align: left;">Bagian Tubuh</th>
                                            <th style="text-align: left;">Sisi Tubuh (Jika Ada Keluhan)</th>
                                            <th style="width: 45px;">Skor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            if (!function_exists('formatBagianTubuh')) {
                                                function formatBagianTubuh($data)
                                                {
                                                    if (empty($data)) {
                                                        return '-';
                                                    }

                                                    if ($data === 'Kedua' || $data === 'kedua') {
                                                        return 'Keduanya';
                                                    }

                                                    // Selain itu → tampilkan apa adanya
                                                    return $data;
                                                }
                                            }
                                        @endphp
                                        <tr>
                                            <td style="text-align: left;" >1. Leher</td>
                                            <td class="text-center">{{ formatBagianTubuh($pengukuran->keluhan_bagian_tubuh->sakit_leher->bagian_tubuh ?? null) }}</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_leher !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_leher->poin : 1 }}</td></tr>
                                        <tr>
                                            <td style="text-align: left;" >2. Bahu</td>
                                            <td class="text-center">{{ formatBagianTubuh($pengukuran->keluhan_bagian_tubuh->sakit_bahu->bagian_tubuh ?? null) }}</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_bahu !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_bahu->poin : 1 }}</td></tr>
                                        <tr>
                                            <td style="text-align: left;" >3. Punggung Atas</td>
                                            <td class="text-center">{{ formatBagianTubuh($pengukuran->keluhan_bagian_tubuh->sakit_punggung_atas->bagian_tubuh ?? null) }}</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_punggung_atas !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_punggung_atas->poin : 1 }}</td></tr>
                                        <tr>
                                            <td style="text-align: left;" >4. Lengan</td>
                                            <td class="text-center">{{ formatBagianTubuh($pengukuran->keluhan_bagian_tubuh->sakit_lengan->bagian_tubuh ?? null) }}</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_lengan !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_lengan->poin : 1 }}</td></tr>
                                        <tr>
                                            <td style="text-align: left;" >5. Siku</td>
                                            <td class="text-center">{{ formatBagianTubuh($pengukuran->keluhan_bagian_tubuh->sakit_siku->bagian_tubuh ?? null) }}</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_siku !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_siku->poin : 1 }}</td></tr>
                                        <tr>
                                            <td style="text-align: left;" >6. Punggung Bawah</td>
                                            <td class="text-center">{{ formatBagianTubuh($pengukuran->keluhan_bagian_tubuh->sakit_punggung_bawah->bagian_tubuh ?? null) }}</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_punggung_bawah !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_punggung_bawah->poin : 1 }}</td></tr>
                                        <tr>
                                            <td style="text-align: left;" >7. Tangan</td>
                                            <td class="text-center">{{ formatBagianTubuh($pengukuran->keluhan_bagian_tubuh->sakit_tangan->bagian_tubuh ?? null) }}</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_tangan !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_tangan->poin : 1 }}</td></tr>
                                        <tr>
                                            <td style="text-align: left;" >8. Pinggul</td>
                                            <td class="text-center">{{ formatBagianTubuh($pengukuran->keluhan_bagian_tubuh->sakit_pinggul->bagian_tubuh ?? null) }}</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_pinggul !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_pinggul->poin : 1 }}</td></tr>
                                        <tr>
                                            <td style="text-align: left;" >9. Paha</td>
                                            <td class="text-center">{{ formatBagianTubuh($pengukuran->keluhan_bagian_tubuh->sakit_paha->bagian_tubuh ?? null) }}</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_paha !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_paha->poin : 1 }}</td></tr>
                                        <tr>
                                            <td style="text-align: left;" >10. Lutut</td>
                                            <td class="text-center">{{ formatBagianTubuh($pengukuran->keluhan_bagian_tubuh->sakit_lutut->bagian_tubuh ?? null) }}</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_lutut !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_lutut->poin : 1 }}</td></tr>
                                        <tr>
                                            <td style="text-align: left;" >11. Betis</td>
                                            <td class="text-center">{{ formatBagianTubuh($pengukuran->keluhan_bagian_tubuh->sakit_betis->bagian_tubuh ?? null) }}</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_betis !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_betis->poin : 1 }}</td></tr>
                                        <tr>
                                            <td style="text-align: left;" >12. Kaki</td>
                                            <td class="text-center">{{ formatBagianTubuh($pengukuran->keluhan_bagian_tubuh->sakit_kaki->bagian_tubuh ?? null) }}</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_kaki !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_kaki->poin : 1 }}</td></tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </table>
                    @php
                    $keluhanData = [
                        ["Leher", $pengukuran->keluhan_bagian_tubuh->sakit_leher],
                        ["Bahu", $pengukuran->keluhan_bagian_tubuh->sakit_bahu],
                        ["Punggung Atas", $pengukuran->keluhan_bagian_tubuh->sakit_punggung_atas],
                        ["Lengan", $pengukuran->keluhan_bagian_tubuh->sakit_lengan],
                        ["Siku", $pengukuran->keluhan_bagian_tubuh->sakit_siku],
                        ["Punggung Bawah", $pengukuran->keluhan_bagian_tubuh->sakit_punggung_bawah],
                        ["Tangan", $pengukuran->keluhan_bagian_tubuh->sakit_tangan],
                        ["Pinggul", $pengukuran->keluhan_bagian_tubuh->sakit_pinggul],
                        ["Paha", $pengukuran->keluhan_bagian_tubuh->sakit_paha],
                        ["Lutut", $pengukuran->keluhan_bagian_tubuh->sakit_lutut],
                        ["Betis", $pengukuran->keluhan_bagian_tubuh->sakit_betis],
                        ["Kaki", $pengukuran->keluhan_bagian_tubuh->sakit_kaki],
                    ];

                    $higherNumber = 0;
                    $returnKeluhan = [];

                    foreach ($keluhanData as $item) {
                        if (!is_array($item) || count($item) < 2) {
                            continue;
                        }

                        [$label, $value] = $item;

                        if (!$value || is_string($value)) {
                            continue;
                        }

                        if (is_array($value) || is_object($value)) {
                            $poin = isset($value->Poin) ? intval($value->Poin) : 0;

                            if ($poin === $higherNumber) {
                                $returnKeluhan[] = $label;
                            } elseif ($poin > $higherNumber) {
                                $higherNumber = $poin;
                                $returnKeluhan = [$label];
                            }
                        }
                    }

                    @endphp
                    <div style="margin-top: 15px;">
                        <table class="info-table">
                                <tr>
                                    <td style="text-align: left !important;"><span class="bold">KESIMPULAN SURVEI KELUHAN GANGGUAN OTOT DAN RANGKA</span></td>
                                </tr>
                                <tr>
                                    <td style="text-align: justify !important;">
                                        Berdasarkan hasil survei keluhan gangguan otot dan rangka yang telah dilakukan, 
                                        didapatkan bahwa bagian tubuh dengan tingkat keluhan tertinggi adalah: 
                                        <span class="bold">{{ !empty($returnKeluhan) ? implode(', ', $returnKeluhan) : 'Tidak Ada Keluhan' }}</span> 
                                        dengan skor keluhan sebesar <span class="bold">{{ $higherNumber }}</span>.
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </td>

            <td class="col-kanan">
                <table class="lhp-header">
                    <tr><th>No. LHP</th><th>No. Sampel</th><th>Jenis Sampel</th></tr>
                    <tr><td>{{ $personal->no_lhp }}</td>
                    <td>{{ $personal->no_sampel }}</td>
                    <td>ERGONOMI</td></tr>
                </table>

                <div class="section">
                    
                    <table class="info-table">
                        <tr>
                            <td style="width: 40%; text-align: left !important;"><div class="section-title">Informasi Pelanggan</div></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td style="width: 40%; text-align: left !important;">Nama Pelanggan</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start; ">{{ strtoupper($personal->nama_pelanggan) }}</td>
                        </tr>
                        <tr>
                            <td style="width: 40%; text-align: left !important;">Alamat / Lokasi Sampling</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">{{ $personal->alamat_pelanggan }}</td>
                        </tr>
                    </table>
                </div>

                <div class="section">
                    
                    <table class="info-table" width="100%">
                        <tr>
                            <td style="width: 40%; text-align: left !important; vertical-align: top !important;" ><div class="section-title">Informasi Sampling</div></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td style="width: 40%; text-align: left !important; vertical-align: top !important;">Tanggal Sampling</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">{{ $personal->tanggal_sampling }}</td>
                        </tr>
                        <tr>
                            <td style="width: 40%; text-align: left !important; vertical-align: top !important;">Metode Sampling*</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">SNI 9011:2021</td>
                        </tr>
                    </table>
                </div>

                <div class="section">
                    
                    <table class="info-table" width="100%">
                        <tr>
                            <td style="width: 40%; text-align: left !important;"><div class="section-title">Data Individu / Pekerja yang diukur</div></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td style="width: 40%; text-align: left !important; vertical-align: top !important;">Nama</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">{{ $personal->nama_pekerja }}</td>
                        </tr>
                        <tr>
                            <td style="width: 40%; text-align: left !important; vertical-align: top !important;">Posisi / Jabatan</td>
                            <td style="width: 3%;">:</td>
                            <td style="text-align:start;">{{ $personal->jabatan }}</td>
                        </tr>
                    </table>
                </div>
                <!-- aktivitas -->
                <div style="height: 15px; clear: both;">&nbsp;</div>
                <div class="section">
                    <table class="bordered-table text-center" >
                        <thead>
                            <tr style="background-color: #f9f9f9;">
                                <th style="width: 10%;">No</th>
                                <th style="width: 60%;">Uraian Tugas</th>
                                <th style="width: 30%;">Durasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Loop data, tapi kalau kosong otomatis lari ke @empty --}}
                        @forelse($personal->aktifitas_k3->uraian as $item)
                            <tr>
                                <td style="text-align: center;">{{ $loop->iteration }}</td>
                                <td style="text-align: left;">
                                    {{ $item->Uraian }}
                                </td>
                                <td style="text-align: left;">
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
                <div style="height: 15px; clear: both;">&nbsp;</div>
                <div class="section">
                    <div class="section-title">Analisis Tingkat Risiko Keluhan GOTRAK*</div>
                    <table class="bordered-table">
                        <thead>
                            <tr style="background-color: #f9f9f9;">
                                <th style="width: 17%;">Skor</th>
                                <th>Tingkat Risiko</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td class="text-center">1 - 4</td>
                            <td class="text-center">Rendah</td></tr>
                            <tr><td class="text-center">6</td>
                            <td class="text-center">Sedang</td></tr>
                            <tr><td class="text-center">8 - 16</td>
                            <td class="text-center">Tinggi</td></tr>
                        </tbody>
                    </table>
                    <div style="height: 15px; clear: both;">&nbsp;</div>
                    <div style="font-size: 6pt; margin-top: 15px; font-style: italic; color: #444;">
                        *Tabel Analisis Tingkat Risiko Keluhan GOTRAK mengacu kepada SNI 9011:2021 tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja
                    </div>

                    <table style="width: 100%; margin-top: 10px; border: none;">
                        <tr>
                            <td style="width: 50%; border: none;"></td>

                            <td style="width: 50%; border: none; text-align: center; vertical-align: top;">
                                
                                <div style="margin-bottom: 5px;">
                                    Tangerang, {{ $ttd->tanggal ?? '13 Agustus 2025' }}
                                </div>

                                @if($ttd && $ttd->qr_path)
                                <br><br><br>
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
            </td>
        </tr>
    </table>
</body>
</html>