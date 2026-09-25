<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Surat Keterangan Kesehatan</title>
    <style>
        body,
        table,
        p,
        td,
        th,
        div,
        span {
            font-family: "Times New Roman", Times, serif;
        }

        body {
            font-size: 9pt;
            color: #222;
            line-height: 1.4;
        }

        .company-header td {
            vertical-align: top;
            padding: 0;
        }

        .company-logo {
            width: 120px;
            max-height: 52px;
        }

        .company-divider {
            border: 0;
            border-top: 1px solid #333;
            margin: 10px 0 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
            margin-top: 1.75rem;
            margin-bottom: 1.75rem;
        }

        .header h1 {
            font-size: 12pt;
            margin: 0 0 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header h2 {
            font-size: 9pt;
            margin: 0;
            font-weight: normal;
        }

        .intro {
            text-align: justify;
            margin-bottom: 12px;
        }

        .section-title {
            font-weight: bold;
            margin: 12px 0 6px;
            text-transform: uppercase;
            font-size: 8.5pt;
        }

        table.info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        table.info-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        table.info-table td.label {
            width: 32%;
            /* font-weight: bold; */
        }

        table.info-table td.sep {
            width: 2%;
        }

        table.exam-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        table.exam-table th,
        table.exam-table td {
            border: 1px solid #333;
            padding: 4px 6px;
            font-size: 8.5pt;
        }

        table.exam-table th {
            background: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }

        .note-box {
            border: 1px solid #333;
            padding: 6px 8px;
            min-height: 32px;
            margin-top: 4px;
            white-space: pre-wrap;
            font-size: 8.5pt;
        }

        .conclusion {
            text-align: justify;
            margin: 14px 0;
            font-size: 8.5pt;
        }

        .signature {
            margin-top: 24px;
            width: 100%;
            font-size: 8.5pt;
        }

        .signature td {
            vertical-align: top;
        }

        .signature .sign-block {
            width: 45%;
            text-align: left;
            line-height: 1.5;
        }

        .signature .sign-block p {
            margin: 0 0 5px;
            line-height: 1;
        }

        .signature .sign-block p:last-child {
            margin-bottom: 0;
        }

        .sign-space {
            height: 56px;
        }

        .muted {
            color: #555;
        }
    </style>
</head>

<body>
    @php \Carbon\Carbon::setLocale('id'); @endphp

    <table class="company-header" width="100%">
        <tr>
            <td style="width: 33.33%; vertical-align: top;">
                @if (!empty($company['logo_path']) && file_exists($company['logo_path']))
                    <img class="company-logo" src="{{ $company['logo_path'] }}" alt="ISL">
                @endif
            </td>
            <td style="width: 33.33%;"></td>
            <td style="width: 33.33%; text-align: right; vertical-align: top;">
                <p style="font-size: 9px; text-align: right; margin: 0; line-height: 1.4;">
                    <b>{{ $company['name'] ?? 'PT INTI SURYA LABORATORIUM' }}</b><br>
                    @if (!empty($company['address']))
                        <span style="white-space: pre-wrap; word-wrap: break-word;">{{ $company['address'] }}</span><br>
                    @endif
                    <span>T : {{ $company['phone'] ?? '-' }} - {{ $company['email'] ?? 'sales@intilab.com' }}</span><br>
                    {{ $company['website'] ?? 'www.intilab.com' }}
                </p>
            </td>
        </tr>
    </table>

    <hr class="company-divider">

    <div class="header">
        <h1 style="text-decoration: underline;">Surat Keterangan Kesehatan</h1>
        @if (!empty($skkNumber))
            <p style="font-size: 10pt; margin: 3px 0 0;">{{ $skkNumber }}</p>
        @endif
    </div>

    <p class="intro">
        Yang bertanda tangan di bawah ini, Petugas Paramedis, menerangkan bahwa karyawan berikut ini
        telah dilakukan pemeriksaan kesehatan dengan hasil sebagai berikut:
    </p>

    <div class="section-title">Data Karyawan</div>
    <table class="info-table">
        <tr>
            <td class="label">Nama</td>
            <td class="sep">:</td>
            <td>{{ $record->karyawan ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">NIK</td>
            <td class="sep">:</td>
            <td>{{ $record->nik_karyawan ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Divisi</td>
            <td class="sep">:</td>
            <td>{{ $record->nama_divisi ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">Hasil Pemeriksaan</div>
    <table class="info-table">
        <tr>
            <td class="label">Tanggal Pemeriksaan</td>
            <td class="sep">:</td>
            <td>{{ $checkDateLong }}</td>
        </tr>
        <tr>
            <td class="label">Waktu Pemeriksaan</td>
            <td class="sep">:</td>
            <td>{{ $record->check_time_label ?? '-' }} WIB</td>
        </tr>
        <tr>
            <td class="label">Keterangan Kesehatan</td>
            <td class="sep">:</td>
            <td>{{ $record->keterangan_label ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Keluhan</td>
            <td class="sep">:</td>
            <td>{{ $record->keluhan ?? '-' }}</td>
        </tr>
    </table>

    <table class="exam-table">
        <thead>
            <tr>
                <th width="45%">Parameter</th>
                <th width="55%">Hasil</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Tekanan Darah (Tensi)</td>
                <td style="text-align:center;">{{ $record->tensi_label ?? '-' }} mmHg</td>
            </tr>
            <tr>
                <td>Klasifikasi Tensi</td>
                <td style="text-align:center;">{{ $record->tensi_classification ?? '-' }}</td>
            </tr>
            <tr>
                <td>Saturasi Oksigen (SpO<sub>2</sub>)</td>
                <td style="text-align:center;">{{ $saturasiLabel }} %</td>
            </tr>
            <tr>
                <td>Nadi</td>
                <td style="text-align:center;">{{ $record->nadi ?? '-' }} x/menit</td>
            </tr>
            <tr>
                <td>Suhu Tubuh</td>
                <td style="text-align:center;">{{ $suhuLabel }} &deg;C</td>
            </tr>
            <tr>
                <td>Stetoskop</td>
                <td style="text-align:center;">{{ $record->stetoskop_label ?? '-' }}</td>
            </tr>
            @if ($hasOptionalLabs)
                <tr>
                    <td>Gula Darah</td>
                    <td style="text-align:center;">{{ $record->gula_darah_label ?? '-' }} mg/dL</td>
                </tr>
                <tr>
                    <td>Asam Urat</td>
                    <td style="text-align:center;">{{ $record->asam_urat_label ?? '-' }} mg/dL</td>
                </tr>
                <tr>
                    <td>Kolesterol</td>
                    <td style="text-align:center;">{{ $record->kolesterol_label ?? '-' }} mg/dL</td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="conclusion">{!! $conclusionText !!}</p>

    <table class="signature">
        <tr>
            <td class="sign-block">
                <p class="muted">{{ $company['city'] ?? 'Tangerang' }}, {{ $issuedDateLong }}</p>
                <p><strong>Petugas Paramedis</strong></p>
                <div class="sign-space"></div>
                <p><strong>{{ $petugasName }}</strong></p>
            </td>
        </tr>
    </table>
</body>

</html>
