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
    $lineTags = [
        1 => 'Grafik 1 (Most)',
        2 => 'Grafik 2 (Least)',
        3 => 'Grafik 3 (Change)',
    ];
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
        <td class="disc-col-left" valign="top">
            <h6 class="section-heading">Gambaran Karakter</h6>

            @forelse($profiles as $profile)
                @php $line = (int) ($profile['line'] ?? 0); @endphp
                <div class="profile-block">
                    <div class="profile-label">{{ $profile['title'] ?? ('Grafik ' . $line) }}</div>
                    <div class="profile-tag">{{ $lineTags[$line] ?? ('Grafik ' . $line) }}</div>
                    <div class="profile-pattern">{{ $profile['pattern'] ?? 'Pattern tidak tersedia' }}</div>

                    @if(!empty($profile['behaviours']))
                        <ul class="behaviour-list">
                            @foreach($profile['behaviours'] as $behaviour)
                                <li>{{ $behaviour }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="empty-note">Detail perilaku tidak tersedia.</p>
                    @endif
                </div>
            @empty
                <p class="empty-note">Profil DISC tidak tersedia.</p>
            @endforelse
        </td>

        <td class="disc-col-right" valign="top">
            <div class="content-block">
                <h6 class="section-heading">Deskripsi Kepribadian</h6>
                <p class="content-text">{{ $description ?: 'Deskripsi kepribadian tidak tersedia untuk hasil tes ini.' }}</p>
            </div>

            <div class="content-block">
                <h6 class="section-heading section-heading-spaced">Job Match</h6>
                @if(!empty($jobs))
                    <ol class="job-list">
                        @foreach($jobs as $job)
                            <li>{{ $job }}</li>
                        @endforeach
                    </ol>
                @else
                    <p class="empty-note">Rekomendasi pekerjaan tidak tersedia.</p>
                @endif
            </div>
        </td>
    </tr>
</table>
</body>
</html>
