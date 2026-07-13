<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Purchase Request - {{ $purchaseRequest->request_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
        }

        .header-title {
            text-align: center;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .header-title h2 {
            margin: 0;
            font-size: 18px;
        }

        .text-muted {
            color: #6c757d;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .layout-table td {
            padding: 4px;
            vertical-align: top;
        }

        .label {
            font-weight: bold;
            width: 35%;
        }

        .value {
            width: 65%;
        }

        hr {
            border: 0;
            border-top: 1px solid #dee2e6;
            margin: 15px 0;
        }

        .data-table {
            margin-top: 10px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #333;
            padding: 6px;
            vertical-align: middle;
        }

        .data-table th {
            text-align: center;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        .img-preview {
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
        }

        .signature-table {
            margin-top: 40px;
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #333;
        }

        .signature-table>tbody>tr>td {
            border: 1px solid #333;
            padding: 0;
            vertical-align: top;
        }

        .signature-inner {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-inner td {
            border: none;
            text-align: center;
            vertical-align: middle;
        }

        .signature-label {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            padding: 12px 8px 8px;
            border-bottom: 1px solid #ccc;
        }

        .signature-gap {
            height: 75px;
            padding: 0;
        }

        .signature-footer {
            border-top: 1px solid #333;
            padding: 0;
        }

        .signature-name {
            font-size: 10px;
            font-weight: bold;
            padding: 8px 8px 2px;
            min-height: 14px;
        }

        .signature-position {
            font-size: 9px;
            color: #444;
            padding: 0 8px 12px;
            min-height: 12px;
        }
    </style>
</head>

<body>

    <div class="header-title">
        <h2>Purchase Request</h2>
        <span class="text-muted">No. Dokumen: {{ $purchaseRequest->request_number }}</span>
    </div>

    <table>
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                <table class="layout-table">
                    <tr>
                        <td class="label">Nama Karyawan</td>
                        <td class="value">:
                            {{ $purchaseRequest->employee_name ?: ($purchaseRequest->employee->nama_lengkap ?: '-') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="label">NIK Karyawan</td>
                        <td class="value">:
                            {{ $purchaseRequest->employee_nik ?: ($purchaseRequest->employee->nik_karyawan ?: '-') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Jabatan</td>
                        <td class="value">:
                            {{ $purchaseRequest->employee_position ?: ($purchaseRequest->employee->jabatan ?: '-') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Divisi</td>
                        <td class="value">:
                            {{ $purchaseRequest->employee_department ?: ($purchaseRequest->employee->department ?: '-') }}
                        </td>
                    </tr>
                </table>
            </td>

            <td style="width: 55%; vertical-align: bottom; padding-left: 10px;">
                <table class="layout-table">
                    <tr>
                        <td class="label">Tanggal Diajukan</td>
                        <td class="value">:
                            {{ date('d M Y', strtotime($purchaseRequest->created_at)) }}
                        </td>
                    </tr>
                    @if ($purchaseRequest->tanggal_kedatangan)
                        <tr>
                            <td class="label">Tanggal Kedatangan</td>
                            <td class="value">:
                                {{ date('d M Y', strtotime($purchaseRequest->tanggal_kedatangan)) }}
                            </td>
                        </tr>
                    @endif
                    @if ($purchaseRequest->processed_at)
                        <tr>
                            <td class="label">Diproses oleh</td>
                            <td class="value">: {{ $purchaseRequest->processed_by }}</td>
                        </tr>
                        <tr>
                            <td class="label">Diproses pada</td>
                            <td class="value">: {{ date('d M Y H:i', strtotime($purchaseRequest->processed_at)) }}</td>
                        </tr>

                        @if ($purchaseRequest->pending_at && !$purchaseRequest->completed_at)
                            <tr>
                                <td class="label">Dipending oleh</td>
                                <td class="value">: {{ $purchaseRequest->pending_by }}</td>
                            </tr>
                            <tr>
                                <td class="label">Dipending pada</td>
                                <td class="value">: {{ date('d M Y H:i', strtotime($purchaseRequest->pending_at)) }}
                                </td>
                            </tr>
                        @endif

                        @if ($purchaseRequest->completed_at)
                            <tr>
                                <td class="label">Diselesaikan oleh</td>
                                <td class="value">: {{ $purchaseRequest->completed_by }}</td>
                            </tr>
                            <tr>
                                <td class="label">Diselesaikan pada</td>
                                <td class="value">: {{ date('d M Y H:i', strtotime($purchaseRequest->completed_at)) }}
                                </td>
                            </tr>
                        @endif
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <hr />

    <table class="layout-table">
        <tr>
            <td class="label" style="width: 15%;">Prioritas</td>
            <td class="value" style="width: 85%;">: {{ strtoupper($purchaseRequest->priority) }}</td>
        </tr>
        <tr>
            <td class="label" style="width: 15%;">Tujuan</td>
            <td class="value" style="width: 85%; line-height: 1.5;">: {{ $purchaseRequest->purpose }}</td>
        </tr>
    </table>

    <hr />

    <h4 style="margin-bottom: 5px; margin-top: 0;">Detail Barang</h4>
    @php
        $item = $purchaseRequest->items->first();
        $attachments = [];

        if ($item && $item->attachment) {
            $decoded = json_decode($item->attachment, true);
            $attachments = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $decoded
                : [$item->attachment];
        }

        $signatureWidth = count($signatures ?? []) > 0 ? floor(100 / count($signatures)) : 25;
    @endphp

    @if ($item)
        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">No.</th>
                    <th width="22%">Nama Barang</th>
                    <th width="12%">Koding</th>
                    <th width="12%">Merk</th>
                    <th width="7%">Qty</th>
                    <th width="8%">Satuan</th>
                    <th width="22%">Keterangan</th>
                    <th width="12%">Lampiran</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">1</td>
                    <td>{{ $item->item_name }}</td>
                    <td class="text-center">{{ $item->item_code ?: '-' }}</td>
                    <td>{{ $item->brand_name ?: '-' }}</td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-center">{{ $item->unit }}</td>
                    <td>{{ $item->note ?: '-' }}</td>
                    <td class="text-center">
                        @if (!empty($attachments))
                            @foreach ($attachments as $attachment)
                                @if (file_exists(public_path('purchase-requests/' . $attachment)))
                                    <img src="{{ public_path('purchase-requests/' . $attachment) }}" class="img-preview" alt="Lampiran"
                                        style="margin-right: 4px;">
                                @endif
                            @endforeach
                        @else
                            -
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    @else
        <p class="text-muted">Tidak ada data barang.</p>
    @endif

    <table class="signature-table">
        <tr>
            @foreach ($signatures as $signature)
                <td width="{{ $signatureWidth }}%">
                    <table class="signature-inner">
                        <tr>
                            <td class="signature-label"><u>{{ $signature['label'] }}</u></td>
                        </tr>
                        <tr>
                            <td class="signature-gap">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="signature-footer">
                                <table class="signature-inner">
                                    <tr>
                                        <td class="signature-name">{{ $signature['name'] }}</td>
                                    </tr>
                                    <tr>
                                        <td class="signature-position">{{ $signature['position'] }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>

</body>

</html>