<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Purchase Order - {{ $poDocument->po_number }}</title>
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

        .header-table td {
            vertical-align: top;
            padding: 0;
        }

        .logo {
            height: 45px;
            padding-bottom: 10px;
        }

        .doc-title-wrap {
            padding: 5px 0;
            text-align: center;
        }

        .doc-title {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 1px;
            margin: 0;
        }

        .meta-label {
            font-weight: bold;
            width: 70px;
        }

        .meta-space {
            font-weight: bold;
            width: 25px;
            text-align: center;
        }

        .meta-value {
            text-align: left;
        }

        .supplier-label {
            font-weight: bold;
            font-size: 11px;
            margin-top: 12px;
        }

        .supplier-name {
            font-weight: bold;
            font-size: 11px;
            margin-top: 4px;
        }

        .supplier-address {
            font-size: 10px;
            line-height: 1.4;
            margin-top: 2px;
        }

        .items-table {
            margin-top: 14px;
        }

        .items-table th {
            background: #000;
            color: #fff;
            padding: 6px 4px;
            text-align: center;
            font-size: 10px;
            border: 1px solid #000;
        }

        .items-table td {
            border: 1px solid #000;
            padding: 6px 4px;
            vertical-align: top;
            font-size: 10px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .summary-table td {
            padding: 3px 6px;
            font-size: 10px;
        }

        .summary-label {
            text-align: right;
            width: 55%;
        }

        .summary-value {
            text-align: right;
            width: 45%;
            font-weight: bold;
        }

        .summary-total td {
            background: #000;
            color: #fff;
            font-weight: bold;
            padding: 5px 6px;
        }

        .info-table td {
            padding: 2px 0;
            vertical-align: top;
            font-size: 9.5px;
            line-height: 1.35;
        }

        .info-label {
            width: 105px;
            font-weight: bold;
        }

        .footer-date {
            margin-top: 18px;
            font-size: 10px;
        }

        .signature-title {
            font-size: 10px;
            font-weight: bold;
            margin-top: 6px;
        }

        .page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #333;
        }

        .page-number {
            text-align: right;
            font-size: 9px;
            margin-top: 8px;
        }

        .keterangan-title {
            font-weight: bold;
            margin-bottom: 4px;
        }
    </style>
</head>

<body>
    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                <img class="logo" src="{{ public_path('img/isl_logo.png') }}" alt="ISL">
                <table style="width:80%; border-top:1px solid #000; border-bottom:1px solid #000;">
                    <tr>
                        <td style="text-align:left; padding:4px 0;">
                        <div class="supplier-label">Supplier :</div>
                        </td>
                    </tr>
                </table>
                <table style="width:80%; margin-top:10px;">
                    <tr>
                        <td style="text-align:left; padding:4px 0;">
                            <div class="supplier-name">{{ $poDocument->supplier_name }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:left;">
                            <div class="supplier-address">{!! nl2br(e($poDocument->supplier_address)) !!}</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%;">
                <table style="width:100%; border-top:1px solid #000; border-bottom:1px solid #000;">
                    <tr>
                        <td style="text-align:center; padding:4px 0;">
                            <span style="
                                font-size:24px;
                                font-weight:bold;
                                letter-spacing:1px;
                            ">
                                PURCHASE ORDER
                            </span>
                        </td>
                    </tr>
                </table>

                <table style="margin-top:8px; width:80%;">
                    <tr>
                        <td class="meta-label">No. Form</td>
                        <td class="meta-space">:</td>
                        <td class="meta-value">{{ $poDocument->po_number }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">No. Faktur</td>
                        <td class="meta-space">:</td>
                        <td class="meta-value">{{ $poDocument->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Tanggal</td>
                        <td class="meta-space">:</td>
                        <td class="meta-value">{{ $poDateFormatted }}</td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 28%;">Nama Barang</th>
                <th style="width: 32%;">Keterangan</th>
                <th style="width: 8%;">Qty</th>
                <th style="width: 16%;">@Harga</th>
                <th style="width: 16%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $poDocument->item_name }}</td>
                <td>{{ $poDocument->keterangan ?: '-' }}</td>
                <td class="text-center">{{ number_format($poDocument->quantity, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($poDocument->unit_price, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($poDocument->line_total, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                @if($poDocument->keterangan)
                    <div class="keterangan-title">KETERANGAN :</div>
                    <div style="font-size: 9.5px; line-height: 1.4;">{!! nl2br(e($poDocument->keterangan)) !!}</div>
                @endif

                <table class="info-table" style="margin-top: 10px;">
                    <tr>
                        <td class="info-label">Telp / Fax</td>
                        <td>: {{ $poDocument->phone_fax ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Person in charge</td>
                        <td>: {{ $poDocument->pic ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Pembayaran</td>
                        <td>: {{ $poDocument->payment_term ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Status Barang</td>
                        <td>: {{ $poDocument->item_status ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Waktu Pengiriman</td>
                        <td>: {{ $deliveryTimeFormatted ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Pengiriman</td>
                        <td>: {{ $poDocument->delivery_type ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Ref. Penawaran</td>
                        <td>: {{ $poDocument->offer_ref ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Pengiriman ke</td>
                        <td>: {!! nl2br(e($poDocument->shipping_address)) !!}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 10px;">
            </td>
            <td style="width: 40%; vertical-align: top; padding-left: 10px;">
                <table class="summary-table">
                    <tr>
                        <td colspan="2"
                            style="
                                border-top:1px solid !important;
                                height:2px;
                                padding:0;
                            ">
                        </td>
                    </tr>
                    <tr>
                        <td class="summary-label">Sub Total</td>
                        <td class="summary-value">{{ number_format($poDocument->sub_total, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="summary-label">Diskon</td>
                        <td class="summary-value">{{ number_format($poDocument->discount, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="summary-label">PPN ({{ number_format($poDocument->ppn_percent, 0) }}%)</td>
                        <td class="summary-value">{{ number_format($poDocument->ppn_amount, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="summary-label">Biaya Lain-lain</td>
                        <td class="summary-value">{{ number_format($poDocument->other_cost, 0, ',', '.') }}</td>
                    </tr>
                    <tr class="summary-total">
                        <td class="summary-label" style="color:#fff;">Total</td>
                        <td class="summary-value" style="color:#fff;">{{ number_format($poDocument->grand_total, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 20px; width: 100%;">
        <tr>
            <td style="width: 50%;">
            </td>
            <td style="width: 50%; text-align: center;">
                <table style="width:100%; margin-bottom:10px;">
                    <tr>
                        <td>
                            <div style="padding:30px">Tangerang, {{ $approvalDateFormatted }}</div>
                        </td>
                    </tr>
                </table>
                <div style="margin-top:15px; display: flex; flex-direction: column; align-items: center;">
                    @if($qrPath && file_exists($qrPath))
                        <img src="{{ $qrPath }}" width="55" height="55" alt="QR Pengesahan" style="margin-bottom:5px;">
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="page-footer">
        Ruko Icon Business Park Blok O No 5-6, BSD City, Sampora, Cisauk, Kab. Tangerang, Banten - 15345
    </div>
</body>

</html>
