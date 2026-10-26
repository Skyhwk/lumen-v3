<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    @include('pdf.assessment._styles')
    <style>
        .section-title-left {
            text-align: left;
        }
    </style>
</head>
<body>
@php
    $items = $kpi['items'] ?? [];
    $managerName = trim((string) ($manager_name ?? ''));
@endphp

<div class="doc-title">KPI Manager / Pengaju</div>
<p class="doc-subtitle">Penilaian kinerja oleh manager untuk penyesuaian karyawan</p>
<hr class="doc-divider" />

<table class="info-table">
    <tr><td class="info-label">Nama Karyawan</td><td class="info-value">: {{ $employee_name }}</td></tr>
    @if($managerName !== '')
        <tr><td class="info-label">Manager Pengaju</td><td class="info-value">: {{ $managerName }}</td></tr>
    @endif
    <tr><td class="info-label">Interpretasi</td><td class="info-value">: {{ $kpi['interpretation'] ?? '-' }}</td></tr>
</table>

<div class="score-box">
    Rata-rata skor: <strong>{{ $kpi['total_score_avg'] ?? '-' }}</strong>
    &nbsp;|&nbsp; Nilai akhir: <strong>{{ $kpi['total_final_score'] ?? '-' }}</strong>
    &nbsp;|&nbsp; Interpretasi: <strong>{{ $kpi['interpretation'] ?? '-' }}</strong>
</div>

<div class="section-title">Rincian Kriteria KPI</div>
<table class="data-table" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <th align="center" style="width:36px;">No</th>
        <th align="left">Kriteria KPI</th>
        <th align="center" style="width:70px;">Bobot</th>
        <th align="left">Indikator Penilaian</th>
        <th align="center" style="width:56px;">Skor</th>
        <th align="center" style="width:70px;">Nilai Akhir</th>
    </tr>
    @forelse($items as $index => $item)
        @php
            $weight = $item['weight_pct'] ?? $item['weight'] ?? 0;
            $criteria = $item['criteria_name'] ?? $item['criteria'] ?? '-';
        @endphp
        <tr>
            <td align="center">{{ $item['criteria_no'] ?? ($index + 1) }}</td>
            <td>{{ $criteria }}</td>
            <td align="center">{{ number_format((float) $weight, 2) }}%</td>
            <td>{{ $item['indicator_text'] ?? '-' }}</td>
            <td align="center">{{ $item['score'] ?? '-' }}</td>
            <td align="center">{{ $item['final_score'] ?? '-' }}</td>
        </tr>
    @empty
        <tr><td colspan="6">Tidak ada data kriteria KPI.</td></tr>
    @endforelse
    @if(!empty($items))
        <tr>
            <td colspan="4" align="right"><strong>Total</strong></td>
            <td align="center"><strong>{{ $kpi['total_score_avg'] ?? 0 }}</strong></td>
            <td align="center"><strong>{{ $kpi['total_final_score'] ?? 0 }}</strong></td>
        </tr>
    @endif
</table>

@if(!empty($kpi['summary']))
    <div class="section-title section-title-left">Ringkasan Penilaian</div>
    <p style="white-space:pre-wrap;line-height:1.45;font-size:9.5pt;">{{ $kpi['summary'] }}</p>
@endif

@if(!empty($kpi['strengths']))
    <div class="section-title section-title-left">Kekuatan</div>
    <p style="white-space:pre-wrap;line-height:1.45;font-size:9.5pt;">{{ $kpi['strengths'] }}</p>
@endif

@if(!empty($kpi['improvements']))
    <div class="section-title section-title-left">Area Perbaikan</div>
    <p style="white-space:pre-wrap;line-height:1.45;font-size:9.5pt;">{{ $kpi['improvements'] }}</p>
@endif
</body>
</html>
