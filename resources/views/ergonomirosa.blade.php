<table class="body" width="100%" style="vertical-align: top;">
    <tr>
        <th colspan="4" style="text-align:left; font-family:Arial, sans-serif; font-size:x-small;">
            PT INTI SURYA LABORATORIUM
        </th>
    </tr>
    <tr class="mt-2 mb-4">
        <th colspan="4" style="text-align:center; font-family:Arial, sans-serif; font-size:large;">
            <u>
                LAPORAN HASIL PENGUJIAN (DRAFT)
            </u>
        </th>
    </tr>
    <tr>
        <td width="65%">
            <table class="skor-a" width="100%" border="1" style="border-collapse: collapse; margin-right: 10px;">
                <tr>
                    <th width="5%" rowspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        No
                    </th>
                    <th witdth="25%" rowspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Jenis Skoring
                    </th>
                    <th width="70%" colspan="4"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Skor Section A
                    </th>
                </tr>
                <tr>
                    <td colspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Section A
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Durasi
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Total (Section A + Durasi)
                    </td>
                </tr>
                <tr>
                    <td width="3%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        1.
                    </td>
                    <td width="24%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Tinggi Kursi & Lebar Kursi
                    </td>
                    <td width="6%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_total_tinggi_kursi_dan_lebar_dudukan }}
                    </td>
                    <td width="7%" rowspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->nilai_table_a }}
                    </td>
                    <td width="30%" rowspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        &nbsp;
                    </td>
                    <td width="30%" rowspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->total_section_a }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        2.
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Sandaran Lengan & Punggung</td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_total_sandaran_lengan_dan_punggung }}
                    </td>
                </tr>
                <tr>
                    <td colspan="6" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;border:0">
                        &nbsp;</td>
                </tr>
            </table>
            {{-- <table class="skor-a" width="100%" border="1" style="border-collapse: collapse; margin-right: 10px;">
                <tr>
                    <th width="5%" rowspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        No
                    </th>
                    <th witdth="25%" rowspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Jenis Skoring
                    </th>
                    <th width="70%" colspan="4"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Skor Section A
                    </th>
                </tr>
                <tr>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Section A
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Durasi
                    </td>
                    <td style="font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Total (Section A + Durasi)
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Skor Section A
                    </td>
                </tr>
                <tr>
                    <td width="3%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">1.
                    </td>
                    <td width="24%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Tinggi kursi & Lebar Kursi
                    </td>
                    <td width="13%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_total_tinggi_kursi_dan_lebar_dudukan }}
                    </td>
                    <td width="9%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_durasi_tinggi_kursi }}
                    </td>
                    <td width="21%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->total_skor_tinggi_dan_lebar_kursi }}
                    </td>
                    <td rowspan="2" width="30%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->total_section_a }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">2.
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Sandaran Lengan & Punggung
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_total_sandaran_lengan_dan_punggung }}
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_durasi_sandaran_lengan }}
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->total_skor_sandaran_lengan_dan_punggung }}
                    </td>
                </tr>
                <tr>
                    <td colspan="6" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;border:0">
                        &nbsp;</td>
                </tr>
            </table> --}}
            <table class="skor-b" width="100%" border="1" style="border-collapse: collapse; margin-right: 10px;">
                <tr>
                    <th width="5%" rowspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        No
                    </th>
                    <th witdth="25%" rowspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Jenis Skoring
                    </th>
                    <th width="70%" colspan="4"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Skor Section B
                    </th>
                </tr>
                <tr>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Section B
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Durasi
                    </td>
                    <td style="font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Total (Section B + Durasi)
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Skor Section B
                    </td>
                </tr>
                <tr>
                    <td width="3%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">1.
                    </td>
                    <td width="24%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Monitor
                    </td>
                    <td width="13%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;  text-align: center;">
                        {{ $pengukuran->skor_monitor }}
                    </td>
                    <td width="9%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_durasi_kerja_monitor }}
                    </td>
                    <td width="21%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->total_skor_monitor }}
                    </td>
                    <td rowspan="2" width="30%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->total_section_b }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">2.
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Telepon
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_telepon }}
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_durasi_kerja_telepon }}
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->total_skor_telepon }}
                    </td>
                </tr>
                <tr>
                    <td colspan="6" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;border:0">
                        &nbsp;</td>
                </tr>
            </table>
            <table class="skor-c" width="100%" border="1" style="border-collapse: collapse; margin-right: 10px;">
                <tr>
                    <th width="5%" rowspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        No
                    </th>
                    <th witdth="25%" rowspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Jenis Skoring
                    </th>
                    <th width="70%" colspan="4"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Skor Section C
                    </th>
                </tr>
                <tr>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Section C
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Durasi
                    </td>
                    <td style="font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Total (Section C + Durasi)
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Skor Section C
                    </td>
                </tr>
                <tr>
                    <td width="3%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">1.
                    </td>
                    <td width="24%" style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                        Mouse
                    </td>
                    <td width="13%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_mouse }}
                    </td>
                    <td width="9%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_durasi_kerja_mouse }}
                    </td>
                    <td width="21%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->total_skor_mouse }}
                    </td>
                    <td rowspan="2" width="30%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->total_section_c }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">2.
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">Keyboard</td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_keyboard }}
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->skor_durasi_kerja_keyboard }}
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->total_skor_keyboard }}
                    </td>
                </tr>
                <tr>
                    <td colspan="6"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;border:0">
                        &nbsp;</td>
                </tr>
            </table>
            <table class="skor-d" width="100%" border="1"
                style="border-collapse: collapse; margin-right: 10px;">
                <tr>
                    <td width="33%"
                        style="font-family:Arial, sans-serif; font-size:x-small; text-align: center; font-weight: bold;">
                        Skor Section A (Section B & Section C)
                    </td>
                    <td width="5%" rowspan="2" style="border: 0;">
                        &nbsp;
                    </td>
                    <td width="27%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; font-weight: bold;">
                        Skor ROSA
                    </td>
                    <td width="5%" rowspan="2" style="border: 0; text-align: center; font-size: 30px;">&rarr;
                    </td>
                    <td width="30%" rowspan="2"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->final_skor_rosa }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        {{ $pengukuran->total_section_d }}
                    </td>
                    <td
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; font-weight: bold;">
                        Skoring Section A & Section D
                    </td>
                </tr>
                <tr>
                    <td colspan="5"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;border:0">
                        &nbsp;
                    </td>
                </tr>
            </table>
            <table class="acuan" width="100%" border="1"
                style="border-collapse: collapse; margin-right: 10px;">
                <tr>
                    <td width="27%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Skor Akhir
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Kategori Risiko
                    </td>
                    <td width="51%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Tindakan
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        1 - 2
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Rendah
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Mungkin perlu dilakukan tindakan
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        3 - 5
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Sedang
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Diperlukan tindakan karena rawan terkena cedera
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        >5
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        Tinggi
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Diperlukan tindakan secara ergonomis sesegera mungkin
                    </td>
                </tr>
                <tr>
                    <td colspan="3"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; border: 0;">
                        &nbsp;
                    </td>
                </tr>
            </table>
            <table class="kesimpulan" width="100%" border="1"
                style="border-collapse: collapse; margin-right: 10px;">
                <tr>
                    <td height="245px"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: justify; text-wrap: break-word; vertical-align: top;">
                        Kesimpulan: <br><br>
                        {{ $pengukuran->result }}
                    </td>
                </tr>
            </table>
        </td>
        <td width="35%">
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
                <tr>
                    <td colspan="3"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; border: 0;">
                        &nbsp;
                    </td>
                </tr>
            </table>
            <table class="pelanggan" width="100%" border="0"
                style="border-collapse: collapse; text-align: center;">
                <tr>
                    <th colspan="3"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        <u>Informasi Pelanggan</u>
                    </th>
                </tr>
                <tr>
                    <td width="27%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Nama Pelanggan
                    </td>
                    <td width="5%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        :
                    </td>
                    <td width="68%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        {{ $personal->nama_pelanggan }}
                    </td>
                </tr>
                <tr>
                    <td height="50px" width="27%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left; vertical-align: top;">
                        Alamat / Lokasi Sampling
                    </td>
                    <td height="50px" width="5%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; vertical-align: top;">
                        :
                    </td>
                    <td height="50px" width="65%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left; vertical-align: top;">
                        {{ $personal->alamat_pelanggan }}
                    </td>
                </tr>
            </table>
            <table class="sampling" width="100%" border="0"
                style="border-collapse: collapse; text-align: center;">
                <tr>
                    <th colspan="3"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        <u>Informasi Sampling</u>
                    </th>
                </tr>
                <tr>
                    <td width="27%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Tanggal Sampling
                    </td>
                    <td width="5%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        :
                    </td>
                    <td width="68%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        {{ $personal->tanggal_sampling }}
                    </td>
                </tr>
                <tr>
                    <td width="27%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Periode Analisis
                    </td>
                    <td width="5%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        :
                    </td>
                    <td width="68%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        {{ $personal->periode_analisis }}
                    </td>
                </tr>
                <tr>
                    <td width="27%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Jenis Analisis
                    </td>
                    <td width="5%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        :
                    </td>
                    <td width="68%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Pengumpulan Data (Pengukuran & Skoring)
                    </td>
                </tr>
                <tr>
                    <td width="27%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left; vertical-align: top;">
                        Metode Analisis<sup style="font-size: 11px; vertical-align: middle;">*</sup>
                    </td>
                    <td width="5%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; vertical-align: top;">
                        :
                    </td>
                    <td width="68%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left; vertical-align: top;">
                        Pengamatan Langsung - ROSA (Rapid Office Restrain Assessment)
                    </td>
                </tr>
            </table>
            <table class="individu" width="100%" border="0"
                style="border-collapse: collapse; text-align: center;">
                <tr>
                    <th colspan="3"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        <u>Data Individu/Pekerja yang Diukur</u>
                    </th>
                </tr>
                <tr>
                    <td width="27%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Nama Pekerja
                    </td>
                    <td width="5%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center;">
                        :
                    </td>
                    <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        {{ $personal->nama_pekerja }}
                    </td>
                </tr>
                <tr>
                    <td width="27%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left; vertical-align: top;">
                        Jenis Pekerja
                    </td>
                    <td width="5%"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: center; vertical-align: top">
                        :
                    </td>
                    <td height="32px"
                        style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left; vertical-align: top">
                        {{ $personal->aktivitas_ukur }}
                    </td>
                </tr>
            </table>
            <table class="notes" width="100%" border="0"
                style="border-collapse: collapse; text-align: center;">
                <tr>
                    <td width="5%"
                        style="font-family:Arial, sans-serif; font-size:x-small; text-align: right; vertical-align: top;">
                        <sup style="font-size: 11px; vertical-align: middle;">*</sup>
                    </td>
                    <td width="95%" style="font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                        Metode Analisis Mengacu kepada Development and Evaluation of an Office
                        Ergonomic Risk Checklist: The Rapid Office Strain Assessment (ROSA)
                        by Michael Sonne, Dino L. Villalta, and M. Andrews, 2012.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
