<style>
    body {
        font-family: calibri, sans-serif;
        font-size: 11pt;
        color: #111;
        line-height: 1.35;
        text-align: justify;
    }
    p { margin: 0 0 3mm; }
    .frame {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #000;
    }
    .frame td { vertical-align: top; padding: 0; }
    .frame-body {
        padding: 3mm 5mm 4mm;
        border-top: 1px solid #c0c0c0;
    }
    .field-line {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 4mm;
    }
    .field-line td { padding: 0.5mm 0; vertical-align: top; }
    .field-label { width: 38mm; }
    .field-sep { width: 4mm; }
    .sp-gap td,
    .sp-gap-lg td {
        padding: 0;
        margin: 0;
        border: none;
        font-size: 1pt;
        line-height: 1pt;
    }
    .sp-gap td { height: 8mm; }
    .sp-gap-sm td { height: 6mm; }
    .sp-gap-lg td { height: 10mm; }
    .section-title {
        font-weight: bold;
        text-decoration: underline;
        margin: 0 0 2mm 0;
        padding: 0;
    }
    .section-title.red { color: #c00000; }
    .list-block {
        margin: 2mm 0 2mm 6mm;
        padding-left: 4mm;
    }
    .list-block li { margin-bottom: 2mm; }
    .dash-list {
        list-style: none;
        padding-left: 0;
        margin-left: 6mm;
    }
    .dash-list li { margin-bottom: 2mm; }
    .uraian-block { margin: 2mm 0 3mm 6mm; }
    .uraian-block p { margin-bottom: 2mm; }
    .closing {
        margin: 0 0 3mm 0;
    }
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
        color: #c00000;
    }
    .sign-title {
        font-style: italic;
        color: #c00000;
        font-size: 10pt;
    }
    .page-break { page-break-before: always; }
</style>

<table class="frame">
    <tr>
        <td class="frame-body">
            <table class="field-line">
                <tr>
                    <td class="field-label" colspan="3">Surat Peringatan ini diterbitkan kepada :</td>
                </tr>
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

            <table class="sp-gap-sm" width="100%" cellpadding="0" cellspacing="0"><tr><td>&nbsp;</td></tr></table>
            <div class="section-title">Dasar Penerbitan Surat Peringatan</div>
            <p>Bahwa karyawan dengan data dan informasi tersebut di atas telah melakukan kesalahan berupa :</p>
            @if(!empty($violationCategory))
                <p style="margin-left:6mm;"><strong>{{ $violationCategory }}</strong></p>
            @endif

            <table class="sp-gap" width="100%" cellpadding="0" cellspacing="0"><tr><td>&nbsp;</td></tr></table>
            <div class="section-title">Catatan :</div>
            <div class="list-block uraian-block">
                @if(!empty($hrNotesHtml))
                    {!! $hrNotesHtml !!}
                @else
                    <p>-</p>
                @endif
            </div>

            <table class="sp-gap" width="100%" cellpadding="0" cellspacing="0"><tr><td>&nbsp;</td></tr></table>
            <div class="section-title red">Bentuk Sanksi</div>
            <p>Sanksi diberikan dalam bentuk {{ $sanctionText }}.</p>

            <table class="sp-gap" width="100%" cellpadding="0" cellspacing="0"><tr><td>&nbsp;</td></tr></table>
            <div class="section-title">Masa Berlaku Surat Peringatan</div>
            <ul class="list-block dash-list">
                <li>
                    - Surat Peringatan berlaku selama {{ $validityMonths }} ({{ $validityMonthsTerbilang }}) bulan,
                    terhitung sejak {{ $validityStartLabel }} sampai dengan tanggal {{ $validityEndLabel }}.
                </li>
                <li>
                    - Apabila selama masa berlaku Surat Peringatan {{ $spLevelNumber }} tersebut di atas,
                    {{ $honorific }} <strong>tidak mengulangi / melakukan</strong> <strong>pelanggaran lain,</strong>
                    maka Surat Peringatan {{ $spLevelNumber }} {{ $honorific }} akan dinyatakan tidak berlaku / gugur.
                </li>
                <li>
                    - Apabila selama masa berlaku Surat Peringatan {{ $spLevelNumber }} tersebut di atas,
                    {{ $honorific }} <strong>kembali mengulangi / melakukan</strong> <strong>pelanggaran lain,</strong>
                    maka perusahaan akan memberikan Surat Peringatan {{ $nextSpLabel }} untuk {{ $honorific }}.
                </li>
            </ul>

            <table class="sp-gap-lg" width="100%" cellpadding="0" cellspacing="0"><tr><td>&nbsp;</td></tr></table>
            <p class="closing">
                Demikian Surat Peringatan {{ $spLevelNumber }} ini dibuat agar dapat diperhatikan dan ditaati
                dengan sebaik-baiknya oleh yang bersangkutan.
            </p>

            <table class="sp-gap-lg" width="100%" cellpadding="0" cellspacing="0"><tr><td>&nbsp;</td></tr></table>
            
        </td>
    </tr>
</table>
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
