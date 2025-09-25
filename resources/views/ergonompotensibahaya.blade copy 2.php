<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian (DRAFT)</title>
     @if(!empty($cssGlobal))
        <style>{!! $cssGlobal !!}</style>
    @endif
</head>
<body>
    <div class="page-container">
        <div class="main-header-title">LAPORAN HASIL PENGUJIAN</div>

        <div class="two-column-layout">
            <!-- KIRI -->
            <div class="column column-left">
                <div class="section">
                    {{-- Bagian Tubuh Atas --}}
                    <div class="section-title">I. Daftar Periksa Potensi Bahaya Tubuh Bagian Atas</div>
                    <table class="table-potensi-bahaya">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Potensi Bahaya</th>
                                <th>Skor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groupedAtas as $kategori => $rows)
                                @foreach($rows as $i => $row)
                                    <tr>
                                        {{-- tampilkan kategori hanya sekali dengan rowspan --}}
                                        @if($i === 0)
                                            <td rowspan="{{ count($rows) }}">{{ $kategori }}</td>
                                        @endif
                                        <td>{{ $row['potensi'] }}</td>
                                        <td>{{ $row['skor'] }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>

                </div>

                <div class="section" style="page-break-before: always;">
                    <div class="section-title">II. Daftar Periksa Potensi Bahaya Tubuh Bagian Bawah</div>
                    <table class="table-potensi-bahaya">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Potensi Bahaya</th>
                                <th>Skor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groupedBawah as $kategori => $rows)
                                @foreach($rows as $i => $row)
                                    <tr>
                                        @if($i === 0)
                                            <td rowspan="{{ count($rows) }}">{{ $kategori }}</td>
                                        @endif
                                        <td>{{ $row['potensi'] }}</td>
                                        <td>{{ $row['skor'] }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                    <table class="total-score-table" style="margin-top: -1px; border-top: 1px solid #000;"> <tr>
                            <td>Total Skor I dan II</td>
                            <td><div class="text-input-space"></div></td>
                        </tr>
                    </table>
                </div>

                <div class="section manual-load-section">
                    <div class="section-title">III. Daftar Periksa Pengamatan Beban Secara Manual</div>
                    <table>
                        <thead><tr><th>Jarak Pengangkatan</th><th>Berat Beban</th><th>Skor</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Skor Langkah 1</td>
                                <td><div class="text-input-space"></div></td>
                                <td><div class="text-input-space"></div></td>
                            </tr>
                        </tbody>
                    </table>
                    <table style="margin-top: -6px;"> <thead><tr><th>Faktor Risiko</th><th>Pengangkatan</th><th>Skor</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Skor Langkah 2</td>
                                <td><div class="text-input-space"></div></td>
                                <td><div class="text-input-space"></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="section">
                     <table class="total-score-table">
                        <tr>
                            <td>Skor Langkah Akhir</td>
                            <td><div class="text-input-space"></div></td>
                        </tr>
                    </table>
                </div>


                <div class="section">
                    <div class="section-title">IV. Rekapitulasi Penilaian Potensi Bahaya</div>
                    <table class="rekap-table">
                        <tr>
                            <td>Total Skor Akhir :</td>
                            <td><div class="text-input-space"></div></td>
                        </tr>
                    </table>
                </div>

                <div class="section">
                    <div class="section-title">V. Kesimpulan</div>
                    <div class="multi-line-input">Berdasarkan hasil pengamatan daftar periksa potensi bahaya ergonomi pada jenis pekerjaan tersebut, dapat disimpulkan bahwa Rekapitulasi Penilaian Potensi Bahaya memiliki hasil interpretasi tingkat resiko : Kondisi tempat kerja aman / Perlu pengamatan lebih lanjut / Berbahaya</div>
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
                            <td></td>
                            <td></td>
                            <td>Ergonomi</td>
                        </tr>
                    </tbody>
                </table>

                <div class="section">
                    <div class="section-title" style="margin-bottom: 3px;">Informasi Pelanggan</div>
                    <table class="info-table">
                        <tr><td style="width: 90px;">Nama Pelanggan</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                        <tr><td>Alamat / Lokasi</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                        <tr><td>Sampling</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                    </table>
                </div>
                <div class="section">
                     <div class="section-title" style="margin-bottom: 3px;">Informasi Sampling</div>
                    <table class="info-table">
                        <tr><td style="width: 90px;">Tanggal Sampling</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                        <tr><td>Periode Analisis</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                        <tr><td>Metode Analisis*</td><td>:</td><td>Observasi Potensi Bahaya Ergonomi SNI 9011:2021</td></tr>
                    </table>
                </div>

                <div class="section">
                    <div class="section-title" style="margin-bottom: 3px;">Data Individu/Pekerja yang Diukur</div>
                    <table class="info-table">
                        <tr><td style="width: 90px;">Nama Pekerja</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                        <tr><td>Posisi/Jabatan</td><td>:</td><td><div class="text-input-space"></div></td></tr>
                    </table>
                </div>

                <div class="section">
                    <table class="uraian-tugas-table">
                        <thead>
                            <tr><th>No.</th><th>Uraian Tugas Singkat</th><th>Waktu/Durasi Kerja Tiap Tugas</th></tr>
                        </thead>
                        <tbody>
                            <tr><td class="centered-text">1</td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                            <tr><td class="centered-text">2</td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                            <tr><td class="centered-text">3</td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                            <tr><td class="centered-text">4</td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                            <tr><td class="centered-text">5</td><td><div class="text-input-space"></div></td><td><div class="text-input-space"></div></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="section">
                     <div class="section-title">Interpretasi Hasil Penilaian Daftar Periksa Potensi Bahaya Ergonomi**</div>
                     <table class="interpretasi-table">
                         <thead><tr><th>Skor</th><th>Interpretasi</th></tr></thead>
                         <tbody>
                             <tr><td>&lt;2</td><td>Kondisi tempat kerja aman</td></tr>
                             <tr><td>3 - 6</td><td>Perlu pengamatan lebih lanjut</td></tr>
                             <tr><td>≥7</td><td>Berbahaya</td></tr>
                         </tbody>
                     </table>
                </div>
                 <div class="notes">
                    * Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja.
                    <br>** Interpretasi Hasil Penilaian Daftar Periksa Potensi Bahaya Ergonomi Mengacu kepada Standar Nasional Indonesia 9011:2021 Tentang Pengukuran dan Evaluasi Potensi Bahaya Ergonomi di Tempat Kerja Bagian 5.1.
                </div>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>
</body>
</html>