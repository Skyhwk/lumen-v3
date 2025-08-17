<table class="body" width="100%" style="vertical-align: top;">
    <tr>
        <th colspan="4" style="text-align:left; font-family:Arial, sans-serif; font-size:x-small;">
            PT INTI SURYA LABORATORIUM
        </th>
    </tr>
    <tr class="mt-2 mb-4">
        <th colspan="4" style="text-align:center; font-family:Arial, sans-serif; font-size:large;">
            <u>
                DRAFT - LAPORAN HASIL PENGUJIAN
            </u>
        </th>
    </tr>
    <tr>
        <td width="25%" style="padding-right: 5px;">
            <table class="skor-a" border="1" style="border-collapse: collapse; text-align: center;">
                <tr>
                    <th width="10%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">No.</th>
                    <th width="70%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Jenis Skor A
                    </th>
                    <th width="20%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Nilai
                    </th>
                </tr>
                <tr>
                    <td rowspan="2" width="10%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">1</td>
                    <td height="50px" width="70%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <img src="{{ public_path('dokumen/img_ergo/reba/reba_leher.jpg') }}" alt="Posisi Leher"
                            style="object-fit: contain;">
                    </td>
                    <td rowspan="2" width="20%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->skor_leher }}
                    </td>
                </tr>
                <tr>
                    <td width="70%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <u>Leher</u>
                    </td>
                </tr>
                <tr>
                    <td rowspan="2" width="10%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">2</td>
                    <td height="50px" width="70%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <img src="{{ public_path('dokumen/img_ergo/reba/reba_badan.jpg') }}" alt="Posisi Badan"
                            style="object-fit: contain;">
                    </td>
                    <td rowspan="2" width="20%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->skor_badan }}
                    </td>
                </tr>
                <tr>
                    <td width="70%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <u>Badan</u>
                    </td>
                </tr>
                <tr>
                    <td rowspan="2" width="10%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">3</td>
                    <td height="50px" width="70%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <img src="{{ public_path('dokumen/img_ergo/reba/reba_kaki.jpg') }}" alt="Posisi Kaki"
                            style="object-fit: contain;">
                    </td>
                    <td rowspan="2" width="20%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->skor_kaki }}
                    </td>
                </tr>
                <tr>
                    <td width="70%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <u>Kaki</u>
                    </td>
                </tr>
                <tr>
                    <td rowspan="2" width="10%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">4</td>
                    <td height="50px" width="70%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <img src="{{ public_path('dokumen/img_ergo/reba/reba_skor_beban.jpg') }}" alt="Skor Beban"
                            style="object-fit: contain;" width="17%" height="45px">
                    </td>
                    <td rowspan="2" width="20%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->skor_beban }}
                    </td>
                </tr>
                <tr>
                    <td width="70%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <u>Skor Beban</u>
                    </td>
                </tr>
                <tr>
                    <td colspan="3"
                        style="text-align: left; font-family:Arial, sans-serif; font-size:x-small; border-left:0; border-right: 0; border-bottom:0;">
                        &nbsp;
                    </td>
                </tr>
                <tr>
                    <th height="50px" colspan="3"
                        style="padding: 2px; text-align: left; font-family:Arial, sans-serif; font-size:x-small; border-left:0; border-right: 0; border-bottom:0; border-top: 0; vertical-align: bottom;">
                        <u>Korelasi Nilai dengan Tabel Acuan</u>
                    </th>
                </tr>
            </table>
        </td>
        <td width="25%" style="padding-right: 5px;">
            <table class="skor-b" border="1" style="border-collapse: collapse; text-align: center;">
                <tr>
                    <th width="10%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">No.</th>
                    <th width="70%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Jenis Skor B
                    </th>
                    <th width="20%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Nilai
                    </th>
                </tr>
                <tr>
                    <td rowspan="2" width="10%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">5
                    </td>
                    <td height="50px" width="70%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <img src="{{ public_path('dokumen/img_ergo/reba/reba_lengan_atas.jpg') }}"
                            alt="Posisi Lengan Atas" style="object-fit: contain;">
                    </td>
                    <td rowspan="2" width="20%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->skor_lengan_atas }}
                    </td>
                </tr>
                <tr>
                    <td width="70%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <u>Lengan Atas</u>
                    </td>
                </tr>
                <tr>
                    <td rowspan="2" width="10%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">6
                    </td>
                    <td height="50px" width="70%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <img src="{{ public_path('dokumen/img_ergo/reba/reba_lengan_bawah.jpg') }}"
                            alt="Posisi Lengan Bawah" style="object-fit: contain;" width="15%" height="45px">
                    </td>
                    <td rowspan="2" width="20%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->skor_lengan_bawah }}
                    </td>
                </tr>
                <tr>
                    <td width="70%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <u>Lengan Bawah</u>
                    </td>
                </tr>
                <tr>
                    <td rowspan="2" width="10%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">7
                    </td>
                    <td height="50px" width="70%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <img src="{{ public_path('dokumen/img_ergo/reba/reba_pergelangan_tangan.jpg') }}"
                            alt="Posisi Pergelangan Tangan" style="object-fit: contain;" width="15%"
                            height="45px">
                    </td>
                    <td rowspan="2" width="20%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->skor_pergelangan_tangan }}
                    </td>
                </tr>
                <tr>
                    <td width="70%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <u>Pergelangan Tangan</u>
                    </td>
                </tr>
                <tr>
                    <td rowspan="2" width="10%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">8
                    </td>
                    <td height="50px" width="70%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <img src="{{ public_path('dokumen/img_ergo/reba/reba_kondisi_pegangan.jpg') }}"
                            alt="Kondisi Pegangan" style="object-fit: contain;" width="15%" height="45px">
                    </td>
                    <td rowspan="2" width="20%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->skor_pegangan }}
                    </td>
                </tr>
                <tr>
                    <td width="70%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <u>Kondisi Pegangan</u>
                    </td>
                </tr>
                <tr>
                    <td rowspan="2" width="10%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">9</td>
                    <td height="50px" width="70%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <img src="{{ public_path('dokumen/img_ergo/reba/reba_aktivitas_otot.jpg') }}"
                            alt="Aktivitas Otot" style="object-fit: contain;" width="15%" height="45px">
                    </td>
                    <td rowspan="2" width="20%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->skor_aktivitas_otot }}
                    </td>
                </tr>
                <tr>
                    <td width="70%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        <u>Aktivitas Otot</u>
                    </td>
                </tr>
            </table>
        </td>
        <td colspan="2" style="padding-left: 5px; vertical-align: top;">
            <table class="lhp" width="100%" border="1"
                style="border-collapse: collapse; text-align: center;">
                <tr>
                    <th width="30%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        No. LHP
                    </th>
                    <th width="30%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        No. Sampel
                    </th>
                    <th width="40%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Jenis Sampel
                    </th>
                </tr>
                <tr>
                    <td width="30%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $personal->no_lhp }}
                    </td>
                    <td width="30%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $personal->no_sampel }}
                    </td>
                    <td width="40%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $personal->jenis_sampel }}
                    </td>
                </tr>
            </table>
            <table class="informasi-pelanggan" width="100%" border="0"
                style="border-collapse: collapse; text-align: left; margin-top: 10px;">
                <tr>
                    <th colspan="3" style="font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        <u>Informasi Pelanggan</u>
                    </th>
                </tr>
                <tr>
                    <td width="20%" style="font-family:Arial, sans-serif; font-size:x-small;">Nama
                        Pelanggan
                    </td>
                    <td width="5%" style="font-family:Arial, sans-serif; font-size:x-small;">:</td>
                    <td width="75%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        {{ strtoupper($personal->nama_pelanggan) }}
                    </td>
                </tr>
                <tr>
                    <td width="20%" style="font-family:Arial, sans-serif; font-size:x-small; vertical-align: top;">
                        Alamat / Lokasi Sampling
                    </td>
                    <td width="5%" style="font-family:Arial, sans-serif; font-size:x-small; vertical-align: top;">
                        :</td>
                    <td width="75%" style="font-family:Arial, sans-serif; font-size:x-small; vertical-align: top;">
                        {{ $personal->alamat_pelanggan }}
                    </td>
                </tr>
            </table>
            <table class="informasi-sampling" width="100%" border="0"
                style="border-collapse: collapse; text-align: left; margin-top: 10px;">
                <tr>
                    <th colspan="3" style="font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        <u>Informasi sampling</u>
                    </th>
                </tr>
                <tr>
                    <td width="20%" style="font-family:Arial, sans-serif; font-size:x-small;">Tanggal Sampling
                    </td>
                    <td width="5%" style="font-family:Arial, sans-serif; font-size:x-small;">:</td>
                    <td width="75%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $personal->tanggal_sampling }}
                    </td>
                </tr>
                <tr>
                    <td width="20%" style="font-family:Arial, sans-serif; font-size:x-small;">Periode Analisis
                    </td>
                    <td width="5%" style="font-family:Arial, sans-serif; font-size:x-small;">:</td>
                    <td width="75%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $personal->periode_analisis }}
                    </td>
                </tr>
                <tr>
                    <td width="20%" style="font-family:Arial, sans-serif; font-size:x-small;">Jenis Analisis
                    </td>
                    <td width="5%" style="font-family:Arial, sans-serif; font-size:x-small;">:</td>
                    <td width="75%" style="font-family:Arial, sans-serif; font-size:x-small;">Rapid Asessment (Form
                        Penilaian Cepat)</td>
                </tr>
                <tr>
                    <td width="20%" style="font-family:Arial, sans-serif; font-size:x-small;">Metode Analisis<sup
                            style="font-size: 11px; vertical-align: middle;">*</sup>
                    </td>
                    <td width="5%" style="font-family:Arial, sans-serif; font-size:x-small;">:</td>
                    <td width="75%" style="font-family:Arial, sans-serif; font-size:x-small;">Pengamatan Langsung -
                        Rapid Entire Body Asessment</td>
                </tr>
            </table>
            <table class="informasi-individu" width="100%" border="0"
                style="border-collapse: collapse; text-align: left; margin-top: 10px;">
                <tr>
                    <th colspan="3" style="font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        <u>Data Individu/Pekerja yang Diukur</u>
                    </th>
                </tr>
                <tr>
                    <td width="20%" style="font-family:Arial, sans-serif; font-size:x-small;">Nama
                    </td>
                    <td width="5%" style="font-family:Arial, sans-serif; font-size:x-small;">:</td>
                    <td width="75%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $personal->nama_pekerja }}
                    </td>
                </tr>
                <tr>
                    <td width="20%" style="font-family:Arial, sans-serif; font-size:x-small;">Usia
                    </td>
                    <td width="5%" style="font-family:Arial, sans-serif; font-size:x-small;">:</td>
                    <td width="75%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $personal->usia }} Tahun</td>
                </tr>
                <tr>
                    <td width="20%" style="font-family:Arial, sans-serif; font-size:x-small;">Lama Bekerja
                    </td>
                    <td width="5%" style="font-family:Arial, sans-serif; font-size:x-small;">:</td>
                    <td width="75%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $personal->lama_kerja }}</td>
                </tr>
                <tr>
                    <th height="90px" colspan="3"
                        style="font-family:Arial, sans-serif; font-size:x-small; text-align: left; vertical-align: bottom;">
                        <u>Tabel Acuan Skor Resiko dan Tindakan Perbaikan<sup
                                style="font-size: 11px; vertical-align: middle;">**</sup></u>
                    </th>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td width="25%">
            <table class="korelasi-a" width="100%" border="1"
                style="border-collapse: collapse; text-align: center;">
                <tr>
                    <th width="10%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">No.
                    </th>
                    <th width="70%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Jenis Nilai
                    </th>
                    <th width="20%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Hasil
                    </th>
                </tr>
                <tr>
                    <td width="10%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">1
                    </td>
                    <td width="70%"
                        style="padding-left: 5px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Tabel A
                    </td>
                    <td width="20%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->nilai_tabel_a}}
                    </td>
                </tr>
                <tr>
                    <td width="10%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">2
                    </td>
                    <td width="70%"
                        style="padding-left: 5px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Skor A
                    </td>
                    <td width="20%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->total_skor_a }}
                    </td>
                </tr>
                <tr>
                    <td width="10%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">3
                    </td>
                    <td width="70%"
                        style="padding-left: 5px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Tabel B
                    </td>
                    <td width="20%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->nilai_tabel_b }}
                    </td>
                </tr>
                <tr>
                    <td width="10%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">4
                    </td>
                    <td width="70%"
                        style="padding-left: 5px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Skor B
                    </td>
                    <td width="20%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->total_skor_b }}
                    </td>
                </tr>
                <tr>
                    <td width="10%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">5
                    </td>
                    <td width="70%"
                        style="padding-left: 5px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Tabel C
                    </td>
                    <td width="20%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->nilai_tabel_c }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2" width="20%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; background-color: lightgrey;">
                        <b>Final Skor REBA</b>
                    </td>
                    <td width="20%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->final_skor_reba }}
                    </td>
                </tr>
            </table>
        </td>
        <td width="25%" style="vertical-align: bottom;">
            <table class="korelasi-b" width="100%" border="1"
                style="border-collapse: collapse; text-align: center; vertical-align: bottom;" height="350px">
                {{-- <tr>
                    <td colspan="2" width="55%"
                        style="border:0;padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        <b>&nbsp; </b>
                    </td>
                    <td width="55%" style="border:0;"></td>
                </tr>
                <tr>
                    <td colspan="2" width="55%"
                        style="border:0;padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        <b>&nbsp; </b>
                    </td>
                    <td width="55%" style="border:0;"></td>
                </tr>
                <tr>
                    <td colspan="2" width="55%"
                        style="border:0;padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        <b>&nbsp; </b>
                    </td>
                    <td width="55%" style="border:0;"></td>
                </tr>
                <tr>
                    <td colspan="2" width="55%"
                        style="border:0;padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        <b>&nbsp; </b>
                    </td>
                    <td width="55%" style="border:0;"></td>
                </tr> --}}
                <tr>
                    <td colspan="2" width="55%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; background-color: lightgrey;">
                        <b>Tingkat Risiko</b>
                    </td>
                    <td width="55%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->tingkat_resiko }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2" width="55%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; background-color: lightgrey;">
                        <b>Kategori Risiko</b>
                    </td>
                    <td width="55%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->kategori_resiko }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2" width="55%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; background-color: lightgrey; vertical-align: middle;">
                        <b>Tindakan</b>
                    </td>
                    <td width="55%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        {{ $pengukuran->tindakan }}
                    </td>
                </tr>
            </table>
        </td>
        <td colspan="2" style="padding-left: 5px";>
            <table class="acuan" width="100%" border="1"
                style="border-collapse: collapse; text-align: center;">
                <tr>
                    <td height="45px" width="15%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        <b>SKOR REBA</b>
                    </td>
                    <td height="45px" width="15%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        <b>TINGKAT RISIKO</b>
                    </td>
                    <td height="45px" width="25%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        <b>KATEGORI RISIKO</b>
                    </td>
                    <td height="45px" width="45%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        <b>TINDAKAN</b>
                    </td>
                </tr>
                <tr>
                    <td width="15%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        1
                    </td>
                    <td width="15%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        0
                    </td>
                    <td width="25%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        Sangat Rendah
                    </td>
                    <td width="45%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        Tidak ada tindakan yang diperlukan
                    </td>
                </tr>
                <tr>
                    <td width="15%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        2-3
                    </td>
                    <td width="15%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        1
                    </td>
                    <td width="25%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        Rendah
                    </td>
                    <td width="45%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        Mungkin diperlukan tindakan
                    </td>
                </tr>
                <tr>
                    <td width="15%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        4-7
                    </td>
                    <td width="15%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        2
                    </td>
                    <td width="25%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        Sedang
                    </td>
                    <td width="45%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        Diperlukan tindakan
                    </td>
                </tr>
                <tr>
                    <td width="15%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        8-10
                    </td>
                    <td width="15%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        3
                    </td>
                    <td width="25%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        Tinggi
                    </td>
                    <td width="45%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        Diperlukan tindakan segera
                    </td>
                </tr>
                <tr>
                    <td width="15%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        11-15
                    </td>
                    <td width="15%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        4
                    </td>
                    <td width="25%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        Sangat Tinggi
                    </td>
                    <td width="45%" style="font-family:Arial, sans-serif; font-size:x-small;">
                        Diperlukan tindakan sesegera mungkin
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <table class="kesimpulan" width="100%" border="1"
                style="border-collapse: collapse; text-align: center; margin-top: 10px">
                <tr>
                    <td width="35%" height="75px"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; text-wrap: break-word;">
                        <b>KESIMPULAN AKHIR KONDISI ERGONOMI BERDASARKAN HASIL PENILAIAN CEPAT SELURUH TUBUH (REBA)</b>
                    </td>
                    <td width="65%" height="75px"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: justify; text-wrap: break-word;">
                        {{ $pengukuran->result }}
                    </td>
                </tr>
            </table>
            <table class="deskripsi" width="100%" border="1"
                style="border-collapse: collapse; text-align: center; margin-top: 10px">
                <tr>
                    <td width="35%" height="60px"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; text-wrap: break-word;">
                        <b>DESKRIPSI SINGKAT PEKERJAAN PEKERJA</b>
                    </td>
                    <td width="65%" height="60px"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; text-wrap: break-word;">
                        {{ $personal->deskripsi_pekerjaan }}
                    </td>
                </tr>
            </table>
        </td>
        <td colspan="2">
            <table class="notes" width="100%" border="0"
                style="border-collapse: collapse; text-align: center;">
                <tr>
                    <td width="5%"
                        style="font-family:Arial, sans-serif; font-size:x-small; text-align: right; vertical-align: top;">
                        <sup style="font-size: 11px; vertical-align: middle;">*</sup>
                    </td>
                    <td width="95%" style="font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Metode Analisis Mengacu kepada Jenis Metode yang Direkomendasikan Pada Pedoman Teknis<br>
                        Penerapan K3 Penjelasan Tambahan Menteri Ketenagakerjaan Nomor 5 Tahun 2018.
                    </td>
                </tr>
                <tr>
                    <td width="5%"
                        style="font-family:Arial, sans-serif; font-size:x-small; text-align: right; vertical-align: top;">
                        <sup style="font-size: 11px; vertical-align: middle;">**</sup>
                    </td>
                    <td width="95%" style="font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Tabel Acuan Skor Risiko mengacu kepada Handbook Human Factors and <br>
                        by Neville Stanton et al, 2005.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
