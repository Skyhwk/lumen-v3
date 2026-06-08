<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Serah Terima Barang - {{ $handoverNumber }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .logo {
            height: 38px;
        }

        .company-name {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.3px;
        }

        .doc-title {
            font-size: 13px;
            font-weight: bold;
            text-decoration: underline;
            margin: 10px 0 8px;
        }

        .meta-label {
            font-weight: bold;
            width: 72px;
        }

        .meta-colon {
            width: 12px;
            text-align: center;
        }

        .items-table {
            margin-top: 10px;
        }

        .items-table th {
            background: #000;
            color: #fff;
            padding: 5px 4px;
            text-align: center;
            font-size: 9px;
            border: 1px solid #000;
        }

        .items-table td {
            border: 1px solid #000;
            padding: 5px 4px;
            vertical-align: top;
            font-size: 9px;
        }

        .text-center {
            text-align: center;
        }

        .keterangan-title {
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 4px;
        }

        .keterangan-body {
            font-size: 9px;
            line-height: 1.45;
            min-height: 36px;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 18px 0 14px;
        }

        .sign-block {
            width: 48%;
            vertical-align: top;
            font-size: 9px;
        }

        .sign-label {
            margin-bottom: 36px;
        }

        .sign-name {
            font-weight: bold;
            border-bottom: 1px solid #000;
            min-height: 14px;
            padding-bottom: 2px;
        }

        .sign-meta {
            margin-top: 4px;
            line-height: 1.35;
        }

        .sign-date {
            margin-top: 8px;
        }
    </style>
</head>

<body>
    <table>
        <tr>
            <td style="width: 55px; vertical-align: top;">
                <img class="logo" src="{{ public_path('img/isl_logo.png') }}" alt="ISL">
            </td>
            <td style="vertical-align: top; padding-left: 6px;">
                <div class="company-name">INTI SURYA LABORATORIUM</div>
            </td>
        </tr>
    </table>

    <div class="doc-title">SERAH TERIMA BARANG &amp; DOKUMEN</div>

    <table>
        <tr>
            <td class="meta-label">No. Form #</td>
            <td class="meta-colon">:</td>
            <td>{{ $handoverNumber }}</td>
        </tr>
        <tr>
            <td class="meta-label">Tanggal</td>
            <td class="meta-colon">:</td>
            <td>{{ $handoverDateFormatted }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 18%;">Kode Barang</th>
                <th style="width: 34%;">Nama Barang</th>
                <th style="width: 10%;">Kts.</th>
                <th style="width: 12%;">Satuan</th>
                <th style="width: 26%;">Catatan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">{{ $itemCode ?: '-' }}</td>
                <td>{{ $itemName }}</td>
                <td class="text-center">{{ $quantity }}</td>
                <td class="text-center">{{ $unit }}</td>
                <td>{{ $itemNote ?: '-' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="keterangan-title">Keterangan</div>
    <div class="keterangan-body">{!! nl2br(e($keterangan)) !!}</div>

    <div class="divider"></div>

    <table>
        <tr>
            <td class="sign-block">
                <div class="sign-label">Diserahkan Oleh,</div>
                <div class="sign-name">{{ $handedByName }}</div>
                <div class="sign-meta">{{ $handedByPosition }}</div>
                <div class="sign-date">Tgl. {{ $handoverDateFormatted }}</div>
            </td>
            <td style="width: 4%;"></td>
            <td class="sign-block">
                <div class="sign-label">Diterima Oleh,</div>
                <div class="sign-name">{{ $receivedByName }}</div>
                <div class="sign-meta">
                    {{ $receivedByPosition }}<br>
                    {{ $receivedByDivision }}
                </div>
                <div class="sign-date">Tgl. ........................</div>
            </td>
        </tr>
    </table>
</body>

</html>
