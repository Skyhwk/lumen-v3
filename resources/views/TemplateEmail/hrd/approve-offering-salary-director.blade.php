@include('TemplateEmail.hrd.partials.shell-open', [
    'title' => 'Approve Offering Salary',
    'heading' => 'Offering Salary Disetujui',
    'subheading' => 'Konfirmasi persetujuan kandidat oleh Direktur',
])

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px 0;">
    <tr>
        <td style="padding:16px 18px;background-color:#ecfdf5;border:1px solid #bbf7d0;border-radius:12px;">
            <p style="margin:0;font-size:15px;line-height:1.7;color:#166534;">
                Berhasil melakukan <strong style="color:#15803d;">APPROVE</strong> kandidat pada Offering Salary.
                Kandidat akan terjadwalkan masuk kerja sesuai detail berikut.
            </p>
        </td>
    </tr>
</table>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;border-spacing:0;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin:0 0 24px 0;">
    @foreach([
        'Kandidat' => $data->nama_lengkap ?? '-',
        'Posisi' => $data->posisi_di_lamar ?? '-',
        'Bagian' => $data->nama_jabatan ?? '-',
        'Tanggal Masuk' => $data->tglInter ?? '-',
        'Tempat' => $data->alamat ?? '-',
    ] as $label => $value)
        <tr>
            <td style="padding:12px 18px;width:34%;font-size:13px;color:#64748b;{{ $loop->first ? '' : 'border-top:1px solid #e2e8f0;' }}vertical-align:top;">{{ $label }}</td>
            <td style="padding:12px 18px;font-size:14px;color:#0f172a;font-weight:600;{{ $loop->first ? '' : 'border-top:1px solid #e2e8f0;' }}vertical-align:top;">{{ $value }}</td>
        </tr>
    @endforeach
</table>

<p style="margin:0;font-size:14px;line-height:1.8;color:#475569;">
    Proses onboarding akan dilanjutkan oleh tim HRD sesuai prosedur yang berlaku.
</p>

@include('TemplateEmail.hrd.partials.shell-close')
