<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    @include('pdf.assessment._styles')
    <style>
        .info-table { font-size: 9.5pt; margin-bottom: 10px; }
        .info-table td { padding: 2px 0; }
        .info-label { width: 120px; }

        .counseling-notes {
            font-size: 9.5pt;
            line-height: 1.45;
            text-align: justify;
        }
        .counseling-notes p {
            margin: 0 0 6px;
            font-size: 9.5pt !important;
            line-height: 1.45 !important;
        }
        .counseling-notes h1,
        .counseling-notes h2,
        .counseling-notes h3,
        .counseling-notes h4,
        .counseling-notes h5,
        .counseling-notes h6 {
            font-family: "Times New Roman", Times, serif !important;
            font-weight: bold !important;
            color: #111 !important;
            text-align: left !important;
            line-height: 1.3 !important;
            margin: 11px 0 5px !important;
            padding: 0 0 2px !important;
            border-bottom: 0.4pt solid #999;
            page-break-after: avoid;
        }
        .counseling-notes h1 { font-size: 11pt !important; }
        .counseling-notes h2 { font-size: 10.5pt !important; }
        .counseling-notes h3,
        .counseling-notes h4,
        .counseling-notes h5,
        .counseling-notes h6 { font-size: 10pt !important; }

        .counseling-notes ul,
        .counseling-notes ol {
            margin: 2px 0 8px;
            padding-left: 16px;
        }
        .counseling-notes li {
            margin: 0 0 5px;
            font-size: 9.5pt !important;
            line-height: 1.45 !important;
        }
        .counseling-notes blockquote {
            margin: 4px 0 8px;
            padding: 5px 8px;
            border-left: 2px solid #888;
            background: #f7f7f7;
            font-size: 9pt !important;
            line-height: 1.4 !important;
        }
        .counseling-notes hr {
            border: 0;
            border-top: 0.4pt solid #ccc;
            margin: 8px 0;
        }
        .counseling-notes strong,
        .counseling-notes b { font-weight: bold; }
        .counseling-notes em,
        .counseling-notes i { font-style: italic; }
        .counseling-notes span,
        .counseling-notes div {
            font-size: inherit !important;
        }

        .schedule-note {
            margin-top: 14px;
            padding-top: 6px;
            border-top: 0.4pt solid #ccc;
            font-size: 8.5pt;
            color: #444;
            line-height: 1.4;
        }
        .schedule-note-label {
            font-weight: bold;
            color: #222;
        }
    </style>
</head>
<body>
@php
    $notes = trim((string) ($counseling['result_notes'] ?? ''));
    $notesIsHtml = $notes !== '' && preg_match('/<[^>]+>/', $notes);
    if ($notesIsHtml) {
        $notes = preg_replace_callback('/\sstyle=("|\')(.*?)\1/is', function ($match) {
            $style = preg_replace(
                '/(?:^|;)\s*(?:font-size|font-family|line-height|margin|padding|letter-spacing)\s*:[^;]*/i',
                '',
                $match[2]
            );
            $style = trim($style, "; \t\n\r");
            return $style === '' ? '' : ' style="' . $style . '"';
        }, $notes);
        $notes = preg_replace('/<\/?font[^>]*>/i', '', $notes);
    }
@endphp

<div class="doc-title">Hasil Konseling Karyawan</div>
<p class="doc-subtitle">Catatan sesi konseling penyesuaian karyawan</p>
<hr class="doc-divider" />

<table class="info-table">
    <tr><td class="info-label">Nama Karyawan</td><td class="info-value">: {{ $employee_name }}</td></tr>
    <tr><td class="info-label">Tanggal</td><td class="info-value">: {{ $counseling['scheduled_date'] ?? '-' }}</td></tr>
    <tr><td class="info-label">Waktu</td><td class="info-value">: {{ isset($counseling['scheduled_time']) ? substr((string) $counseling['scheduled_time'], 0, 5) : '-' }}</td></tr>
    <tr><td class="info-label">Tipe</td><td class="info-value">: {{ $counseling['type'] ?? '-' }}</td></tr>
    <tr><td class="info-label">Konselor</td><td class="info-value">: {{ $counseling['counselor_name'] ?? '-' }}</td></tr>
    <tr><td class="info-label">Lokasi / Link</td><td class="info-value">: {{ $counseling['location'] ?? $counseling['meeting_link'] ?? '-' }}</td></tr>
    <tr><td class="info-label">Status</td><td class="info-value">: {{ $counseling['status'] ?? '-' }}</td></tr>
</table>

<div class="section-title">Catatan Hasil Konseling</div>
@if($notes === '')
    <p style="line-height:1.45;font-size:9.5pt;">Catatan hasil konseling belum diisi.</p>
@elseif($notesIsHtml)
    <div class="counseling-notes">{!! $notes !!}</div>
@else
    <p style="white-space:pre-wrap;line-height:1.45;font-size:9.5pt;">{{ $notes }}</p>
@endif

@if(!empty($counseling['description']))
<div class="schedule-note">
    <span class="schedule-note-label">Deskripsi jadwal:</span>
    {{ $counseling['description'] }}
</div>
@endif
</body>
</html>
