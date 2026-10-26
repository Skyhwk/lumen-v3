<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    @include('pdf.assessment._styles')
</head>
<body>
@php
    $summary = $attendance['summary'] ?? [];
    $monthly = $attendance['monthly'] ?? [];
    $daily = $attendance['daily'] ?? [];
@endphp

<div class="doc-title">Laporan Absensi Karyawan</div>
<p class="doc-subtitle">Rekap kehadiran 3 bulan terakhir</p>
<hr class="doc-divider" />

<table class="info-table">
    <tr><td class="info-label">Nama Karyawan</td><td class="info-value">: {{ $employee_name }}</td></tr>
    <tr><td class="info-label">Periode</td><td class="info-value">: {{ $attendance['period_label'] ?? '-' }}</td></tr>
    <tr><td class="info-label">Hari Hadir</td><td class="info-value">: {{ $summary['hadir'] ?? 0 }}</td></tr>
</table>

<div class="score-box">
    Hadir: <strong>{{ $summary['hadir'] ?? 0 }}</strong>
    &nbsp;|&nbsp; Tepat waktu: <strong>{{ $summary['tepat_waktu'] ?? 0 }}</strong>
    &nbsp;|&nbsp; Telat: <strong>{{ $summary['telat'] ?? 0 }}</strong>
    &nbsp;|&nbsp; Lembur: <strong>{{ $summary['lembur'] ?? 0 }}</strong>
</div>

<div class="section-title">Rekap per Bulan</div>
<table class="qa-grid" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:10pt;">
    <tr>
        <th align="left" style="border:0.6px solid #000;padding:5px;">Bulan</th>
        <th align="center" style="border:0.6px solid #000;padding:5px;">Hadir</th>
        <th align="center" style="border:0.6px solid #000;padding:5px;">Tepat Waktu</th>
        <th align="center" style="border:0.6px solid #000;padding:5px;">Telat</th>
        <th align="center" style="border:0.6px solid #000;padding:5px;">Lembur</th>
    </tr>
    @forelse($monthly as $month)
        @php $ms = $month['summary'] ?? []; @endphp
        <tr>
            <td style="border:0.6px solid #000;padding:5px;">{{ $month['label'] ?? '-' }}</td>
            <td align="center" style="border:0.6px solid #000;padding:5px;">{{ $ms['hadir'] ?? 0 }}</td>
            <td align="center" style="border:0.6px solid #000;padding:5px;">{{ $ms['tepat_waktu'] ?? 0 }}</td>
            <td align="center" style="border:0.6px solid #000;padding:5px;">{{ $ms['telat'] ?? 0 }}</td>
            <td align="center" style="border:0.6px solid #000;padding:5px;">{{ $ms['lembur'] ?? 0 }}</td>
        </tr>
    @empty
        <tr><td colspan="5" style="border:0.6px solid #000;padding:5px;">Data absensi per bulan belum tersedia.</td></tr>
    @endforelse
</table>

@if(!empty($daily))
<div class="section-title">Rincian Hari Kerja</div>
<table class="qa-grid" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:9pt;">
    <tr>
        <th align="left" style="border:0.6px solid #000;padding:4px;">Tanggal</th>
        <th align="left" style="border:0.6px solid #000;padding:4px;">Hari</th>
        <th align="center" style="border:0.6px solid #000;padding:4px;">Masuk</th>
        <th align="center" style="border:0.6px solid #000;padding:4px;">Keluar</th>
        <th align="left" style="border:0.6px solid #000;padding:4px;">Status</th>
    </tr>
    @foreach($daily as $row)
        <tr>
            <td style="border:0.6px solid #000;padding:4px;">{{ $row['tanggal'] ?? '-' }}</td>
            <td style="border:0.6px solid #000;padding:4px;">{{ $row['hari'] ?? '-' }}</td>
            <td align="center" style="border:0.6px solid #000;padding:4px;">{{ $row['jam_masuk'] ?? '-' }}</td>
            <td align="center" style="border:0.6px solid #000;padding:4px;">{{ $row['jam_keluar'] ?? '-' }}</td>
            <td style="border:0.6px solid #000;padding:4px;">{{ $row['status'] ?? '-' }}</td>
        </tr>
    @endforeach
</table>
@endif
</body>
</html>
