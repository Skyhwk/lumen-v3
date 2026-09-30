@php
    use App\Services\SalaryAdjustmentEmailViewData as EmailData;
    $request = $bundle['request'] ?? [];
    $kpi = $bundle['kpi'] ?? null;
    if (is_object($kpi)) {
        $kpi = json_decode(json_encode($kpi), true);
    }
    $kpiItems = $kpi['items'] ?? [];
    $assessmentReport = $bundle['assessment_report'] ?? null;
    $attendance = $bundle['attendance'] ?? null;
    $counseling = $bundle['counseling'] ?? null;
    if (is_object($counseling)) {
        $counseling = json_decode(json_encode($counseling), true);
    }
    $logs = $bundle['logs'] ?? [];
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px 0;border:1px solid #e4e4e7;">
    <tr>
        <td style="padding:12px 16px;background-color:#18181b;">
            <p style="margin:0;font-size:14px;font-weight:700;color:#ffffff;">Detail Lengkap Evaluasi</p>
            <p style="margin:4px 0 0 0;font-size:12px;line-height:1.6;color:#d4d4d8;">
                KPI manager, assessment psikometri (DISC &amp; PAPI Kostick), absensi, konseling, dan timeline proses.
            </p>
        </td>
    </tr>
</table>

{{-- KPI --}}
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e4e4e7;margin:0 0 20px 0;">
    <tr>
        <td style="padding:10px 14px;background-color:#f4f4f5;border-bottom:1px solid #e4e4e7;">
            <strong style="font-size:13px;color:#18181b;">KPI Manager / Pengaju</strong>
        </td>
    </tr>
    <tr>
        <td style="padding:16px;">
            @if($kpi)
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 14px 0;">
                    <tr>
                        <td style="padding:8px 12px;border:1px solid #e4e4e7;background-color:#fafafa;width:33%;">
                            <span style="font-size:11px;color:#71717a;display:block;">Rata-rata Skor</span>
                            <strong style="font-size:16px;color:#18181b;">{{ $kpi['total_score_avg'] ?? '-' }}</strong>
                        </td>
                        <td style="padding:8px 12px;border:1px solid #e4e4e7;background-color:#fafafa;width:33%;">
                            <span style="font-size:11px;color:#71717a;display:block;">Nilai Akhir</span>
                            <strong style="font-size:16px;color:#18181b;">{{ $kpi['total_final_score'] ?? '-' }}</strong>
                        </td>
                        <td style="padding:8px 12px;border:1px solid #e4e4e7;background-color:#fafafa;width:34%;">
                            <span style="font-size:11px;color:#71717a;display:block;">Interpretasi</span>
                            <strong style="font-size:13px;color:#18181b;">{{ $kpi['interpretation'] ?? '-' }}</strong>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;font-size:12px;">
                    <tr style="background-color:#f4f4f5;">
                        <th align="center" style="padding:8px;border:1px solid #e4e4e7;width:36px;">No</th>
                        <th align="left" style="padding:8px;border:1px solid #e4e4e7;">Kriteria KPI</th>
                        <th align="center" style="padding:8px;border:1px solid #e4e4e7;width:70px;">Bobot</th>
                        <th align="left" style="padding:8px;border:1px solid #e4e4e7;min-width:180px;">Indikator Penilaian</th>
                        <th align="center" style="padding:8px;border:1px solid #e4e4e7;width:60px;">Skor</th>
                        <th align="center" style="padding:8px;border:1px solid #e4e4e7;width:70px;">Nilai Akhir</th>
                    </tr>
                    @forelse($kpiItems as $index => $item)
                        <tr>
                            <td align="center" style="padding:8px;border:1px solid #e4e4e7;vertical-align:top;">{{ $index + 1 }}</td>
                            <td style="padding:8px;border:1px solid #e4e4e7;vertical-align:top;font-weight:600;color:#18181b;">{{ $item['criteria_name'] ?? '-' }}</td>
                            <td align="center" style="padding:8px;border:1px solid #e4e4e7;vertical-align:top;">{{ number_format((float) ($item['weight_pct'] ?? 0), 2) }}%</td>
                            <td style="padding:8px;border:1px solid #e4e4e7;vertical-align:top;color:#3f3f46;line-height:1.55;">{{ $item['indicator_text'] ?? '-' }}</td>
                            <td align="center" style="padding:8px;border:1px solid #e4e4e7;vertical-align:top;">{{ $item['score'] ?? '-' }}</td>
                            <td align="center" style="padding:8px;border:1px solid #e4e4e7;vertical-align:top;font-weight:700;">{{ $item['final_score'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:10px;border:1px solid #e4e4e7;color:#71717a;">Tidak ada data KPI.</td></tr>
                    @endforelse
                    <tr style="background-color:#f4f4f5;font-weight:700;">
                        <td colspan="4" align="right" style="padding:8px;border:1px solid #e4e4e7;">Total</td>
                        <td align="center" style="padding:8px;border:1px solid #e4e4e7;">{{ $kpi['total_score_avg'] ?? 0 }}</td>
                        <td align="center" style="padding:8px;border:1px solid #e4e4e7;">{{ $kpi['total_final_score'] ?? 0 }}</td>
                    </tr>
                </table>

                @if(!empty($kpi['summary']))
                    <p style="margin:14px 0 0 0;font-size:13px;color:#3f3f46;line-height:1.65;"><strong>Ringkasan Penilaian:</strong><br>{!! nl2br(e($kpi['summary'])) !!}</p>
                @endif
                @if(!empty($kpi['strengths']))
                    <p style="margin:10px 0 0 0;font-size:13px;color:#3f3f46;line-height:1.65;"><strong>Kekuatan:</strong><br>{!! nl2br(e($kpi['strengths'])) !!}</p>
                @endif
                @if(!empty($kpi['improvements']))
                    <p style="margin:10px 0 0 0;font-size:13px;color:#3f3f46;line-height:1.65;"><strong>Area Perbaikan:</strong><br>{!! nl2br(e($kpi['improvements'])) !!}</p>
                @endif
            @else
                <p style="margin:0;font-size:13px;color:#71717a;">Data KPI belum tersedia.</p>
            @endif
        </td>
    </tr>
</table>

{{-- Assessment --}}
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e4e4e7;margin:0 0 20px 0;">
    <tr>
        <td style="padding:10px 14px;background-color:#f4f4f5;border-bottom:1px solid #e4e4e7;">
            <strong style="font-size:13px;color:#18181b;">Hasil Assessment Psikometri</strong>
            @if(($assessmentReport['total_score'] ?? null) !== null)
                <span style="float:right;font-size:12px;font-weight:700;color:#15803d;">Skor Total: {{ $assessmentReport['total_score'] }}%</span>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:16px;">
            @forelse(($assessmentReport['sessions'] ?? []) as $session)
                @php
                    $engine = $session['engine'] ?? null;
                    $discDetail = $session['disc_detail'] ?? null;
                    $papiDetail = $session['papi_detail'] ?? null;
                @endphp

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e4e4e7;margin:0 0 16px 0;">
                    <tr>
                        <td style="padding:10px 14px;background-color:#fafafa;border-bottom:1px solid #e4e4e7;">
                            <strong style="font-size:13px;color:#18181b;">{{ $session['category_name'] ?? 'Sesi Assessment' }}</strong>
                            @if(($session['score'] ?? null) !== null)
                                <span style="float:right;font-size:12px;color:#52525b;font-weight:600;">Skor: {{ $session['score'] }}</span>
                            @endif
                            <p style="margin:4px 0 0 0;font-size:11px;color:#71717a;">{{ $session['summary_text'] ?? 'Hasil tersedia' }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px;">
                            @if($engine === 'disc' && !empty($discDetail))
                                {{-- DISC Detail --}}
                                <p style="margin:0 0 10px 0;font-size:12px;font-weight:700;color:#18181b;text-transform:uppercase;letter-spacing:0.05em;">Interpretasi DISC</p>

                                @foreach(($discDetail['profiles'] ?? []) as $profile)
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e4e4e7;margin:0 0 10px 0;">
                                        <tr>
                                            <td style="padding:10px 12px;background-color:#f4f4f5;border-bottom:1px solid #e4e4e7;">
                                                <strong style="font-size:12px;color:#18181b;">{{ $profile['title'] ?? ('Grafik ' . ($profile['line'] ?? '-')) }}</strong>
                                                <span style="float:right;font-size:11px;color:#71717a;">Grafik {{ $profile['line'] ?? '-' }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:10px 12px;">
                                                <p style="margin:0 0 6px 0;font-size:13px;font-weight:700;color:#2563eb;text-transform:uppercase;">{{ $profile['pattern'] ?? 'Pattern tidak tersedia' }}</p>
                                                @if(!empty($profile['behaviours']))
                                                    <ul style="margin:0;padding:0 0 0 18px;font-size:12px;line-height:1.65;color:#3f3f46;">
                                                        @foreach($profile['behaviours'] as $behaviour)
                                                            <li style="margin:0 0 4px 0;">{{ $behaviour }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                @endforeach

                                @if(!empty($discDetail['description']))
                                    <p style="margin:10px 0 0 0;font-size:12px;color:#3f3f46;line-height:1.65;">
                                        <strong>Deskripsi Kepribadian:</strong><br>{{ $discDetail['description'] }}
                                    </p>
                                @endif

                                @if(!empty($discDetail['jobs']))
                                    <p style="margin:10px 0 0 0;font-size:12px;color:#3f3f46;line-height:1.65;">
                                        <strong>Job Match:</strong><br>{{ implode(' · ', $discDetail['jobs']) }}
                                    </p>
                                @endif

                            @elseif($engine === 'papi_kostick' && !empty($papiDetail['rows']))
                                {{-- PAPI Kostick Detail --}}
                                <p style="margin:0 0 10px 0;font-size:12px;font-weight:700;color:#18181b;text-transform:uppercase;letter-spacing:0.05em;">Hasil PAPI Kostick</p>
                                <p style="margin:0 0 12px 0;font-size:11px;color:#71717a;">Analisis dimensi peran dan kebutuhan dalam lingkungan kerja</p>

                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;font-size:11px;">
                                    <tr style="background-color:#f4f4f5;">
                                        <th align="center" style="padding:8px;border:1px solid #e4e4e7;width:36px;">No</th>
                                        <th align="left" style="padding:8px;border:1px solid #e4e4e7;">Sikap &amp; Gaya Kerja</th>
                                        <th align="center" style="padding:8px;border:1px solid #e4e4e7;width:56px;">Kode</th>
                                        <th align="center" style="padding:8px;border:1px solid #e4e4e7;width:56px;">Skor</th>
                                        <th align="left" style="padding:8px;border:1px solid #e4e4e7;">Interpretasi</th>
                                    </tr>
                                    @foreach($papiDetail['rows'] as $row)
                                        @php $scoreStyle = EmailData::papiScoreStyle($row['score'] ?? 0); @endphp
                                        <tr>
                                            <td align="center" style="padding:7px;border:1px solid #e4e4e7;vertical-align:top;">{{ $row['no'] ?? '-' }}</td>
                                            <td style="padding:7px;border:1px solid #e4e4e7;vertical-align:top;color:#18181b;">{{ $row['role'] ?? '-' }}</td>
                                            <td align="center" style="padding:7px;border:1px solid #e4e4e7;vertical-align:top;font-weight:700;">{{ $row['code'] ?? '-' }}</td>
                                            <td align="center" style="padding:7px;border:1px solid #e4e4e7;vertical-align:top;">
                                                <span style="display:inline-block;padding:2px 8px;background-color:{{ $scoreStyle['bg'] }};color:{{ $scoreStyle['color'] }};border:1px solid {{ $scoreStyle['border'] }};font-weight:700;">
                                                    {{ $row['score'] ?? 0 }}
                                                </span>
                                            </td>
                                            <td style="padding:7px;border:1px solid #e4e4e7;vertical-align:top;color:#3f3f46;line-height:1.55;">{{ $row['interpretation'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </table>

                            @else
                                @foreach(($session['items'] ?? []) as $item)
                                    <p style="margin:0 0 6px 0;font-size:12px;line-height:1.6;color:#3f3f46;">
                                        <strong>{{ $item['label'] ?? '-' }}:</strong> {{ $item['value'] ?? '-' }}
                                    </p>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                </table>
            @empty
                <p style="margin:0;font-size:13px;color:#71717a;">Data assessment belum tersedia.</p>
            @endforelse
        </td>
    </tr>
</table>

{{-- Absensi --}}
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e4e4e7;margin:0 0 20px 0;">
    <tr>
        <td style="padding:10px 14px;background-color:#f4f4f5;border-bottom:1px solid #e4e4e7;">
            <strong style="font-size:13px;color:#18181b;">Absensi 3 Bulan</strong>
            @if(!empty($attendance['period_label']))
                <span style="display:block;font-size:11px;color:#71717a;margin-top:2px;">Periode: {{ $attendance['period_label'] }}</span>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:16px;">
            @if($attendance)
                @php $summary = $attendance['summary'] ?? []; @endphp
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 14px 0;">
                    <tr>
                        @foreach([
                            ['Hadir', $summary['hadir'] ?? 0],
                            ['Tepat Waktu', $summary['tepat_waktu'] ?? 0],
                            ['Telat', $summary['telat'] ?? 0],
                            ['Lembur', $summary['lembur'] ?? 0],
                        ] as [$label, $value])
                            <td class="stack-column" width="25%" style="padding:2px;">
                                <div style="border:1px solid #e4e4e7;background-color:#fafafa;padding:12px 8px;text-align:center;">
                                    <div style="font-size:20px;font-weight:700;color:#18181b;">{{ $value }}</div>
                                    <div style="font-size:11px;color:#71717a;margin-top:4px;">{{ $label }}</div>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </table>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;font-size:12px;">
                    <tr style="background-color:#f4f4f5;">
                        <th align="left" style="padding:8px;border:1px solid #e4e4e7;">Bulan</th>
                        <th align="center" style="padding:8px;border:1px solid #e4e4e7;">Hadir</th>
                        <th align="center" style="padding:8px;border:1px solid #e4e4e7;">Telat</th>
                        <th align="center" style="padding:8px;border:1px solid #e4e4e7;">Lembur</th>
                    </tr>
                    @foreach(($attendance['monthly'] ?? []) as $month)
                        @php $ms = $month['summary'] ?? []; @endphp
                        <tr>
                            <td style="padding:8px;border:1px solid #e4e4e7;">{{ $month['label'] ?? '-' }}</td>
                            <td align="center" style="padding:8px;border:1px solid #e4e4e7;">{{ $ms['hadir'] ?? 0 }}</td>
                            <td align="center" style="padding:8px;border:1px solid #e4e4e7;">{{ $ms['telat'] ?? 0 }}</td>
                            <td align="center" style="padding:8px;border:1px solid #e4e4e7;">{{ $ms['lembur'] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </table>
            @else
                <p style="margin:0;font-size:13px;color:#71717a;">Data absensi belum tersedia.</p>
            @endif
        </td>
    </tr>
</table>

{{-- Konseling --}}
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e4e4e7;margin:0 0 20px 0;">
    <tr>
        <td style="padding:10px 14px;background-color:#f4f4f5;border-bottom:1px solid #e4e4e7;">
            <strong style="font-size:13px;color:#18181b;">Hasil Konseling</strong>
        </td>
    </tr>
    <tr>
        <td style="padding:16px;">
            @if($counseling)
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin:0 0 12px 0;border:1px solid #e4e4e7;">
                    @foreach([
                        'Tanggal' => $counseling['scheduled_date'] ?? '-',
                        'Waktu' => isset($counseling['scheduled_time']) ? substr((string) $counseling['scheduled_time'], 0, 5) : '-',
                        'Tipe' => $counseling['type'] ?? '-',
                        'Konselor' => $counseling['counselor_name'] ?? '-',
                    ] as $label => $value)
                        <tr>
                            <td style="padding:8px 12px;width:30%;font-size:12px;color:#71717a;{{ $loop->first ? '' : 'border-top:1px solid #e4e4e7;' }}">{{ $label }}</td>
                            <td style="padding:8px 12px;font-size:13px;color:#18181b;font-weight:600;{{ $loop->first ? '' : 'border-top:1px solid #e4e4e7;' }}">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
                <div style="padding:14px;border:1px solid #bbf7d0;background-color:#f0fdf4;">
                    <p style="margin:0 0 8px 0;font-size:12px;font-weight:700;color:#166534;">Catatan Hasil Konseling</p>
                    <p style="margin:0;font-size:13px;line-height:1.7;color:#3f3f46;white-space:pre-wrap;">{{ EmailData::plainText($counseling['result_notes'] ?? '') }}</p>
                </div>
            @else
                <p style="margin:0;font-size:13px;color:#71717a;">Data konseling belum tersedia.</p>
            @endif
        </td>
    </tr>
</table>

{{-- Timeline --}}
@if(!empty($logs))
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e4e4e7;margin:0 0 8px 0;">
    <tr>
        <td style="padding:10px 14px;background-color:#f4f4f5;border-bottom:1px solid #e4e4e7;">
            <strong style="font-size:13px;color:#18181b;">Timeline Status</strong>
        </td>
    </tr>
    <tr>
        <td style="padding:16px;">
            @foreach($logs as $log)
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-left:3px solid #2563eb;padding-left:12px;margin:0 0 12px 0;">
                    <tr>
                        <td>
                            <p style="margin:0;font-size:13px;font-weight:700;color:#18181b;">{{ $log['action_label'] ?? $log['action'] ?? '-' }}</p>
                            <p style="margin:4px 0 0 0;font-size:11px;color:#71717a;">{{ EmailData::formatLogTime($log['created_at'] ?? null) }} · {{ $log['actor_name'] ?? 'System' }}</p>
                            @if(!empty($log['notes']))
                                <p style="margin:6px 0 0 0;font-size:12px;line-height:1.6;color:#52525b;">{{ $log['notes'] }}</p>
                            @endif
                        </td>
                    </tr>
                </table>
            @endforeach
        </td>
    </tr>
</table>
@endif

<p style="margin:16px 0 0 0;font-size:11px;line-height:1.6;color:#71717a;">
    Lampiran PDF berisi ringkasan dokumen evaluasi untuk arsip internal.
</p>
