<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian</title>

    @if(!empty($spesifik))
        <style>{!! $spesifik !!}</style>
    @endif
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
                            <td> <div class="text-input-space">{{ $pengukuran->identitas_umum->tangan_dominan }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-column">2. Mata Kerja</td>
                            <td> <div class="text-input-space">{{ $pengukuran->identitas_umum->masa_kerja }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-column">3. Merasakan Kelelahan Mental Setelah Bekerja</td>
                            <td> <div class="text-input-space">{{ $pengukuran->identitas_umum->lelah_mental }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-column">4. Merasakan Kelelahan Fisik Setelah Bekerja</td>
                            <td> <div class="text-input-space">{{ $pengukuran->identitas_umum->lelah_fisik }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-column">5. Merasakan Ketidaknyamanan/Nyeri/Sakit Dalam Satu Tahun Terakhir
                            </td>
                            <td> <div class="text-input-space">{{ $pengukuran->identitas_umum->rasa_sakit }}</div>
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
                            <img src="{{ public_path('dokumen/img_ergo/gotrak/anatomygontrak.png') }}"
                            style="width: 160px; height: auto; object-fit: contain; image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges; display: block; margin: 0 auto;"
                            alt="Body Map"/>
                        </div>
                        <div class="body-parts-list-container">
                            <table class="body-parts-list">
                                <tr>
                                    <td><span>1 = Leher</span></td>
                                    <td>
                                        <div class="input-line">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_leher !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_leher->poin : 0 }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><span>2 = Bahu</span></td>
                                    <td>
                                        <div class="input-line">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_bahu !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_bahu->poin : 0 }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><span>3 = Punggung Atas</span></td>
                                    <td>
                                        <div class="input-line">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_punggung_atas !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_punggung_atas->poin : 0 }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><span>4 = Lengan</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_lengan !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_lengan->poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>5 = Siku</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_siku !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_siku->poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>6 = Punggung Bawah</span></td>
                                    <td><div class="input-line">{{( $pengukuran->keluhan_bagian_tubuh->sakit_punggung_bawah !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_punggung_bawah->poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>7 = Tangan</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_tangan !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_tangan->poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>8 = Pinggul</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_pinggul !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_pinggul->poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>9 = Paha</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_paha !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_paha->poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>10 = Lutut</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_lutut !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_lutut->poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>11 = Betis</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_betis !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_betis->poin : 0 }}</div></td>
                                </tr>
                                <tr>
                                    <td><span>12 = Kaki</span></td>
                                    <td><div class="input-line">{{ ($pengukuran->keluhan_bagian_tubuh->sakit_kaki !== 'Tidak') ? $pengukuran->keluhan_bagian_tubuh->sakit_kaki->poin : 0 }}</div></td>
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
                            <td>ERGONOMI</td>
                        </tr>
                    </tbody>
                </table>

                <div class="section">
                    <span class="bold">Informasi Pelanggan</span>
                    <table class="info-table">
                       <tr>
                            <td style="width: 25%; text-align:start;">Nama Pelanggan</td>
                            <td style="width: 3%; text-align:start;">:</td>
                            <td style="width: 72%; text-align:start;">{{ strtoupper($personal->nama_pelanggan) }}</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Alamat / Lokasi Sampling</td>
                            <td style="width: 3%; text-align:start;">:</td>
                            <td style="width: 72%; text-align:start;">{{ $personal->alamat_pelanggan }}</td>
                        </tr>
                    </table>
                </div>
                <div class="section">
                    <span class="bold">Informasi Sampling</span>
                    <table class="info-table">
                        <!-- <tr>
                            <td style="width: 120px;">Metode Sampling</td>
                            <td> SNI 9011:2021</td>
                        </tr> -->
                        <tr>
                            <td style="width: 25%; text-align:start;">Tanggal Sampling</td>
                            <td style="width: 3%;">:</td>
                            <td style="width: 72%; text-align:start;">{{ $personal->tanggal_sampling }}</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Jenis Analisis</td>
                            <td style="width: 3%;">:</td>
                            <td style="width: 72%; text-align:start;">Kuesioner</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Metode Analisis*</td>
                            <td style="width: 3%;">:</td>
                            <td style="width: 72%; text-align:start;">Identifikasi Keluhan Gangguan Otot dan Rangka</td>
                        </tr>
                    </table>
                </div>

                <div class="section">
                    <span class="bold">Data Individu/Pekerja yang Diukur</span>
                    <table class="info-table">
                        <tr>
                            <td style="width: 25%; text-align:start;">Nama</td>
                            <td style="width: 3% ;text-align:start;">:</td>
                            <td style="width: 72%; text-align:start;">{{ $personal->nama_pekerja }}</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Usia</td>
                            <td style="width: 3% ;text-align:start;">:</td>
                            <td style="width: 72%; text-align:start;">{{ $personal->usia }} Tahun</td>
                        </tr>
                        <tr>
                            <td style="width: 25%; text-align:start;">Lama Bekerja</td>
                            <td style="width: 3% ;text-align:start;">:</td>
                            <td style="width: 72%; text-align:start;">{{ $personal->lama_kerja }}</td>
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
                                               Tangerang, {{ $ttd->tanggal }}
                                            </div><br>
                                            <div class="signature-text">
                                                    <img src="{{ $ttd->qr_path }}" width="50" height="50" alt="ttd">
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