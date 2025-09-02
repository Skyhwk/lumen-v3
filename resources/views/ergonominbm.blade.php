<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian (DRAFT)</title>
    @if(!empty($spesifik))
        <style>{!! $spesifik !!}</style>
    @endif
</head>
<body>
    <div class="header">
        <h1>LAPORAN HASIL PENGUJIAN (DRAFT)</h1>
    </div>

    <div class="content-container clearfix">
        <div class="left-section">
            <div class="section-title">A. KELUHAN SISTEM MUSCULOSKETAL</div>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2">NO.</th>
                        <th rowspan="2">BAGIAN</th>
                        <th colspan="2">Terdapat Keluhan</th>
                        <th rowspan="2" colspan="2">PETA BAGIAN TUBUH</th>
                        <th>NO.</th>
                        <th>BAGIAN</th>
                        <th colspan="2">Terdapat Keluhan</th>
                    </tr>
                    <tr>
                        <th>Sebelum</th>
                        <th>Sesudah</th>
                        <th></th>
                        <th></th>
                        <th>Sebelum</th>
                        <th>Sesudah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>0.</td>
                        <td style="text-align: left;">Leher atas</td>
                        <td>{{$pengukuran->sebelum->skor_leher_atas}}</td>
                        <td>{{$pengukuran->setelah->skor_leher_atas}}</td>
                        <td rowspan="12" colspan="2" style="padding:0; vertical-align: top;"> <img
                                src="{{ public_path('dokumen/img_ergo/nbm/anatomi.jpg') }}" alt="Body Map" class="body-map">
                        </td>
                        <td>1.</td>
                        <td style="text-align: left;">Tengkuk</td>
                        <td>{{$pengukuran->sebelum->skor_tengkuk}}</td>
                        <td>{{$pengukuran->setelah->skor_tengkuk}}</td>
                    </tr>
                    <tr>
                        <td>2.</td>
                        <td style="text-align: left;">Bahu kiri</td>
                        <td>{{$pengukuran->sebelum->skor_bahu_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_bahu_kiri}}</td>
                        <td>3.</td>
                        <td style="text-align: left;">Bahu kanan</td>
                        <td>{{$pengukuran->sebelum->skor_bahu_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_bahu_kanan}}</td>
                    </tr>
                    <tr>
                        <td>4.</td>
                        <td style="text-align: left;">Lengan atas kiri</td>
                        <td>{{$pengukuran->sebelum->skor_lengan_atas_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_lengan_atas_kiri}}</td>
                        <td>5.</td>
                        <td style="text-align: left;">Punggung</td>
                        <td>{{$pengukuran->sebelum->skor_lengan_atas_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_lengan_atas_kiri}}</td>
                    </tr>
                    <tr>
                        <td>6.</td>
                        <td style="text-align: left;">Lengan atas kanan</td>
                        <td>{{$pengukuran->sebelum->skor_lengan_atas_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_lengan_atas_kanan}}</td>
                        <td>7.</td>
                        <td style="text-align: left;">Pinggang</td>
                        <td>{{$pengukuran->sebelum->skor_pinggang}}</td>
                        <td>{{$pengukuran->setelah->skor_pinggang}}</td>
                    </tr>
                    <tr>
                        <td>8.</td>
                        <td style="text-align: left;">Pinggul</td>
                        <td>{{$pengukuran->sebelum->skor_pinggul}}</td>
                        <td>{{$pengukuran->setelah->skor_pinggul}}</td>
                        <td>9.</td>
                        <td style="text-align: left;">Pantat</td>
                        <td>{{$pengukuran->sebelum->skor_pantat}}</td>
                        <td>{{$pengukuran->setelah->skor_pantat}}</td>
                    </tr>
                    <tr>
                        <td>10.</td>
                        <td style="text-align: left;">Siku kiri</td>
                        <td>{{$pengukuran->sebelum->skor_siku_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_siku_kiri}}</td>
                        <td>11.</td>
                        <td style="text-align: left;">Siku kanan</td>
                        <td>{{$pengukuran->sebelum->skor_siku_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_siku_kanan}}</td>
                    </tr>
                    <tr>
                        <td>12.</td>
                        <td style="text-align: left;">Lengan bawah kiri</td>
                        <td>{{$pengukuran->sebelum->skor_lengan_bawah_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_lengan_bawah_kiri}}</td>
                        <td>13.</td>
                        <td style="text-align: left;">Lengan bawah kanan</td>
                        <td>{{$pengukuran->sebelum->skor_lengan_bawah_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_lengan_bawah_kanan}}</td>
                    </tr>
                    <tr>
                        <td>14.</td>
                        <td style="text-align: left;">Pergelangan tangan kiri</td>
                        <td>{{$pengukuran->sebelum->skor_pergelangan_tangan_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_pergelangan_tangan_kiri}}</td>
                        <td>15.</td>
                        <td style="text-align: left;">Pergelangan tangan kanan</td>
                        <td>{{$pengukuran->sebelum->skor_pergelangan_tangan_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_pergelangan_tangan_kanan}}</td>
                    </tr>
                    <tr>
                        <td>16.</td>
                        <td style="text-align: left;">Tangan kiri</td>
                        <td>{{$pengukuran->sebelum->skor_tangan_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_tangan_kiri}}</td>
                        <td>17.</td>
                        <td style="text-align: left;">Tangan kanan</td>
                        <td>{{$pengukuran->sebelum->skor_tangan_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_tangan_kanan}}</td>
                    </tr>
                    <tr>
                        <td>18.</td>
                        <td style="text-align: left;">Paha kiri</td>
                        <td>{{$pengukuran->sebelum->skor_paha_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_paha_kiri}}</td>
                        <td>19.</td>
                        <td style="text-align: left;">Paha kanan</td>
                        <td>{{$pengukuran->sebelum->skor_paha_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_paha_kanan}}</td>
                    </tr>
                    <tr>
                        <td>20.</td>
                        <td style="text-align: left;">Lutut kiri</td>
                        <td>{{$pengukuran->sebelum->skor_lutut_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_lutut_kiri}}</td>
                        <td>21.</td>
                        <td style="text-align: left;">Lutut kanan</td>
                        <td>{{$pengukuran->sebelum->skor_lutut_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_lutut_kanan}}</td>
                    </tr>
                    <tr>
                        <td>22.</td>
                        <td style="text-align: left;">Betis kiri</td>
                        <td>{{$pengukuran->sebelum->skor_betis_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_betis_kiri}}</td>
                        <td>23.</td>
                        <td style="text-align: left;">Betis kanan</td>
                        <td>{{$pengukuran->sebelum->skor_betis_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_betis_kanan}}</td>
                    </tr>
                    <tr>
                        <td>24.</td>
                        <td style="text-align: left;">Pergelangan kaki kiri</td>
                        <td>{{$pengukuran->sebelum->skor_pergelangan_kaki_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_pergelangan_kaki_kiri}}</td>
                        <td colspan="2" class="result-header">HASIL AKHIR</td>
                        <td>25.</td>
                        <td style="text-align: left;">Pergelangan kaki kanan</td>
                        <td>{{$pengukuran->sebelum->skor_pergelangan_kaki_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_pergelangan_kaki_kanan}}</td>
                    </tr>
                    <tr>
                        <td>26.</td>
                        <td style="text-align: left;">Kaki kiri</td>
                        <td>{{$pengukuran->sebelum->skor_kaki_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_kaki_kiri}}</td>
                        <td colspan="2" class="nested-table-container">
                            <table class="nested-table">
                                <tr>
                                    <td>SEBELUM</td>
                                    <td>SESUDAH</td>
                                </tr>
                            </table>
                        </td>
                        <td>27.</td>
                        <td style="text-align: left;">Kaki kanan</td>
                        <td>{{$pengukuran->sebelum->skor_kaki_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_kaki_kanan}}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="total-score">TOTAL SKOR KIRI</td>
                        <td>{{$pengukuran->sebelum->skor_kiri}}</td>
                        <td>{{$pengukuran->setelah->skor_kiri}}</td>
                        <td class="nested-table-container">
                            {{$pengukuran->sebelum->total_skor}}
                        </td>
                        <td class="nested-table-container">
                            {{$pengukuran->setelah->total_skor}}
                        </td>
                        <td colspan="2" class="total-score">TOTAL SKOR KANAN</td>
                        <td>{{$pengukuran->sebelum->skor_kanan}}</td>
                        <td>{{$pengukuran->setelah->skor_kanan}}</td>
                    </tr>
                    <tr>
                      <td rowspan="3" colspan="2">KESIMPULAN AKHIR KELUHAN SISTEM MUSCULOSKETAL</td>
                      <td colspan="9" height="40"></td>
                    </tr>
                </tbody>
            </table>

            <div class="section-title" style="margin-top: 15px;">B. KELUHAN SUBJEKTIF</div>
            <table>
                <thead>
                    <tr>
                        <th>JENIS PENGUKURAN</th>
                        <th>TOTAL SKOR</th>
                        <th>TINGKAT RISIKO</th>
                        <th>KATEGORI RISIKO</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: left;">SEBELUM BEKERJA</td>
                        <td>{{$pengukuran->sebelum->total_skor}}</td>
                        <td>{{$pengukuran->sebelum->tingkat_resiko}}</td>
                        <td>{{$pengukuran->sebelum->kategori_risiko}}</td>
                    </tr>
                    <tr>
                        <td style="text-align: left;">SETELAH BEKERJA</td>
                        <td>{{$pengukuran->setelah->total_skor}}</td>
                        <td>{{$pengukuran->setelah->tingkat_resiko}}</td>
                        <td>{{$pengukuran->setelah->kategori_risiko}}</td>
                    </tr>
                    <tr>
                     <td>KESIMPULAN AKHIR KELUHAN SUBJEKTIF</td>
                     <td colspan="3" height="40"></td>
                    </tr>
                </tbody>
            </table>
            <div class="job-description">
                <table>
                        <tr>
                            <th>DESKRIPSI SINGKAT PEKERJAAN PEKERJA</th>
                            <td colspan="2" height="60" style="vertical-align: top; text-align:left;">
                                {{$personal->aktivitas_ukur}}
                            </td>
                        </tr>
                </table>
            </div>
        </div>
        <div class="right-section">
            <div style="margin-top: 30px;">
                <table>
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
                            <td>Lingkungan</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="padding: 5px;">
                    <div class="info-header">Informasi Pelanggan</div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Nama Pelanggan</span>
                        <span>: {{$personal->nama_pelanggan}} </span>
                    </div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Alamat / Lokasi Sampling</span>
                        <span>: {{$personal->alamat_pelanggan}} </span>
                    </div>

                    <div class="info-header">Informasi Sampling</div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Tanggal Sampling</span>
                        <span>: {{$personal->tanggal_sampling}} </span>
                    </div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Periode Analisa</span>
                        <span>: {{$personal->periode_analis}} </span>
                    </div>

                    <div class="info-header">Data Individu/Pekerja yang Diukur</div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Nama Pekerja</span>
                        <span>: {{$personal->nama_pekerja}} </span>
                    </div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Jenis Pekerjaan</span>
                        <span>: </span>
                    </div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Jenis Analisa</span>
                        <span>: Pengumpulan Data (Pengukuran & Skoring)</span>
                    </div>

                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Metode Analisa*</span>
                        <span>: Kuesioner Nordic Body Map</span>
                    </div>

                    <div class="info-header">Informasi Data Individu/Pekerja yang Diukur</div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Nama</span>
                        <span>: {{$personal->nama_pekerja}} </span>
                    </div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Usia</span>
                        <span>: {{$personal->usia}}</span>
                    </div>
                    <div style="margin-bottom: 3px;">
                        <span class="info-label">Lama Bekerja</span>
                        <span>: {{$personal->lama_kerja}}</span>
                    </div>
            </div>
            <div class="risk-table">
                <p>**Tabel Acuan Skor Risiko dan Tindakan Perbaikan</p>
                <table>
                    <thead>
                        <tr>
                            <th>Total Skor Keluhan Individu</th>
                            <th>Tingkat Risiko</th>
                            <th>Kategori Risiko</th>
                            <th>Tindakan Perbaikan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>0-20</td>
                            <td>0</td>
                            <td>Rendah</td>
                            <td style="text-align: left;">Belum diperlukan adanya tindakan perbaikan</td>
                        </tr>
                        <tr>
                            <td>21-41</td>
                            <td>1</td>
                            <td>Sedang</td>
                            <td style="text-align: left;">Mungkin diperlukan tindakan dikemudian hari</td>
                        </tr>
                        <tr>
                            <td>42-62</td>
                            <td>2</td>
                            <td>Tinggi</td>
                            <td style="text-align: left;">Diperlukan tindakan segera</td>
                        </tr>
                        <tr>
                            <td>63-84</td>
                            <td>3</td>
                            <td>Sangat Tinggi</td>
                            <td style="text-align: left;">Diperlukan tindakan menyeluruh sesegera mungkin</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="table-note">* Metode Analisis Mengacu kepada Jenis Metode yang Direkomendasikan Pada Pedoman
                Teknis Penerapan K3 Penjelasan Tambahan Peraturan Menteri Ketenagakerjaan Nomor 5 Tahun 2018.</p>
            <p class="table-note">**Tabel Acuan Skor Risiko Mengacu kepada Evaluation of Human Work 3<sup>rd</sup>
                Edition Chapter 16 : Static Muscle Loading and The Evaluation of Posture by E. Nigel Corlett, 1992.
            </p>
        </div>
    </div>
</body>
</html>