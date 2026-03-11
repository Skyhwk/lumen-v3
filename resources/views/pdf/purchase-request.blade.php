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

        .badge {
            padding: 4px 10px;
            color: #fff;
            font-weight: bold;
            border-radius: 4px;
            font-size: 10px;
            display: inline-block;
        }

        .bg-Low {
            background-color: #28a745;
        }

        .bg-Normal {
            background-color: #007bff;
        }

        .bg-Urgent {
            background-color: #ffc107;
            color: #212529;
        }

        .bg-Critical {
            background-color: #dc3545;
        }

        .bg-secondary {
            background-color: #6c757d;
        }

        .data-table {
            margin-top: 10px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #dee2e6;
            padding: 6px;
            vertical-align: middle;
        }

        .data-table th {
            background-color: #f8f9fa;
            text-align: center;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        .table-success td {
            background-color: #d4edda;
        }

        .table-danger td {
            background-color: #f8d7da;
        }

        .status-note {
            font-size: 9px;
            font-style: italic;
            color: #555;
            margin-top: 4px;
        }

        .img-preview {
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
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

            <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                @if ($purchaseRequest->processed_at)
                    <table class="layout-table">
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
                    </table>
                @endif
            </td>
        </tr>
    </table>

    <hr />

    <table class="layout-table">
        <tr>
            <td class="label" style="width: 15%;">Prioritas</td>
            <td class="value" style="width: 85%;">: 
                @php
                    $bgColors = [
                        'Low' => '#28a745',
                        'Normal' => '#007bff',
                        'Urgent' => '#ffc107',
                        'Critical' => '#dc3545',
                    ];
                    $bgColor = $bgColors[$purchaseRequest->priority] ?: '#6c757d';
                    $textColor = $purchaseRequest->priority == 'Urgent' ? '#212529' : '#ffffff';
                @endphp

                <span
                    style="background-color: {{ $bgColor }}; color: {{ $textColor }}; font-weight: bold; font-size: 10px; border: 4px solid {{ $bgColor }}; border-radius: 4px;">
                    {{ strtoupper($purchaseRequest->priority) }}
                </span>
            </td>
        </tr>
        <tr>
            <td class="label" style="width: 15%;">Tujuan</td>
            <td class="value" style="width: 85%; line-height: 1.5;">: {{ $purchaseRequest->purpose }}</td>
        </tr>
    </table>

    <hr />

    <h4 style="margin-bottom: 5px; margin-top: 0;">Daftar Permintaan Barang</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No.</th>
                <th width="15%">Nama Barang</th>
                <th width="12%">Koding</th>
                <th width="12%">Merk</th>
                <th width="5%">Qty</th>
                <th width="8%">Satuan</th>
                <th width="23%">Keterangan</th>
                <th width="15%">Lampiran</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseRequest->items as $index => $item)
                @php
                    $rowClass = '';
                    $statusText = '';

                    if ($item->rejected_finance_at || $item->rejected_at) {
                        $rowClass = 'table-danger';
                        $rejector = $item->rejected_finance_by ?: $item->rejected_by;
                        $rejectDate = date('d M Y', strtotime($item->rejected_finance_at ?: $item->rejected_at));
                        $reason = $item->rejection_finance_note ?: $item->rejection_note;
                        $statusText = "Ditolak oleh {$rejector} ({$rejectDate}). Alasan: {$reason}";
                    } elseif ($item->approved_at) {
                        $rowClass = 'table-success';
                        $approver = $item->approved_by;
                        $approveDate = date('d M Y', strtotime($item->approved_at));
                        $statusText = "Disetujui oleh {$approver} ({$approveDate})";
                    }
                @endphp
                <tr class="{{ $rowClass }}">
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->item_name }}</td>
                    <td>{{ $item->item_code ?: '-' }}</td>
                    <td class="text-center">{{ $item->brand_name ?: '-' }}</td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-center">{{ $item->unit }}</td>
                    <td>
                        {{ $item->note ?: '-' }}
                        @if ($statusText)
                            <div class="status-note">* {{ $statusText }}</div>
                        @endif
                    </td>
                    <td class="text-center">
                        @if ($item->attachment && file_exists(public_path('purchase-requests/' . $item->attachment)))
                            <img src="{{ public_path('purchase-requests/' . $item->attachment) }}" class="img-preview" alt="Lampiran">
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>

</html>
