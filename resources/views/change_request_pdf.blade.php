<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Change Request - {{ $data->nomor_dokumen }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .header-table td {
            border: 1px solid #333;
            padding: 10px;
            vertical-align: middle;
        }
        .logo-cell {
            width: 25%;
            text-align: center;
        }
        .title-cell {
            width: 50%;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
        }
        .info-cell {
            width: 25%;
            font-size: 9px;
        }
        .section-title {
            background-color: #f2f2f2;
            font-weight: bold;
            padding: 6px 10px;
            border: 1px solid #333;
            margin-top: 15px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th, .data-table td {
            border: 1px solid #333;
            padding: 6px 8px;
            vertical-align: top;
        }
        .data-table th {
            background-color: #f9f9f9;
            text-align: left;
            width: 25%;
            font-weight: bold;
        }
        .checkbox-box {
            border: 1px solid #333;
            font-size: 10px;
            font-weight: bold;
            padding: 1px 3px;
            margin-right: 8px;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }
        .signature-table td {
            border: 1px solid #333;
            width: 33.33%;
            text-align: center;
            padding: 10px;
            vertical-align: top;
        }
        .signature-title {
            font-weight: bold;
            margin-bottom: 50px;
            text-decoration: underline;
        }
        .signature-name {
            font-weight: bold;
        }
        .signature-date {
            font-size: 9px;
            color: #666;
        }
    </style>
</head>
<body>

    <!-- Header Form -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <img src="{{ public_path('isl_logo.png') }}" style="max-height: 40px; max-width: 140px;" />
            </td>
            <td class="title-cell">
                FORMULIR PERMINTAAN PERUBAHAN<br>
                (CHANGE REQUEST FORM)
            </td>
            <td class="info-cell">
                <strong>No. Dokumen:</strong> {{ $data->nomor_dokumen }}<br>
                <strong>Tgl Permintaan:</strong> {{ \Carbon\Carbon::parse($data->tanggal_permintaan)->format('d-m-Y') }}<br>
                <strong>Status:</strong> <span style="font-weight: bold; color: #0056b3;">{{ $data->status }}</span>
            </td>
        </tr>
    </table>

    <!-- A. INFORMASI PERMINTAAN -->
    <div class="section-title">A. Informasi Permintaan</div>
    <table class="data-table">
        <tr>
            <th>Pemohon (User)</th>
            <td>{{ $data->pemohon }}</td>
            <th>Divisi / Departemen</th>
            <td>{{ $data->divisi }}</td>
        </tr>
        <tr>
            <th>Aplikasi</th>
            <td>{{ $data->aplikasi }}</td>
            <th>Jenis Permintaan</th>
            <td>{{ $data->jenis_permintaan }}</td>
        </tr>
        <tr>
            <th>Prioritas</th>
            <td><strong>{{ $data->prioritas }}</strong></td>
            <th>File Lampiran</th>
            <td>{{ $data->lampiran ? $data->lampiran : '-' }}</td>
        </tr>
    </table>

    <!-- B. DESKRIPSI PERUBAHAN -->
    <div class="section-title">B. Deskripsi Kebutuhan Perubahan</div>
    <table class="data-table">
        <tr>
            <th style="width: 20%;">Judul Fitur / Perubahan</th>
            <td style="width: 80%;"><strong>{{ $data->judul }}</strong></td>
        </tr>
        <tr>
            <th>Latar Belakang / Alasan</th>
            <td>{!! nl2br(e($data->latar_belakang)) !!}</td>
        </tr>
        <tr>
            <th>Kondisi Saat Ini</th>
            <td>{!! nl2br(e($data->kondisi_saat_ini)) !!}</td>
        </tr>
        <tr>
            <th>Kondisi Yang Diinginkan</th>
            <td>{!! nl2br(e($data->kondisi_yang_diinginkan)) !!}</td>
        </tr>
    </table>

    <!-- C. DAMPAK PERUBAHAN -->
    <div class="section-title">C. Estimasi Dampak Perubahan</div>
    @php
        $dampak = is_array($data->dampak) ? $data->dampak : (json_decode($data->dampak, true) ?: []);
        $dampakLower = array_map('strtolower', $dampak);
    @endphp
    <table class="data-table">
        <tr>
            <td style="width: 25%; padding: 8px;">
                <span class="checkbox-box">&nbsp;{!! in_array('database', $dampakLower) ? '✓' : '&nbsp;' !!}&nbsp;</span> Database
            </td>
            <td style="width: 25%; padding: 8px;">
                <span class="checkbox-box">&nbsp;{!! in_array('frontend', $dampakLower) ? '✓' : '&nbsp;' !!}&nbsp;</span> Frontend
            </td>
            <td style="width: 25%; padding: 8px;">
                <span class="checkbox-box">&nbsp;{!! in_array('backend', $dampakLower) ? '✓' : '&nbsp;' !!}&nbsp;</span> Backend
            </td>
            <td style="width: 25%; padding: 8px;">
                <span class="checkbox-box">&nbsp;{!! in_array('api', $dampakLower) ? '✓' : '&nbsp;' !!}&nbsp;</span> API
            </td>
        </tr>
        <tr>
            <td style="padding: 8px;">
                <span class="checkbox-box">&nbsp;{!! in_array('report', $dampakLower) ? '✓' : '&nbsp;' !!}&nbsp;</span> Report
            </td>
            <td style="padding: 8px;">
                <span class="checkbox-box">&nbsp;{!! in_array('mobile', $dampakLower) ? '✓' : '&nbsp;' !!}&nbsp;</span> Mobile
            </td>
            <td style="padding: 8px;">
                <span class="checkbox-box">&nbsp;{!! in_array('integrasi', $dampakLower) ? '✓' : '&nbsp;' !!}&nbsp;</span> Integrasi
            </td>
            <td style="padding: 8px;"></td>
        </tr>
    </table>

    <!-- D. ANALISA TEKNIS IT -->
    <div class="section-title">D. Analisa Teknis & Estimasi Pengerjaan (IT Department Only)</div>
    @php
        $isOverdue = false;
        $estDays = 1;
        $actualDays = 0;
        if ($data->tanggal_development && $data->estimasi_pengerjaan) {
            $estStr = strtolower($data->estimasi_pengerjaan);
            preg_match('/\d+/', $estStr, $matches);
            $val = isset($matches[0]) ? (int)$matches[0] : 0;
            
            if (strpos($estStr, 'hari') !== false) {
                $estDays = $val;
            } elseif (strpos($estStr, 'jam') !== false) {
                $estDays = ceil($val / 8);
            } else {
                $estDays = $val ?: 1;
            }
            
            $start = new \DateTime($data->tanggal_development);
            $end = $data->tanggal_testing ? new \DateTime($data->tanggal_testing) : new \DateTime();
            
            $diff = $start->diff($end);
            $actualDays = $diff->days + 1;
            
            $isOverdue = $actualDays > $estDays;
        }
    @endphp
    <table class="data-table">
        <tr>
            <th>Analisa Teknis IT</th>
            <td colspan="3">{!! $data->analisa_it ? nl2br(e($data->analisa_it)) : '<em>Belum dianalisa</em>' !!}</td>
        </tr>
        <tr>
            <th>Tingkat Kesulitan</th>
            <td>{{ $data->tingkat_kesulitan ?: '-' }}</td>
            <th>Estimasi Jam Kerja</th>
            <td {!! $isOverdue ? 'style="color: red; font-weight: bold;"' : '' !!}>
                {{ $data->estimasi_pengerjaan ?: '-' }}
                @if($isOverdue)
                    <br><span style="font-size: 8px; color: red; font-weight: normal;">(Overdue: Pengerjaan {{ $actualDays }} Hari > Estimasi {{ $estDays }} Hari)</span>
                @endif
            </td>
        </tr>
        <tr>
            <th>Risiko Dampak</th>
            <td>{{ $data->risiko ?: '-' }}</td>
            <th>Developer PIC</th>
            <td><strong>{{ $data->developer_pic ?: '-' }}</strong></td>
        </tr>
    </table>

    <!-- E. TANDA TANGAN / PERSETUJUAN (SIGN-OFF) -->
    <div class="section-title">E. Lembar Persetujuan (Sign-off)</div>
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-title">Diajukan Oleh (Pemohon)</div>
                <br><br><br>
                <div class="signature-name">{{ $data->pemohon }}</div>
                <div class="signature-date">Tgl: {{ \Carbon\Carbon::parse($data->tanggal_permintaan)->format('d-m-Y') }}</div>
            </td>
            <td>
                <div class="signature-title">Dianalisa & Disetujui IT</div>
                <br><br><br>
                <div class="signature-name">{{ $data->disetujui_it_by ?: '.........................' }}</div>
                <div class="signature-date">
                    @if($data->disetujui_it_at)
                        Tgl: {{ \Carbon\Carbon::parse($data->disetujui_it_at)->format('d-m-Y H:i') }}
                    @else
                        Tgl: -
                    @endif
                </div>
            </td>
            <td>
                <div class="signature-title">Disetujui & Rilis (UAT)</div>
                <br><br><br>
                <div class="signature-name">{{ $data->disetujui_user_by ?: '.........................' }}</div>
                <div class="signature-date">
                    @if($data->disetujui_user_at)
                        Tgl: {{ \Carbon\Carbon::parse($data->disetujui_user_at)->format('d-m-Y H:i') }}
                    @else
                        Tgl: -
                    @endif
                </div>
            </td>
        </tr>
    </table>

    @if($data->lampiran)
        @php
            $extension = strtolower(pathinfo($data->lampiran, PATHINFO_EXTENSION));
            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
        @endphp
        @if($isImage)
            <pagebreak />
            <div class="section-title">F. Lampiran Gambar (Attachment)</div>
            <div style="text-align: center; margin-top: 15px;">
                <img src="{{ public_path('change_requests/' . $data->lampiran) }}" style="max-width: 100%; max-height: 700px; border: 1px solid #ddd; padding: 5px;" />
                <div style="margin-top: 10px; font-size: 10px; color: #666;">Nama File: {{ $data->lampiran }}</div>
            </div>
        @endif
    @endif

</body>
</html>
