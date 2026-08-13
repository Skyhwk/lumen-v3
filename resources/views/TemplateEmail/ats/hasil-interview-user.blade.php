@include('TemplateEmail.ats.partials.shell-open', [
    'title' => 'Permohonan Persetujuan Kandidat',
    'heading' => 'Permohonan Persetujuan Kandidat',
    'subheading' => 'Review kandidat untuk persetujuan Direktur',
])

<p style="margin:0 0 14px 0;font-size:15px;line-height:1.7;color:#334155;">
    Yth. Bapak/Ibu Direktur,
</p>

<p style="margin:0 0 20px 0;font-size:14px;line-height:1.8;color:#475569;text-align:justify;">
    Dengan hormat, kami informasikan bahwa saat ini terdapat kandidat potensial yang telah melalui tahap seleksi awal dan dinyatakan memenuhi kriteria untuk dipertimbangkan dalam proses selanjutnya. Kami mohon persetujuan Bapak/Ibu Direktur atas kandidat berikut:
</p>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;border-spacing:0;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin:0 0 20px 0;">
    @foreach([
        'Candidate Name' => $recruitment->nama_lengkap ?? '-',
        'Email' => $recruitment->email ?? '-',
        'Phone' => $recruitment->no_telepon ?? '-',
        'Request No.' => $pr->no_request ?? '-',
        'Division' => $pr->detailDivisi->nama_divisi ?? $pr->divisi ?? '-',
        'Applied Position' => $pr->detailPosisi->nama_jabatan ?? $pr->posisi ?? '-',
        'Branch' => $pr->detailCabang->nama_cabang ?? $pr->lokasi_penempatan_cabang ?? '-',
        'Interview Type' => ucfirst($interview->jenis_interview ?? '-'),
        'Interview Date' => !empty($interview->tanggal_interview) ? \Carbon\Carbon::parse($interview->tanggal_interview)->locale('id')->isoFormat('D MMMM YYYY') : '-',
    ] as $label => $value)
        <tr>
            <td class="info-label" style="padding:10px 16px;width:40%;font-size:13px;color:#64748b;{{ $loop->first ? '' : 'border-top:1px solid #e2e8f0;' }}vertical-align:top;">{{ $label }}</td>
            <td style="padding:10px 16px;font-size:13px;color:#0f172a;font-weight:600;{{ $loop->first ? '' : 'border-top:1px solid #e2e8f0;' }}vertical-align:top;">{{ $value }}</td>
        </tr>
    @endforeach
</table>

<!-- Review HRD -->
<div style="background-color: #f1f5f9; border: 1px solid #cbd5e1; padding: 14px; border-radius: 8px; margin-bottom: 14px;">
    <h3 style="margin: 0 0 8px 0; font-size: 13px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em;">
        <strong>HRD Interview Review</strong>
    </h3>
    <div style="margin: 0; font-size: 13px; color: #475569; line-height: 1.5;">
        {!! $hrdInterview->catatan_interview ?? '<i>Tidak ada catatan</i>' !!}
    </div>
</div>

<!-- Review User -->
<div style="background-color: {{ $decision === 'approve' ? '#f0fdf4' : '#fef2f2' }}; border: 1px solid {{ $decision === 'approve' ? '#bbf7d0' : '#fecaca' }}; padding: 14px; border-radius: 8px; margin-bottom: 20px;">
    <h3 style="margin: 0 0 8px 0; font-size: 13px; color: {{ $decision === 'approve' ? '#166534' : '#991b1b' }}; text-transform: uppercase; letter-spacing: 0.05em;">
        <strong>User Interview Review</strong>
    </h3>
    <div style="margin: 0; font-size: 13px; color: #475569; line-height: 1.5;">
        {!! $interview->catatan_interview ?? '<i>Tidak ada catatan</i>' !!}
    </div>
</div>

<p style="margin:0 0 20px 0;font-size:14px;line-height:1.8;color:#475569;text-align:justify;">
    Kandidat ini telah memenuhi sejumlah persyaratan awal dan memiliki potensi sesuai dengan kebutuhan perusahaan. Persetujuan Bapak/Ibu Direktur akan sangat membantu dalam menentukan langkah selanjutnya.
</p>

<p style="margin:0 0 28px 0;font-size:14px;line-height:1.8;color:#475569;">
    Terima kasih atas perhatian dan kerja samanya.<br><br>
    Hormat kami,<br>
    <strong style="color:#1e40af;">HRD Recruitment Team</strong>
</p>

<div style="height:1px;background-color:#e2e8f0;margin:0 0 28px 0;"></div>

@php
    $portalUrl = rtrim(env('PORTALV4', 'http://portal.intilab.com'), '/');
    $encodedToken = rawurlencode($recruitment->token_approval ?? '');
    $btn = (object)[
        'approve' => "{$portalUrl}/new-recruitment/decision/{$encodedToken}?decision=approve",
        'reject' => "{$portalUrl}/new-recruitment/decision/{$encodedToken}?decision=reject"
    ];
@endphp

@include('TemplateEmail.ats.partials.action-buttons', ['btn' => $btn])

@include('TemplateEmail.ats.partials.cv-detail', $cv)

@include('TemplateEmail.ats.partials.shell-close')
