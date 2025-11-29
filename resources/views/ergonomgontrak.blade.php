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
        }
        .col-kiri { width: 155mm; padding-right: 4mm; vertical-align: top; }
        .col-kanan { width: 110mm; vertical-align: top; }

        /* BOX STYLE */
        .section {
            margin-bottom: 5px;
            /* border: 1px solid #000; */
            padding: 3px;
        }
        .section-title {
            font-weight: bold;
            /* text-decoration: underline; */
            margin-bottom: 4px;
            font-size: 8pt;
            /* text-transform: uppercase; */
            /* background-color: #f2f2f2; */
            padding: 2px;
        }

        /* TABEL UMUM (Compact) */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table td { padding: 1px 2px; vertical-align: top; }
        .label-col { width: 40%; }
        .separator-col { width: 2%; text-align: center; }
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
                    <div class="section-title">HASIL ANALISIS SURVEI AWAL GANGGUAN OTOT DAN RANGKA</div>
                    <table class="data-table">
                        <tr>
                            <td class="label-col">1. Tangan Dominan</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">{{ $pengukuran->identitas_umum->tangan_dominan }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">2. Masa Kerja</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">{{ $pengukuran->identitas_umum->masa_kerja }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">3. Kelelahan Mental (Pasca Kerja)</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">{{ $pengukuran->identitas_umum->lelah_mental }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">4. Kelelahan Fisik (Pasca Kerja)</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">{{ $pengukuran->identitas_umum->lelah_fisik }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">5. Nyeri/Sakit (1 Tahun Terakhir)</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">{{ $pengukuran->identitas_umum->rasa_sakit }}</td>
                        </tr>
                    </table>
                    <div style="margin-top: 3px;">
                        <span class="bold">KESIMPULAN SURVEI AWAL:</span>
                        <div class="box-conclusion">Pekerja memiliki risiko bahaya ergonomi</div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">HASIL ANALISIS SURVEI LANJUTAN</div>
                    
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
                                            <th style="width: 45px;">Skor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>1.Leher</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_leher !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_leher->poin : 0 }}</td></tr>
                                        <tr>
                                            <td>2.Bahu</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_bahu !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_bahu->poin : 0 }}</td></tr>
                                        <tr>
                                            <td>3.Punggung Atas</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_punggung_atas !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_punggung_atas->poin : 0 }}</td></tr>
                                        <tr>
                                            <td>4.Lengan</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_lengan !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_lengan->poin : 0 }}</td></tr>
                                        <tr>
                                            <td>5.Siku</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_siku !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_siku->poin : 0 }}</td></tr>
                                        <tr>
                                            <td>6.Punggung Bawah</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_punggung_bawah !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_punggung_bawah->poin : 0 }}</td></tr>
                                        <tr>
                                            <td>7.Tangan</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_tangan !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_tangan->poin : 0 }}</td></tr>
                                        <tr>
                                            <td>8.Pinggul</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_pinggul !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_pinggul->poin : 0 }}</td></tr>
                                        <tr>
                                            <td>9.Paha</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_paha !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_paha->poin : 0 }}</td></tr>
                                        <tr>
                                            <td>10.Lutut</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_lutut !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_lutut->poin : 0 }}</td></tr>
                                        <tr>
                                            <td>11.Betis</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_betis !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_betis->poin : 0 }}</td></tr>
                                        <tr>
                                            <td>12.Kaki</td>
                                            <td class="text-center">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_kaki !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_kaki->poin : 0 }}</td></tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 15px;">
                        <span class="bold">ANALISIS POTENSI BAHAYA:</span>
                        <div class="box-conclusion">
                            {{($personal->aktifitas_k3 != null) ? $personal->aktifitas_k3->analisis_potensi_bahaya : '' }}
                        </div>
                    </div>
                    <div style="margin-top: 15px;">
                        <span class="bold">KESIMPULAN SURVEI LANJUTAN:</span>
                        <div class="box-conclusion">
                            {{($personal->aktifitas_k3 != null) ? $personal->aktifitas_k3->kesimpulan_survey_lanjutan : '' }}
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
                    <div class="section-title">INFORMASI PELANGGAN</div>
                    <table class="data-table">
                        <tr>
                            <td class="label-col" style="width: 25%;">Pelanggan</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">{{ strtoupper($personal->nama_pelanggan) }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">Alamat / Lokasi Sampling</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">{{ $personal->alamat_pelanggan }}</td>
                        </tr>
                    </table>
                </div>

                <div class="section">
                    <div class="section-title">INFORMASI SAMPLING</div>
                    <table class="data-table">
                        <tr>
                            <td class="label-col" style="width: 25%;">Tgl Sampling</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">{{ $personal->tanggal_sampling }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">Jenis Analisis</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">Kuesioner</td>
                        </tr>
                        <tr>
                            <td class="label-col">Metode*</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">Observasi Potensi Bahaya Ergonomi SNI 9011:2021</td>
                        </tr>
                    </table>
                </div>
                <div class="section">
                    <div class="section-title">DATA PEKERJA</div>
                    <table class="data-table">
                        <tr>
                            <td class="label-col" style="width: 25%;">Nama</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">{{ $personal->nama_pekerja }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">Usia</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">{{ $personal->usia }} Tahun</td>
                        </tr>
                        <tr>
                            <td class="label-col">Lama Kerja</td>
                            <td class="separator-col">:</td>
                            <td class="value-col">{{ $personal->lama_kerja }}</td>
                        </tr>
                    </table>
                </div>
                <!-- aktivitas -->
                <div style="height: 15px; clear: both;">&nbsp;</div>
                <div class="section">
                    <table class="bordered-table">
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
                <div style="height: 15px; clear: both;">&nbsp;</div>
                <div class="section">
                    <div class="section-title">TINGKAT RISIKO</div>
                    <table class="bordered-table">
                        <thead>
                            <tr style="background-color: #f9f9f9;">
                                <th style="width: 17%;">Skor</th>
                                <th>Tingkat Risiko</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td class="text-center">1 - 4</td>
                            <td>Rendah</td></tr>
                            <tr><td class="text-center">5 - 7</td>
                            <td>Sedang</td></tr>
                            <tr><td class="text-center">8 - 16</td>
                            <td>Tinggi</td></tr>
                        </tbody>
                    </table>
                    <div style="height: 15px; clear: both;">&nbsp;</div>
                    <div style="font-size: 6pt; margin-top: 15px; font-style: italic; color: #444;">
                        * Metode mengacu SNI 9011:2021<br>
                        ** Klasifikasi mengacu SNI 9011:2021
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
            </td>
        </tr>
    </table>
</body>
</html>