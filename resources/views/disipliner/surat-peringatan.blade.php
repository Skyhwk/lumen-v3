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
    .frame-head {
        border-bottom: 1px solid #c0c0c0;
        padding: 2mm 4mm 3mm;
        text-align: center;
    }
    .title-main {
        font-size: 16pt;
        font-weight: bold;
        text-align: center;
        line-height: 1.2;
    }
    .title-ref {
        font-size: 10pt;
        font-weight: bold;
        text-align: center;
        margin-top: 1.5mm;
    }
    .frame-body {
        padding: 3mm 5mm 4mm;
    }
    .field-line {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 1mm;
    }
    .field-line td { padding: 0.5mm 0; vertical-align: top; }
    .field-label { width: 38mm; }
    .field-sep { width: 4mm; }
    .section-title {
        font-weight: bold;
        text-decoration: underline;
        margin: 4mm 0 2mm;
    }
    .section-title.red { color: #c00000; }
    .list-block {
        margin: 2mm 0 2mm 6mm;
        padding-left: 4mm;
    }
    .list-block li { margin-bottom: 2mm; }
    .uraian-block { margin: 2mm 0 3mm 6mm; }
    .uraian-block p { margin-bottom: 2mm; }
    .closing { margin-top: 5mm; }
    .sign-block { margin-top: 8mm; }
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
        <td class="frame-head">
            <div class="title-main">
                SURAT PERINGATAN {{ $spLevelRoman }} (SP-{{ $spLevelNumber }})
            </div>
            <div class="title-ref">Ref : {{ $refNumber }}</div>
        </td>
    </tr>
    <tr>
        <td class="frame-body">
            <table class="field-line">
                <tr>
                    <td class="field-label">Surat Peringatan ini diterbitkan kepada : Nama / NIK</td>
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

            <div class="section-title">Dasar Penerbitan Surat Peringatan</div>
            <p>Bahwa karyawan dengan data dan informasi tersebut di atas telah melakukan kesalahan berupa :</p>
            @if(!empty($violationCategory))
                <p style="margin-left:6mm;"><strong>{{ $violationCategory }}</strong></p>
            @endif
            <div class="uraian-block">{!! $violationDescription !!}</div>

            <div class="section-title">Catatan :</div>
            <div class="list-block uraian-block">
                @if(!empty($hrNotesHtml))
                    {!! $hrNotesHtml !!}
                @else
                    <p>-</p>
                @endif
            </div>

            <div class="section-title red">Bentuk Sanksi</div>
            <p>Sanksi diberikan dalam bentuk {{ $sanctionText }}.</p>

            <div class="section-title">Masa Berlaku Surat Peringatan</div>
            <ol class="list-block">
                <li>
                    Surat Peringatan berlaku selama {{ $validityMonths }} ({{ $validityMonthsTerbilang }}) bulan,
                    terhitung sejak {{ $validityStartLabel }} sampai dengan tanggal {{ $validityEndLabel }}.
                </li>
                <li>
                    Apabila selama masa berlaku Surat Peringatan {{ $spLevelNumber }} tersebut di atas,
                    {{ $honorific }} <strong>tidak mengulangi / melakukan</strong> <strong>pelanggaran lain,</strong>
                    maka Surat Peringatan {{ $spLevelNumber }} {{ $honorific }} akan dinyatakan tidak berlaku / gugur.
                </li>
                <li>
                    Apabila selama masa berlaku Surat Peringatan {{ $spLevelNumber }} tersebut di atas,
                    {{ $honorific }} <strong>kembali mengulangi / melakukan</strong> <strong>pelanggaran lain,</strong>
                    maka perusahaan akan memberikan Surat Peringatan {{ $nextSpLabel }} untuk {{ $honorific }}.
                </li>
            </ol>

            <p class="closing">
                Demikian Surat Peringatan {{ $spLevelNumber }} ini dibuat agar dapat diperhatikan dan ditaati
                dengan sebaik-baiknya oleh yang bersangkutan.
            </p>

            <div class="sign-block">
                <p>Tangerang, {{ $issueDateLong }}</p>
                <p style="height: 18mm;">&nbsp;</p>
                <p class="sign-name">{{ $signatoryName }}</p>
                <p class="sign-title">{{ $signatoryTitle }}</p>
            </div>
        </td>
    </tr>
</table>
