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

<div class="section-title">Soal &amp; Jawaban</div>
@if(empty($rows))
    <p class="text-muted">Detail soal tidak tersedia.</p>
@else
@php
    $half = (int) ceil(count($rows) / 2);
    $pairs = [];
    for ($i = 0; $i < $half; $i++) {
        $pairs[] = [$rows[$i] ?? null, $rows[$i + $half] ?? null];
    }
@endphp
<table class="qa-grid" width="100%" cellpadding="0" cellspacing="0">
    @foreach($pairs as $pair)
    <tr>
        <td width="50%" valign="top" style="border-right: 0.9px solid #000000; padding: 0 12px 8px 0;">
            @if(!empty($pair[0]))
            <div class="qa-item-header">Soal {{ $pair[0]['no'] ?? '-' }}</div>
            <div class="qa-line">
                <span class="qa-prefix">Q:</span> {{ $pair[0]['question'] ?? '-' }}
            </div>
            <div class="qa-line">
                <span class="qa-prefix">J:</span> {{ $pair[0]['selected'] ?? '-' }}
                @if(($pair[0]['is_correct'] ?? null) === true)
                    <span class="status-label">(Benar)</span>
                @elseif(($pair[0]['is_correct'] ?? null) === false)
                    <span class="status-label">(Salah)</span>
                @endif
            </div>
            @endif
        </td>
        <td width="50%" valign="top" style="padding: 0 0 8px 12px;">
            @if(!empty($pair[1]))
            <div class="qa-item-header">Soal {{ $pair[1]['no'] ?? '-' }}</div>
            <div class="qa-line">
                <span class="qa-prefix">Q:</span> {{ $pair[1]['question'] ?? '-' }}
            </div>
            <div class="qa-line">
                <span class="qa-prefix">J:</span> {{ $pair[1]['selected'] ?? '-' }}
                @if(($pair[1]['is_correct'] ?? null) === true)
                    <span class="status-label">(Benar)</span>
                @elseif(($pair[1]['is_correct'] ?? null) === false)
                    <span class="status-label">(Salah)</span>
                @endif
            </div>
            @endif
        </td>
    </tr>
    @endforeach
</table>
@endif
</body>
</html>
