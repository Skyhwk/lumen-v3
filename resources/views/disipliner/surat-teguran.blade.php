<style>
    body {
        font-family: calibri, sans-serif;
        font-size: 11pt;
        color: #111;
        line-height: 1.35;
        text-align: justify;
    }
    p { margin: 0 0 3mm; }
    .field-line {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 4mm;
    }
    .field-line td { padding: 0.5mm 0; vertical-align: top; }
    .field-label { width: 22mm; white-space: nowrap; }
    .field-sep { width: 4mm; }
    .perihal-value {
        font-weight: bold;
        text-decoration: underline;
    }
    .sp-gap-sm td { height: 4mm; padding: 0; font-size: 1pt; line-height: 1pt; }
    .sp-gap td { height: 6mm; padding: 0; font-size: 1pt; line-height: 1pt; }
    .section-title {
        font-weight: bold;
        text-decoration: underline;
        margin: 0 0 2mm 0;
    }
    .list-block {
        margin: 2mm 0 2mm 6mm;
        padding-left: 0;
    }
    .list-block p { margin-bottom: 2mm; }
    .closing { margin: 0 0 3mm 0; }
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
    .sign-name {
        font-weight: bold;
        text-decoration: underline;
        margin-top: 2mm;
    }
    .sign-title { font-style: italic; margin-top: 1mm; }
</style>

<table class="field-line">
    <tr>
        <td class="field-label">Perihal</td>
        <td class="field-sep">:</td>
        <td class="perihal-value">{{ $perihal }}</td>
    </tr>
</table>

<table class="sp-gap-sm" width="100%" cellpadding="0" cellspacing="0"><tr><td>&nbsp;</td></tr></table>

<p>Perusahaan menerbitkan Surat Teguran kepada karyawan dengan data sebagai berikut :</p>

<table class="field-line">
    <tr>
        <td class="field-label">Nama / NIK</td>
        <td class="field-sep">:</td>
        <td>{{ $employeeNameNik }}</td>
    </tr>
    <tr>
        <td class="field-label">Jabatan</td>
        <td class="field-sep">:</td>
        <td>{{ $positionName }}</td>
    </tr>
    <tr>
        <td class="field-label">Divisi / Posisi</td>
        <td class="field-sep">:</td>
        <td>{{ $divisionName }}</td>
    </tr>
    <tr>
        <td class="field-label">Penempatan</td>
        <td class="field-sep">:</td>
        <td>{{ $placementName }}</td>
    </tr>
</table>

<table class="sp-gap" width="100%" cellpadding="0" cellspacing="0"><tr><td>&nbsp;</td></tr></table>

<div class="section-title">Dasar Penerbitan Surat Teguran</div>
<p>Bahwa karyawan telah melakukan pelanggaran, yaitu sebagai berikut :</p>

<div class="list-block">
    @if(!empty($violationHtml))
        {!! $violationHtml !!}
    @else
        <p>-</p>
    @endif
</div>

<table class="sp-gap" width="100%" cellpadding="0" cellspacing="0"><tr><td>&nbsp;</td></tr></table>

<p class="closing">
    Oleh karena itu, surat teguran ini diberikan dengan tujuan sebagai teguran tertulis perusahaan kepada
    {{ $honorific }} agar dapat mematuhi peraturan perusahaan yang berlaku dan {{ $honorific }}
    tidak mengulangi / melakukan pelanggaran serupa di kemudian hari.
</p>
<p class="closing">
    Apabila {{ $honorific }} <strong>mengulangi / melakukan pelanggaran lain,</strong> maka perusahaan dapat memberikan
    Surat Teguran / Surat Peringatan / sampai dengan pengenaan Pemutusan Hubungan Kerja (PHK) kepada {{ $honorific }}.
</p>
<p class="closing">
    Demikian Surat Teguran ini dibuat agar dapat diperhatikan dan dipatuhi dengan sebaik-baiknya oleh {{ $honorific }}.
</p>

<div style="page-break-inside: avoid;">
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
</div>
</div>
