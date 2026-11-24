<style>
    body { font-family: calibri, sans-serif; font-size: 11pt; line-height: 1.45; color: #111; }
    p { margin: 0 0 2.6mm; text-align: justify; }
    .perihal { text-align: left; margin: 0 0 3mm; }
    .identity { width: 100%; border-collapse: collapse; margin: 1mm 0 3mm; }
    .identity td { vertical-align: top; padding: 0.45mm 0; text-align: left; border: none; }
    .identity .label { width: 36mm; }
    .identity .sep { width: 4mm; }
    .assets { width: 100%; border-collapse: collapse; margin: 1mm 0 3.5mm; }
    .assets td { vertical-align: bottom; padding: 1.6mm 0 1.1mm; border: none; }
    .assets .num { width: 8mm; text-align: left; white-space: nowrap; }
    .assets .label { width: 24mm; white-space: nowrap; }
    .assets .sep { width: 4mm; }
    .assets .nama { width: 58mm; border-bottom: 0.6px dotted #333; padding-right: 3mm; }
    .assets .label-qty { width: 26mm; white-space: nowrap; padding-left: 2mm; }
    .assets .qty { border-bottom: 0.6px dotted #333; }
    .empty-asset { font-style: italic; color: #555; margin: 1mm 0 3mm; }
    .date-line { margin: 3mm 0 1mm; text-align: left; }
    .sign { width: 100%; border-collapse: collapse; margin-top: 2mm; table-layout: fixed; }
    .sign td { border: none; padding: 0; vertical-align: top; text-align: center; }
    .sign .col { width: 42%; }
    .sign .gap { width: 16%; }
    .sign .label { font-weight: bold; padding-bottom: 1mm; text-align: center; }
    .sign .space { height: 22mm; line-height: 22mm; font-size: 1pt; }
    .sign .name {
        font-weight: bold;
        text-decoration: underline;
        text-align: center;
        line-height: 1.2;
        padding-top: 0.5mm;
    }
    .sign .role { font-style: italic; font-size: 10.5pt; padding-top: 0.8mm; text-align: center; }
</style>

<p class="perihal">Perihal : <strong><u>Penerimaan Aset Perusahaan</u></strong></p>

<p>Yang bertanda tangan di bawah ini :</p>

<table class="identity">
    <tr><td class="label">Nama / NIK</td><td class="sep">:</td><td>{{ $namaNik }}</td></tr>
    <tr><td class="label">Divisi / Posisi</td><td class="sep">:</td><td>{{ $divisiPosisi }}</td></tr>
    <tr><td class="label">Penempatan</td><td class="sep">:</td><td>{{ $penempatan }}</td></tr>
</table>

<p>Dengan ini menyatakan bahwa saya telah menerima aset perusahaan dengan rincian sebagai berikut :</p>

@if(count($asetItems) === 0)
    <p class="empty-asset">Belum ada aset yang dicatat.</p>
@else
    <table class="assets">
        @foreach($asetItems as $index => $item)
            <tr>
                <td class="num">{{ $index + 1 }}.</td>
                <td class="label">Nama Aset</td>
                <td class="sep">:</td>
                <td class="nama">{{ $item['nama'] }}</td>
                <td class="label-qty">Jumlah Aset</td>
                <td class="sep">:</td>
                <td class="qty">{{ $item['jumlah'] }}</td>
            </tr>
        @endforeach
    </table>
@endif

<p>Demikian surat pernyataan ini dibuat dengan sebenar-benarnya, agar dapat dipergunakan sebagaimana mestinya.</p>

<p class="date-line">Tangerang, {{ $tanggalPengesahan }}</p>

<table class="sign" width="100%">
    <tr>
        <td class="col label">Penerima Aset,</td>
        <td class="gap"></td>
        <td class="col label">Mengetahui,</td>
    </tr>
    <tr>
        <td class="col space">&nbsp;</td>
        <td class="gap"></td>
        <td class="col space">&nbsp;</td>
    </tr>
    <tr>
        <td class="col name">{{ $namaKaryawan }}</td>
        <td class="gap"></td>
        <td class="col name">{{ $namaPenanggungJawab }}</td>
    </tr>
    <tr>
        <td class="col role">{{ $jabatanKaryawan }}</td>
        <td class="gap"></td>
        <td class="col role">{{ $jabatanPenanggungJawab }}</td>
    </tr>
</table>
