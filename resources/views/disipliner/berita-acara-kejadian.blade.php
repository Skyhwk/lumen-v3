<style>
    body {
        font-family: calibri, sans-serif;
        font-size: 10.5pt;
        color: #111;
        line-height: 1.3;
        text-align: justify;
    }
    p { margin: 0 0 2mm; }
    .section-heading {
        font-weight: bold;
        margin: 3mm 0 1mm;
        text-align: left;
    }
    .section-hint { font-weight: normal; font-size: 9pt; }
    table.grid {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }
    table.grid td {
        font-size: 10.5pt;
        line-height: 1.25;
        vertical-align: bottom;
        padding: 1.1mm 0 1.3mm;
    }
    td.k { white-space: nowrap; }
    td.c { text-align: center; white-space: nowrap; }
    td.v { border-bottom: 0.35mm solid #111; padding-right: 2mm; }
    td.note {
        font-size: 8pt;
        color: #333;
        text-align: right;
        vertical-align: top;
        white-space: nowrap;
    }
    .u {
        border-bottom: 0.35mm solid #111;
        padding: 0 12mm 0 0;
    }
    .kronologi-block { margin: 1.5mm 0 3mm; text-align: left; }
    .kronologi-block p { margin: 0 0 1.5mm; text-align: left; }
    .kronologi-ruled {
        border-bottom: 0.3mm solid #999;
        height: 6.2mm;
    }
    .closing { margin: 3mm 0 6mm; }
    .sign-block {
        width: 100%;
        page-break-inside: avoid;
    }
    .sign-date {
        margin: 0 0 4mm 0;
        text-align: left;
    }
    .sign-qr-row {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1mm;
    }
    .sign-qr-row td {
        padding: 0;
        text-align: left;
        vertical-align: top;
    }
    .sign-qr-row img {
        width: 12mm;
        height: 12mm;
        max-width: 12mm;
        max-height: 12mm;
    }
    .sign-line { border-bottom: 0.35mm solid #111; width: 55mm; margin-top: 2mm; }
    .strike { text-decoration: line-through; }
</style>

<table class="grid">
    <colgroup>
        <col style="width:38mm">
        <col style="width:3mm">
        <col style="width:95mm">
        <col style="width:46mm">
    </colgroup>
    <tr>
        <td class="k">Perihal**</td>
        <td class="c">:</td>
        <td class="v">@if($perihal !== ''){{ $perihal }}@else&nbsp;@endif</td>
        <td class="note">*coret yang tidak perlu</td>
    </tr>
    <tr>
        <td class="k">Lampiran**</td>
        <td class="c">:</td>
        <td class="v">@if($lampiran !== '-'){{ $lampiran }}@else&nbsp;@endif</td>
        <td class="note">** diisi oleh HRD</td>
    </tr>
</table>

<p class="section-heading">Yang bertanda tangan di bawah ini :</p>
<table class="grid">
    <colgroup>
        <col style="width:44mm">
        <col style="width:3mm">
        <col style="width:48mm">
        <col style="width:38mm">
        <col style="width:3mm">
        <col style="width:46mm">
    </colgroup>
    <tr>
        <td class="k">Nama Lengkap</td>
        <td class="c">:</td>
        <td class="v">{{ $signatoryNama }}</td>
        <td class="k">NIK</td>
        <td class="c">:</td>
        <td class="v">{{ $signatoryNik }}</td>
    </tr>
    <tr>
        <td class="k">Tempat, Tanggal Lahir</td>
        <td class="c">:</td>
        <td class="v">{{ $signatoryTempatTanggalLahir }}</td>
        <td class="k">Divisi</td>
        <td class="c">:</td>
        <td class="v">{{ $signatoryDivisi }}</td>
    </tr>
    <tr>
        <td class="k">Nomor HP</td>
        <td class="c">:</td>
        <td class="v">{{ $signatoryNomorHp }}</td>
        <td class="k">Posisi</td>
        <td class="c">:</td>
        <td class="v">{{ $signatoryPosisi }}</td>
    </tr>
    <tr>
        <td class="k">Alamat Saat Ini</td>
        <td class="c">:</td>
        <td class="v" colspan="4">{{ $signatoryAlamat }}</td>
    </tr>
</table>

<p class="section-heading">Dengan ini menerangkan bahwa pada :</p>
<table class="grid">
    <colgroup>
        <col style="width:44mm">
        <col style="width:3mm">
        <col style="width:48mm">
        <col style="width:38mm">
        <col style="width:3mm">
        <col style="width:46mm">
    </colgroup>
    <tr>
        <td class="k">Hari dan Tanggal</td>
        <td class="c">:</td>
        <td class="v">{{ $hariDanTanggal }}</td>
        <td class="k">Pukul</td>
        <td class="c">:</td>
        <td><span class="u">{{ $pukulKejadian }}</span> <span style="font-size:9pt;"></span></td>
    </tr>
    <tr>
        <td class="k">Telah Terjadi Kejadian</td>
        <td class="c">:</td>
        <td class="v" colspan="4">@if($telahTerjadiKejadian !== ''){{ $telahTerjadiKejadian }}@else&nbsp;@endif</td>
    </tr>
    <tr>
        <td class="k">Tempat Kejadian</td>
        <td class="c">:</td>
        <td class="v" colspan="4">@if($lokasiKejadian !== '-'){{ $lokasiKejadian }}@else&nbsp;@endif</td>
    </tr>
</table>

<p class="section-heading">{{ $dampakAsetSectionTitle }}</p>
<table class="grid">
    <colgroup>
        <col style="width:44mm">
        <col style="width:3mm">
        <col style="width:48mm">
        <col style="width:38mm">
        <col style="width:3mm">
        <col style="width:46mm">
    </colgroup>
    <tr>
        <td class="k">Nama / Jenis Aset</td>
        <td class="c">:</td>
        <td class="v">@if($dampakAset['nama_jenis_aset'] !== ''){{ $dampakAset['nama_jenis_aset'] }}@else&nbsp;@endif</td>
        <td class="k">No Aset** / No Plat</td>
        <td class="c">:</td>
        <td class="v">@if($dampakNoAsetPlat !== '-'){{ $dampakNoAsetPlat }}@else&nbsp;@endif</td>
    </tr>
    <tr>
        <td class="k">Merek / Tipe Aset</td>
        <td class="c">:</td>
        <td class="v">@if($dampakAset['merek_tipe_aset'] !== ''){{ $dampakAset['merek_tipe_aset'] }}@else&nbsp;@endif</td>
        <td class="k">Jumlah Unit Aset</td>
        <td class="c">:</td>
        <td class="v">@if($dampakAset['jumlah_unit_aset'] !== ''){{ $dampakAset['jumlah_unit_aset'] }}@else&nbsp;@endif</td>
    </tr>
    <tr>
        <td class="k">Tujuan Pemakaian Aset</td>
        <td class="c">:</td>
        <td class="v">@if($dampakAset['tujuan_pemakaian_aset'] !== ''){{ $dampakAset['tujuan_pemakaian_aset'] }}@else&nbsp;@endif</td>
        <td class="k">Jenis Kerusakan Aset</td>
        <td class="c">:</td>
        <td style="white-space:nowrap;">
            @if($dampakJenisSebagian)
                sebagian / <span class="strike">seluruhnya</span>*
            @elseif($dampakJenisSeluruhnya)
                <span class="strike">sebagian</span> / seluruhnya*
            @else
                sebagian / seluruhnya*
            @endif
        </td>
    </tr>
    <tr>
        <td class="k">Bagian Kerusakan Aset</td>
        <td class="c">:</td>
        <td class="v" colspan="4">@if($dampakAset['bagian_kerusakan_aset'] !== ''){{ $dampakAset['bagian_kerusakan_aset'] }}@else&nbsp;@endif</td>
    </tr>
</table>

<p class="section-heading">
    Adapun kronologi kejadian sebagai berikut :
    <span class="section-hint">(uraikan secara jelas dan lengkap)</span>
</p>
<div class="kronologi-block">
    @if($kronologiHasContent)
        {!! $uraianHtml !!}
    @else
        @for ($i = 0; $i < 10; $i++)
            <div class="kronologi-ruled">&nbsp;</div>
        @endfor
    @endif
</div>

<div style="page-break-inside: avoid;">
<p class="closing">
    Demikian berita acara kejadian ini saya buat dengan sebenar-benarnya dan dapat dipertanggungjawabkan
    atas kebenarannya, serta saya bersedia untuk menerima dan bertanggung jawab untuk melaksanakan segala
    sanksi yang ditetapkan perusahaan, sesuai dengan peraturan perusahaan yang berlaku.
</p>

<div class="sign-block" style="margin-left: 10mm; margin-top: 5mm;">
    <p class="sign-date">Tangerang, {{ $issueDateLong }}</p>
    @if(!empty($qrImagePath))
        <table class="sign-qr-row" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td align="left">
                    <img
                        src="{{ $qrImagePath }}"
                        alt="QR Pengesahan"
                        width="40"
                        height="40"
                        style="width: 17mm; height: 17mm; max-width: 17mm; max-height: 17mm; margin-left: 8%;"
                    >
                </td>
            </tr>
        </table>
    @else
        <p style="font-size: 10pt; color: #666; margin: 2mm 0 0 0; text-align: left;">
            Menunggu pengesahan (QR)
        </p>
    @endif
    <div class="sign-line"></div>
</div>
</div>
