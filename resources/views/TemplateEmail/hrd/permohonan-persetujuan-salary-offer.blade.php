@include('TemplateEmail.hrd.partials.shell-open', [
    'title' => 'Permohonan Persetujuan Offering Salary',
    'heading' => 'Permohonan Persetujuan Offering Salary',
    'subheading' => 'Review kandidat untuk persetujuan Offering Salary',
])

<p style="margin:0 0 14px 0;font-size:15px;line-height:1.7;color:#334155;">
    Yth. Bapak/Ibu Direktur,
</p>

<p style="margin:0 0 20px 0;font-size:14px;line-height:1.8;color:#475569;text-align:justify;">
    Dengan hormat, kami informasikan bahwa saat ini terdapat kandidat potensial yang telah melalui tahap seleksi awal
    dan dinyatakan memenuhi kriteria untuk dipertimbangkan dalam proses selanjutnya.
    Kami mohon persetujuan Bapak/Ibu Direktur atas penawaran gaji (Offering Salary) untuk kandidat berikut:
</p>


@php
    $gajiTerakhir = !empty($data->gaji_terakhir) ? 'Rp ' . number_format($data->gaji_terakhir, 0, ',', '.') : '-';
    $ekspektasiGaji = !empty($data->ekspetasi_gaji) ? 'Rp ' . number_format($data->ekspetasi_gaji, 0, ',', '.') : '-';
    $penawaranGajiHrd = !empty($data->sallary_offer_hrd)
        ? 'Rp ' . number_format($data->sallary_offer_hrd, 0, ',', '.')
        : (!empty($data->sallary_offer->sallary_offer_hrd) ? 'Rp ' . number_format($data->sallary_offer->sallary_offer_hrd, 0, ',', '.') : '-');
@endphp

   <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;border-spacing:0;background:linear-gradient(180deg,#eff6ff 0%,#f8fafc 100%);border:1px solid #bfdbfe;border-radius:14px;overflow:hidden;margin:0 0 16px 0;">
    @foreach([
            'Nama Kandidat' => $data->nama_lengkap ?? '-',
            'Shio' => $data->shio ?? '-',
            'Elemen' => $data->elemen ?? '-',
            'Posisi yang Dilamar' => \App\Services\HrdEmailViewData::getNamaJabatan($data),
            'Usia' => \App\Services\HrdEmailViewData::getUsia($data),
            'Alamat' => $data->alamat_domisili ?? '-',
            'Kontak' => $contact ?? '-',
        ] as $label => $value)
            <tr>
                        <td
                style="padding:12px 18px;width:38%;font-size:13px;color:#64748b;{{ $loop->first ? '' : 'border-top:1px solid #dbeafe;' }}vertical-align:top;">{{ $label }}</td>
            <td style="padding:12px 18px;font-size:14px;color:#0f172a;font-weight:400;{{ $loop->first ? '' : 'border-top:1px solid #dbeafe;' }}vertical-align:top;">{{ $value }}</td>
            </tr>
    @endforeach
</table>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;border-spacing:0;background:#f8fafc;border:1px solid #bfdbfe;border-radius:14px;overflow:hidden;margin:0 0 24px 0;">
    <tr>
        <td style="width:33.33%;padding:14px 16px;vertical-align:top;border-right:1px solid #dbeafe;">
            <div style="font-size:12px;color:#64748b;margin:0 0 6px 0;">Gaji Terakhir</div>
            <div style="font-size:14px;color:#0f172a;font-weight:600;">{{ $gajiTerakhir }}</div>
        </td>
        <td style="width:33.33%;padding:14px 16px;vertical-align:top;border-right:1px solid #dbeafe;">
            <div style="font-size:12px;color:#64748b;margin:0 0 6px 0;">Ekspektasi Gaji</div>
            <div style="font-size:14px;color:#0f172a;font-weight:600;">{{ $ekspektasiGaji }}</div>
        </td>
        <td style="width:33.33%;padding:14px 16px;vertical-align:top;">
            <div style="font-size:12px;color:#64748b;margin:0 0 6px 0;">Persetujuan Gaji</div>
            <div style="font-size:14px;color:#1e40af;font-weight:700;">{{ $penawaranGajiHrd }}</div>
        </td>
    </tr>
</table>

@if(!empty($data->resubmit_reason))
    @php
        $resubmitAt = '-';
        if (!empty($data->resubmit_at)) {
            $resubmitDate = \Carbon\Carbon::parse($data->resubmit_at);
            $resubmitAt = $resubmitDate->locale('id')->translatedFormat('d F Y') . $resubmitDate->format(' h:i A');
        }
    @endphp
    <div style="background-color:#fffbeb;border:1px solid #fde68a;padding:14px;border-radius:8px;margin-bottom:20px;">
        <h3 style="margin:0 0 8px 0;font-size:13px;color:#92400e;text-transform:uppercase;letter-spacing:0.05em;">
            <strong>Alasan Pengajuan Ulang</strong>
        </h3>
        <div style="margin:0 0 10px 0;font-size:13px;color:#475569;line-height:1.6;">
            {!! ($data->resubmit_reason) !!}
        </div>
        <table role="presentation" cellspacing="0" cellpadding="0" style="font-size:12px;color:#78716c;line-height:1.6;border-collapse:collapse;">
            <tr>
                <td style="padding:0 8px 0 0;">Diajukan ulang oleh</td>
                <td style="padding:0 8px 0 0;">:</td>
                <td style="padding:0;"><strong>{{ $data->resubmit_by ?? 'HRD' }}</strong></td>
            </tr>
            <tr>
                <td style="padding:0 8px 0 0;">Tanggal diajukan</td>
                <td style="padding:0 8px 0 0;">:</td>
                <td style="padding:0;">{{ $resubmitAt }}</td>
            </tr>
        </table>
    </div>
@endif

@php
    $bypassData = is_array($data->bypass ?? null)
        ? $data->bypass
        : (json_decode($data->bypass ?? '{}', true) ?: []);
    $bypassLabels = [
        'hrd_interview' => [
            'label' => 'HRD Interview',
            'background' => '#f1f5f9',
            'border' => '#cbd5e1',
            'heading' => '#334155',
        ],
        'user_interview' => [
            'label' => 'User Interview',
            'background' => '#f0fdf4',
            'border' => '#bbf7d0',
            'heading' => '#166534',
        ],
    ];
@endphp
@foreach($bypassLabels as $bypassStage => $bypassStyle)
    @if(!empty($bypassData[$bypassStage]['description']))
        @php
            $bypassAt = '-';
            if (!empty($bypassData[$bypassStage]['at'])) {
                $bypassDate = \Carbon\Carbon::parse($bypassData[$bypassStage]['at']);
                $bypassAt = $bypassDate->locale('id')->translatedFormat('d F Y') . $bypassDate->format(' h:i A');
            }
        @endphp
        <div style="background-color:{{ $bypassStyle['background'] }};border:1px solid {{ $bypassStyle['border'] }};padding:14px;border-radius:8px;margin-bottom:20px;">
            <h3 style="margin:0 0 8px 0;font-size:13px;color:{{ $bypassStyle['heading'] }};text-transform:uppercase;letter-spacing:0.05em;">
                <strong>Bypass {{ $bypassStyle['label'] }}</strong>
            </h3>
            <div style="margin:0 0 10px 0;font-size:13px;color:#475569;line-height:1.6;">
                {!! $bypassData[$bypassStage]['description'] !!}
            </div>
            <table role="presentation" cellspacing="0" cellpadding="0" style="font-size:12px;color:#78716c;line-height:1.6;border-collapse:collapse;">
                <tr>
                    <td style="padding:0 8px 0 0;">Bypass oleh</td>
                    <td style="padding:0 8px 0 0;">:</td>
                    <td style="padding:0;"><strong>{{ $bypassData[$bypassStage]['by'] ?? 'HRD' }}</strong></td>
                </tr>
                <tr>
                    <td style="padding:0 8px 0 0;">Tanggal bypass</td>
                    <td style="padding:0 8px 0 0;">:</td>
                    <td style="padding:0;">{{ $bypassAt }}</td>
                </tr>
            </table>
        </div>
    @endif
@endforeach

<p style="margin:0 0 20px 0;font-size:14px;line-height:1.8;color:#475569;text-align:justify;">
    Kandidat ini telah memenuhi sejumlah persyaratan awal dan memiliki potensi sesuai dengan kebutuhan perusahaan.
    Persetujuan Bapak/Ibu Direktur akan sangat membantu dalam menentukan langkah selanjutnya.
</p>

<p style="margin:0 0 28px 0;font-size:14px;line-height:1.8;color:#475569;">
    Terima kasih atas perhatian dan kerja samanya.<br><br>
    Hormat kami,<br>
    <strong style="color:#1e40af;">HRD Division</strong>
</p>

<div style="height:1px;background-color:#e2e8f0;margin:0 0 28px 0;"></div>

@include('TemplateEmail.hrd.partials.cv-detail-salary-offer', $cv)

@if(!empty($btn))
    @include('TemplateEmail.hrd.partials.action-buttons-salary-offer', ['btn' => $btn, 'mark' => $mark])
@endif

@include('TemplateEmail.hrd.partials.shell-close')
