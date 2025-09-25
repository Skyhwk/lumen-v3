<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengujian (DRAFT)</title>
    @if(!empty($cssGlobal))
        <style>{!! $cssGlobal !!}</style>
    @endif

    @if(!empty($spesifik))
        <style>{!! $spesifik !!}</style>
    @endif
</head>
<body>
    <div class="page-container">
        <div class="main-header-title">LAPORAN HASIL PENGUJIAN</div>
        
        <div class="two-column-layout">
            <!-- KOLOM KIRI (65%) -->
            <div class="column column-left">
                <div class="section">
                    <div class="section-title">HASIL ANALISIS SURVEI AWAL GANGGUAN OTOT DAN RANGKA</div>
                    <table class="info-table">
                        <tr>
                            <td class="label-column">1. Tangan Dominan</td>
                            <td><div class="text-input-space">{{ $pengukuran->Identitas_Umum->{"Tangan Dominan"} }}</div></td>
                        </tr>
                        <!-- ... data lainnya ... -->
                    </table>
                    <div class="font-bold">KESIMPULAN SURVEI AWAL</div>
                    <div class="text-input-space" style="min-height: 25px;">
                        Pekerja memiliki risiko bahaya ergonomi
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">HASIL ANALISIS SURVEI LANJUTAN GANGGUAN OTOT DAN RANGKA</div>
                    <div class="image-placeholder-container">
                        <div class="image-placeholder">
                            <img src="{{ public_path(\'dokumen/img_ergo/gotrak/anatomygontrak.png\') }}" 
                                 alt="Body Map" class="body-map">
                        </div>
                        <div class="body-parts-list-container">
                            <table class="body-parts-list">
                                <!-- Body parts data -->
                            </table>
                            
                            <div class="section">
                                <div class="section-title">ANALISIS POTENSI BAHAYA</div>
                                <div class="analysis-content">
                                    <!-- Content -->
                                </div>
                            </div>
                            
                            <div class="section">
                                <div class="section-title">KESIMPULAN SURVEI LANJUTAN</div>
                                <div class="conclusion-content">
                                    <!-- Content -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KOLOM KANAN (35%) -->
            <div class="column column-right">
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
                            <td>{{ $personal->no_lhp }}</td>
                            <td>{{ $personal->no_sampel }}</td>
                            <td>Ergonomi</td>
                        </tr>
                    </tbody>
                </table>

                <div class="section">
                    <div class="font-bold">Informasi Pelanggan</div>
                    <!-- Table content -->
                </div>

                <div class="section">
                    <div class="font-bold">Informasi Sampling</div>
                    <!-- Table content -->
                </div>

                <div class="section">
                    <div class="font-bold">Data Individu/Pekerja yang Diukur</div>
                    <!-- Table content -->
                </div>

                <div class="section">
                    <div class="section-title">Tingkat Risiko Keluhan Gangguan Otot dan Rangka</div>
                    <!-- Reference table -->
                </div>
            </div>
        </div>
        
        <div class="footer-text">
            <span>Generated: {{ date("d/m/Y H:i") }}</span>
            <span>Page 1 of 1</span>
        </div>
    </div>
</body>
</html>
