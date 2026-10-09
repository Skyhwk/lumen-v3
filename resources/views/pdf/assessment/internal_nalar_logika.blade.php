<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            line-height: 1.35;
        }
        .title {
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            margin: 0 0 3px;
        }
        .subtitle {
            text-align: center;
            font-size: 9pt;
            color: #444;
            margin: 0 0 8px;
        }
        .meta-line { margin: 0; font-size: 9pt; }
        .meta-label { font-weight: bold; display: inline-block; min-width: 118px; }
        .section-meta {
            font-size: 8pt;
            color: #555;
            margin: 0 0 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #ddd;
        }
        .exam-item {
            margin-bottom: 8px;
            page-break-inside: avoid;
        }
        .exam-stem {
            margin-bottom: 3px;
            text-align: justify;
            font-size: 9pt;
        }
        .exam-option {
            margin: 1px 0;
            padding: 1px 3px 1px 12px;
            text-align: justify;
            font-size: 9pt;
        }
        .option-correct { color: #047857; }
        .option-wrong { color: #b91c1c; }
        .option-selected { font-weight: bold; }
        .correct-hint {
            font-style: italic;
            font-weight: normal;
        }
        .mark-correct { color: #047857; }
        .mark-wrong { color: #b91c1c; }
    </style>
</head>
<body>
    <div class="title">Laporan Jawaban Assessment Internal</div>
    <div class="subtitle">Sesi {{ $session_title }}</div>

    <div class="meta-line"><span class="meta-label">Nama Peserta</span>: {{ $participant_name }}</div>
    <div class="meta-line"><span class="meta-label">Email</span>: {{ $participant_email }}</div>
    <div class="meta-line"><span class="meta-label">Assessment</span>: {{ $assessment_name }}</div>
    <div class="meta-line"><span class="meta-label">Waktu Assessment</span>: {{ $assessment_time }}</div>

    <div class="section-meta">
        @if($score !== null)
            Skor {{ number_format($score, $score == (int) $score ? 0 : 2) }}/100
        @endif
        @if(!empty($session_completed_at) && $session_completed_at !== '-')
            @if($score !== null) &nbsp;·&nbsp; @endif
            Selesai {{ $session_completed_at }}
        @endif
    </div>

    @foreach ($questions as $question)
        <div class="exam-item">
            <div class="exam-stem">
                {{ $question['no'] }}. {{ $question['question'] }}@if(!empty($question['answered_correctly']))<span class="mark-correct"> (Benar)</span>@else<span class="mark-wrong"> (Salah)</span>@endif
            </div>
            @foreach ($question['options'] as $option)
                @php
                    $classes = ['exam-option'];
                    $answeredCorrectly = !empty($question['answered_correctly']);
                    $showHint = !$answeredCorrectly && !empty($option['is_correct']);

                    if (!empty($option['is_wrong'])) {
                        $classes[] = 'option-wrong';
                        $classes[] = 'option-selected';
                    } elseif (!empty($option['is_correct'])) {
                        $classes[] = 'option-correct';
                        if ($answeredCorrectly && !empty($option['is_selected'])) {
                            $classes[] = 'option-selected';
                        }
                    } elseif (!empty($option['is_selected'])) {
                        $classes[] = 'option-selected';
                    }

                    $letter = $option['letter'] ?? '';
                @endphp
                <div class="{{ implode(' ', $classes) }}">
                    @if($letter !== '')
                        {{ $letter }}.
                    @endif
                    {{ $option['label'] }}@if($showHint)<span class="correct-hint"> (jawaban benar)</span>@endif
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
