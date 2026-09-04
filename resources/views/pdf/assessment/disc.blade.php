<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    @include('pdf.assessment._styles_disc')
</head>
<body>
@php
    $profiles = $disc_detail['profiles'] ?? [];
    $description = $disc_detail['description'] ?? null;
    $jobs = $disc_detail['jobs'] ?? [];
    $scoreScale = $disc_detail['score_scale'] ?? 8;
    $lineTags = [
        1 => 'Grafik 1 (Most)',
        2 => 'Grafik 2 (Least)',
        3 => 'Grafik 3 (Change)',
    ];

    $chartProfiles = array_values(array_filter($profiles, function ($profile) {
        return !empty($profile['scores']);
    }));
@endphp

<div class="doc-title">Laporan Hasil Assessment DISC</div>
<p class="doc-subtitle">Interpretasi kepribadian dan rekomendasi pekerjaan kandidat</p>

<hr class="doc-divider" />

<table class="info-table">
    <tr>
        <td class="info-label">Nama Kandidat</td>
        <td class="info-value">: {{ $candidate_name }}</td>
    </tr>
    <tr>
        <td class="info-label">Jenis Tes</td>
        <td class="info-value">: {{ $category_name }}</td>
    </tr>
    @if(!empty($completed_at))
    <tr>
        <td class="info-label">Tanggal Selesai</td>
        <td class="info-value">: {{ $completed_at }}</td>
    </tr>
    @endif
</table>

<table class="disc-layout" cellpadding="0" cellspacing="0">
    <tr>
        <td class="disc-col-left">
            <table class="heading-table">
                <tr><td class="section-heading">Gambaran Karakter</td></tr>
            </table>

            @forelse($profiles as $profile)
                @php $line = (int) ($profile['line'] ?? 0); @endphp
                <table class="profile-card">
                    <tr>
                        <td class="profile-label">{{ $profile['title'] ?? ('Grafik ' . $line) }}</td>
                        <td class="profile-tag">{{ $lineTags[$line] ?? ('Grafik ' . $line) }}</td>
                    </tr>
                    <tr>
                        <td class="profile-body" colspan="2">
                            <div class="profile-pattern">{{ $profile['pattern'] ?? 'Pattern tidak tersedia' }}</div>

                            @if(!empty($profile['behaviours']))
                                <table class="behaviour-table">
                                    @foreach($profile['behaviours'] as $behaviour)
                                        <tr>
                                            <td class="behaviour-bullet">&bull;</td>
                                            <td>{{ $behaviour }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @else
                                <p class="empty-note">Detail perilaku tidak tersedia.</p>
                            @endif
                        </td>
                    </tr>
                </table>
            @empty
                <p class="empty-note">Profil DISC tidak tersedia.</p>
            @endforelse
        </td>

        <td class="disc-col-right">
            <table class="heading-table">
                <tr><td class="section-heading">Deskripsi Kepribadian</td></tr>
            </table>

            <table class="quote-card">
                <tr>
                    <td>
                        <p class="content-text">{{ $description ?: 'Deskripsi kepribadian tidak tersedia untuk hasil tes ini.' }}</p>
                    </td>
                </tr>
            </table>

            <table class="heading-table heading-table-spaced">
                <tr><td class="section-heading">Job Match (Rekomendasi Pekerjaan)</td></tr>
            </table>

            @if(!empty($jobs))
                <table class="job-table">
                    @foreach($jobs as $job)
                        <tr>
                            <td class="job-num">{{ $loop->iteration }}</td>
                            <td class="job-name">{{ $job }}</td>
                        </tr>
                    @endforeach
                </table>
            @else
                <p class="empty-note">Rekomendasi pekerjaan tidak tersedia.</p>
            @endif
        </td>
    </tr>
</table>

@if(!empty($chartProfiles))
<pagebreak />

@php
    $situationLabels = [
        1 => 'Saat tampil di muka umum / wawancara',
        2 => 'Saat mendapat tekanan',
        3 => 'Kepribadian kerja yang lebih menetap',
    ];

    $decisionRows = [];
    foreach ($chartProfiles as $profile) {
        $line = (int) ($profile['line'] ?? 0);
        $dominant = null;
        $dominantAbs = -1;
        $scale = max(1, (float) $scoreScale);

        foreach ($profile['scores'] as $score) {
            $value = (float) ($score['value'] ?? 0);
            $clamped = max(-$scale, min($scale, $value));
            if (abs($clamped) > $dominantAbs) {
                $dominantAbs = abs($clamped);
                $mapped = max(0, min(100, (int) round(($clamped + $scale) / (2 * $scale) * 100)));
                if ($mapped >= 75) {
                    $level = 'Sangat tinggi';
                } elseif ($mapped >= 63) {
                    $level = 'Tinggi';
                } elseif ($mapped > 37) {
                    $level = 'Seimbang';
                } elseif ($mapped > 25) {
                    $level = 'Rendah';
                } else {
                    $level = 'Sangat rendah';
                }
                $dominant = [
                    'key' => $score['key'] ?? '',
                    'label' => $score['label'] ?? '',
                    'mapped' => $mapped,
                    'level' => $level,
                    'direction' => $clamped >= 0 ? 'tinggi' : 'rendah',
                ];
            }
        }

        $decisionRows[] = [
            'line' => $line,
            'tag' => $lineTags[$line] ?? ('Grafik ' . $line),
            'situation' => $situationLabels[$line] ?? '',
            'pattern' => $profile['pattern'] ?? '-',
            'dominant' => $dominant,
        ];
    }
@endphp

<div class="doc-title">Grafik Skor DISC</div>


<hr class="doc-divider" />

<table class="info-table">
    <tr>
        <td class="info-label">Nama Kandidat</td>
        <td class="info-value">: {{ $candidate_name }}</td>
    </tr>
    <tr>
        <td class="info-label">Cara baca</td>
        <td class="info-value">: Pita biru di tengah = netral (Segmen 4). Titik di atas pita = menonjol. Titik di bawah pita = tidak menonjol. Bentuk garis D-I-S-C adalah profil kandidat.</td>
    </tr>
</table>

<table class="heading-table">
    <tr><td class="section-heading">Ringkasan untuk Keputusan</td></tr>
</table>

<table class="decision-table">
    <tr>
        <th>Situasi</th>
        <th>Pola</th>
        <th>Dimensi terkuat</th>
        <th class="text-center">Intensitas</th>
    </tr>
    @foreach($decisionRows as $row)
        <tr class="{{ (int) $row['line'] === 3 ? 'decision-primary' : '' }}">
            <td>
                <strong>{{ $row['tag'] }}</strong>
                @if((int) $row['line'] === 3)
                    <span class="chart-primary-badge">Utama</span>
                @endif
                <br>
                <span class="decision-sub">{{ $row['situation'] }}</span>
            </td>
            <td class="decision-pattern">{{ $row['pattern'] }}</td>
            <td>
                @if($row['dominant'])
                    <strong>{{ $row['dominant']['key'] }}</strong> {{ $row['dominant']['label'] }}
                @else
                    -
                @endif
            </td>
            <td class="text-center">
                @if($row['dominant'])
                    <strong>{{ $row['dominant']['mapped'] }}%</strong>
                    <div class="decision-sub">{{ $row['dominant']['level'] }}</div>
                @else
                    -
                @endif
            </td>
        </tr>
    @endforeach
</table>

<p class="chart-page-note">
    Grafik 3 (kiri, paling relevan untuk keputusan masuk) = kepribadian kerja yang menetap.
    Grafik 1 = kesan di muka umum. Grafik 2 = reaksi saat tertekan.
    Posisi titik memakai skor D/I/S/C dari rule DISC kami (bukan angka mentah soal).
</p>

@if(!empty($disc_detail['chart_image']))
    <div class="disc-profile-wrap">
        <img src="{{ $disc_detail['chart_image'] }}" width="100%" alt="Grafik profil DISC" />
    </div>
@else
    @foreach($chartProfiles as $profile)
        @php $line = (int) ($profile['line'] ?? 0); @endphp
        <table class="chart-block chart-block-wide {{ $line === 3 ? 'chart-block-primary' : '' }}">
            <tr>
                <td class="chart-head" colspan="1">
                    <table class="chart-head-row" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="chart-head-title">
                                {{ $lineTags[$line] ?? ('Grafik ' . $line) }}
                                @if($line === 3)
                                    <span class="chart-primary-badge">Utama untuk keputusan</span>
                                @endif
                            </td>
                            <td class="chart-head-tag">{{ $profile['pattern'] ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="chart-body">
                    @include('pdf.assessment._disc_chart', [
                        'scores' => $profile['scores'],
                        'scale' => $scoreScale,
                    ])
                </td>
            </tr>
        </table>
    @endforeach
@endif

<table class="chart-legend">
    <tr>
        <td><strong>D</strong> Dominance &mdash; tegas, ambil kendali</td>
        <td><strong>I</strong> Influence &mdash; komunikatif, memengaruhi orang</td>
        <td><strong>S</strong> Steadiness &mdash; tenang, stabil, setia proses</td>
        <td><strong>C</strong> Compliance &mdash; teliti, patuh aturan</td>
    </tr>
</table>
@endif
</body>
</html>
