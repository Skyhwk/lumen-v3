<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian</title>
   
    @if(!empty($cssGlobal))
        <style>{!! $cssGlobal !!}</style>
    @endif

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

