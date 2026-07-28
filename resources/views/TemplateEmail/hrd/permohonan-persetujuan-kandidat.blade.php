@include('TemplateEmail.hrd.partials.shell-open', [
    'title' => 'Permohonan Persetujuan Kandidat',
    'heading' => 'Permohonan Persetujuan Kandidat',
    'subheading' => $mark === 'Ibu Boss'
        ? 'Review kandidat untuk persetujuan Ibu Direktur'
        : 'Review kandidat untuk persetujuan Offering Salary',
])

<p style="margin:0 0 14px 0;font-size:15px;line-height:1.7;color:#334155;">
    Yth. Bapak/Ibu Direktur,
</p>

<p style="margin:0 0 20px 0;font-size:14px;line-height:1.8;color:#475569;text-align:justify;">
    Dengan hormat, kami informasikan bahwa saat ini terdapat kandidat potensial yang telah melalui tahap seleksi awal
    dan dinyatakan memenuhi kriteria untuk dipertimbangkan dalam proses selanjutnya.
    Kami mohon persetujuan Bapak/Ibu Direktur atas kandidat berikut:
</p>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;border-spacing:0;background:linear-gradient(180deg,#eff6ff 0%,#f8fafc 100%);border:1px solid #bfdbfe;border-radius:14px;overflow:hidden;margin:0 0 24px 0;">
    @foreach([
        'Nama Kandidat' => $data->nama_lengkap ?? '-',
        'Shio' => $data->shio ?? '-',
        'Elemen' => $data->elemen ?? '-',
        'Posisi yang Dilamar' => $data->nama_jabatan ?? '-',
        'Usia' => $data->umur ?? '-',
        'Alamat' => $data->alamat_domisili ?? '-',
        'Kontak' => $contact ?? '-',
    ] as $label => $value)
        <tr>
            <td style="padding:12px 18px;width:38%;font-size:13px;color:#64748b;{{ $loop->first ? '' : 'border-top:1px solid #dbeafe;' }}vertical-align:top;">{{ $label }}</td>
            <td style="padding:12px 18px;font-size:14px;color:#0f172a;font-weight:600;{{ $loop->first ? '' : 'border-top:1px solid #dbeafe;' }}vertical-align:top;">{{ $value }}</td>
        </tr>
    @endforeach
</table>

<p style="margin:0 0 20px 0;font-size:14px;line-height:1.8;color:#475569;text-align:justify;">
    Kandidat ini telah memenuhi sejumlah persyaratan awal dan memiliki potensi sesuai dengan kebutuhan perusahaan.
    Persetujuan Bapak/Ibu Direktur akan sangat membantu dalam menentukan langkah selanjutnya.
</p>

<p style="margin:0 0 28px 0;font-size:14px;line-height:1.8;color:#475569;">
    Terima kasih atas perhatian dan kerja samanya.<br><br>
    Hormat kami,<br>
    <strong style="color:#1e40af;">HRD Recruitment Team</strong>
</p>

<div style="height:1px;background-color:#e2e8f0;margin:0 0 28px 0;"></div>

@include('TemplateEmail.hrd.partials.cv-detail', $cv)

@if(!empty($btn))
    @include('TemplateEmail.hrd.partials.action-buttons', ['btn' => $btn, 'mark' => $mark])
@endif

@include('TemplateEmail.hrd.partials.shell-close')
