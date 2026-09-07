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
    usort($chartProfiles, function ($a, $b) {
        return ((int) ($a['line'] ?? 0)) <=> ((int) ($b['line'] ?? 0));
    });

    $situationLabels = [
        1 => 'Saat tampil di muka umum / wawancara',
        2 => 'Saat mendapat tekanan',
        3 => 'Kepribadian kerja yang lebih menetap',
    ];

    $formatScore = function ($value) {
        $n = (float) $value;
        $rounded = abs($n - round($n)) < 0.05 ? (int) round($n) : round($n, 1);
        return $rounded > 0 ? '+' . $rounded : (string) $rounded;
    };

    $patternLabel = function ($profile) {
        $raw = trim((string) ($profile['pattern'] ?? ''));
        if ($raw === '' || strcasecmp($raw, 'pattern tidak tersedia') === 0) {
            return 'PATTERN TIDAK TERSEDIA';
        }
        return strtoupper($raw);
    };

    $decisionRows = [];
    foreach ($chartProfiles as $profile) {
        $line = (int) ($profile['line'] ?? 0);
        $dominant = null;
        $bestClamped = null;
        $scale = max(1, (float) $scoreScale);

        foreach ($profile['scores'] as $score) {
            if (!is_array($score)) {
                continue;
            }

            $value = (float) ($score['value'] ?? 0);
            $clamped = max(-$scale, min($scale, $value));

            if ($bestClamped !== null && $clamped <= $bestClamped) {
                continue;
            }

            $bestClamped = $clamped;
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
                'value' => $clamped,
                'above_neutral' => $clamped > 0,
            ];
        }

        $decisionRows[] = [
            'line' => $line,
            'tag' => $lineTags[$line] ?? ('Grafik ' . $line),
            'situation' => $situationLabels[$line] ?? '',
            'pattern' => $patternLabel($profile),
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
</table>

<table class="guide-box">
    <tr>
        <td>
            <div class="guide-title">Panduan Membaca hasil DISC</div>
            <p>
                Grafik 3 merupakan grafik utama yang digunakan dalam pengambilan keputusan.
                Grafik 1 dan Grafik 2 hanya digunakan sebagai informasi pendukung mengenai sikap pada situasi yang berbeda.
            </p>
            <ol>
                <li>Lihat pita biru di tengah sebagai area netral.</li>
                <li>Titik di atas pita menunjukan kecenderungan sifat yang lebih menonjol.</li>
                <li>Titik di bawah pita menunjukan kecenderungan sifat yang kurang menonjol.</li>
                <li>Faktor D,I,S, atau C dengan titik tertinggi merupakan faktor kecenderungan yang paling dominan.</li>
                <li>Nama pola di atas grafik merupakan ringkasan gaya perilaku.</li>
                <li>PATTERN TIDAK TERSEDIA berarti kombinasi titik tidak memiliki nama pola baku. Hasil test tetap dapat di gunakan.</li>
            </ol>
            <p>
                <strong>Grafik 1</strong> = sikap saat tampil atau wawancara.
                <strong>Grafik 2</strong> = sikap saat tertekan.
                <strong>Grafik 3</strong> = gaya kerja yang menetap, dipakai untuk keputusan.
            </p>
            <p>
                <strong>D</strong> tegas.
                <strong>I</strong> komunikatif.
                <strong>S</strong> tenang dan stabil.
                <strong>C</strong> teliti dan patuh aturan.
            </p>
        </td>
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
                    @if(empty($row['dominant']['above_neutral']))
                        <div class="decision-sub">titik tertinggi, masih di bawah garis netral</div>
                    @endif
                @else
                    -
                @endif
            </td>
            <td class="text-center">
                @if($row['dominant'])
                    <strong>{{ $row['dominant']['mapped'] }}%</strong>
                    <div class="decision-sub">{{ $row['dominant']['level'] }}</div>
                    <div class="decision-sub">skor {{ $formatScore($row['dominant']['value']) }}</div>
                @else
                    -
                @endif
            </td>
        </tr>
    @endforeach
</table>

<p class="chart-page-note">
    Ringkasan: buka Grafik 3, lihat huruf yang titiknya paling atas, lalu baca nama polanya.
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
                            <td class="chart-head-tag">{{ $patternLabel($profile) }}</td>
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
