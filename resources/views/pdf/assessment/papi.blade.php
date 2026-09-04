<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8">@include('pdf.assessment._styles')</head>
<body>
@php $rows = $papi_detail['rows'] ?? []; @endphp

<div class="doc-title">Laporan Hasil PAPI KOSTICK</div>
<p class="doc-subtitle">Analisis dimensi attitude &amp; work style dalam lingkungan kerja</p>

<hr class="doc-divider" />

<table class="info-table">
    <tr><td class="info-label">Nama Kandidat</td><td class="info-value">: {{ $candidate_name }}</td></tr>
    <tr><td class="info-label">Jenis Tes</td><td class="info-value">: {{ $category_name }}</td></tr>
    @if(!empty($completed_at))
    <tr><td class="info-label">Tanggal Selesai</td><td class="info-value">: {{ $completed_at }}</td></tr>
    @endif
</table>

<div class="section-title">Hasil Evaluasi PAPI Kostick</div>
@if(empty($rows))
    <p class="text-muted">Data PAPI Kostick tidak tersedia.</p>
@else
<table class="data-table">
    <thead>
        <tr>
            <th class="text-center" style="width:5%;">No</th>
            <th>Attitude &amp; Work Style</th>
            <th class="text-center" style="width:10%;">Code</th>
            <th class="text-center" style="width:10%;">Score</th>
            <th>Interpretation</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
        <tr>
            <td class="text-center">{{ $row['no'] ?? '-' }}</td>
            <td>{{ $row['role'] ?? '-' }}</td>
            <td class="text-center"><span class="papi-code">{{ $row['code'] ?? '-' }}</span></td>
            <td class="text-center">{{ $row['score'] ?? 0 }}</td>
            <td>{{ $row['interpretation'] ?? '-' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif
</body>
</html>
