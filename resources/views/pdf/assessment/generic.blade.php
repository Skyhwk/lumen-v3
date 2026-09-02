<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8">@include('pdf.assessment._styles')</head>
<body>
@php
    $rows = $generic_detail['rows'] ?? [];
    $score = $generic_detail['score'] ?? null;
    $correct = $generic_detail['correct_answers'] ?? null;
    $total = $generic_detail['total_questions'] ?? count($rows);
@endphp

<div class="doc-title">Laporan Hasil Tes {{ $category_name }}</div>
<p class="doc-subtitle">{{ $summary['summary_text'] ?? 'Ringkasan hasil assessment' }}</p>

<hr class="doc-divider" />

<table class="info-table">
    <tr><td class="info-label">Nama Kandidat</td><td class="info-value">: {{ $candidate_name }}</td></tr>
    <tr><td class="info-label">Jenis Tes</td><td class="info-value">: {{ $category_name }}</td></tr>
    @if(!empty($completed_at))
    <tr><td class="info-label">Tanggal Selesai</td><td class="info-value">: {{ $completed_at }}</td></tr>
    @endif
</table>

@if($score !== null || $correct !== null)
<div class="score-box">
    @if($score !== null)
        <div>Skor: <span class="score-value">{{ $score }}/100</span></div>
    @endif
    @if($correct !== null && $total > 0)
        <div style="margin-top:4px;">Jawaban Benar: <strong>{{ $correct }}/{{ $total }}</strong></div>
    @endif
</div>
@endif

<div class="section-title">Detail Soal &amp; Jawaban</div>
@if(empty($rows))
    <p class="text-muted">Detail soal tidak tersedia.</p>
@else
<div class="qa-list">
    @foreach($rows as $row)
    <div class="qa-item">
        <div class="qa-item-header">Soal {{ $row['no'] ?? '-' }}</div>
        <div class="qa-line">
            <span class="qa-prefix">Q:</span>
            <span>{{ $row['question'] ?? '-' }}</span>
        </div>
        <div class="qa-line">
            <span class="qa-prefix">J:</span>
            <span>{{ $row['selected'] ?? '-' }}</span>
            @if($row['is_correct'] === true)
                <span class="status-label">(Benar)</span>
            @elseif($row['is_correct'] === false)
                <span class="status-label">(Salah)</span>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endif
</body>
</html>
