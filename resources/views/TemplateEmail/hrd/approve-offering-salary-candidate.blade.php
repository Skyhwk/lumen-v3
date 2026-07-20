@include('TemplateEmail.hrd.partials.shell-open', [
    'title' => 'Offering Salary - Diterima',
    'heading' => 'Selamat, Anda Diterima!',
    'subheading' => 'Konfirmasi penerimaan kandidat',
])

<p style="margin:0 0 14px 0;font-size:15px;line-height:1.7;color:#334155;">
    Dear <strong>{{ $data->nama_lengkap ?? '-' }}</strong>,
</p>

<p style="margin:0 0 16px 0;font-size:14px;line-height:1.8;color:#475569;text-align:justify;">
    Terima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi
    <strong style="color:#1e40af;">{{ $data->posisi_di_lamar ?? '-' }}</strong>.
</p>

<p style="margin:0 0 16px 0;font-size:14px;line-height:1.8;color:#475569;text-align:justify;">
    Berdasarkan pertimbangan dan penilaian pihak kami, serta sesuai dengan kesepakatan yang telah Anda setujui
    pada tahapan akhir proses rekrutmen perusahaan kami, maka dengan ini kami informasikan keputusan pihak perusahaan bahwa
    <strong style="color:#15803d;text-decoration:underline;">Anda diterima untuk bergabung dengan perusahaan</strong>.
</p>

<p style="margin:0 0 12px 0;font-size:14px;line-height:1.8;color:#475569;">
    Sehubungan dengan hal tersebut, <strong>Anda wajib hadir di perusahaan pada:</strong>
</p>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;border-spacing:0;background:linear-gradient(180deg,#eff6ff 0%,#f8fafc 100%);border:1px solid #bfdbfe;border-radius:14px;overflow:hidden;margin:0 0 20px 0;">
    @foreach([
        'Hari / Tanggal' => trim(($data->hariIndonesia ?? '') . ' / ' . ($data->tglInter ?? '-')),
        'Waktu' => '08:00 WIB',
        'Alamat Perusahaan' => $data->alamat ?? '-',
    ] as $label => $value)
        <tr>
            <td style="padding:12px 18px;width:38%;font-size:13px;color:#64748b;{{ $loop->first ? '' : 'border-top:1px solid #dbeafe;' }}vertical-align:top;">{{ $label }}</td>
            <td style="padding:12px 18px;font-size:14px;color:#0f172a;font-weight:600;{{ $loop->first ? '' : 'border-top:1px solid #dbeafe;' }}vertical-align:top;">{{ $value }}</td>
        </tr>
    @endforeach
</table>

<p style="margin:0 0 16px 0;font-size:14px;line-height:1.8;color:#475569;text-align:justify;">
    Apabila terdapat pertanyaan, Anda dapat langsung menghubungi pihak HRD melalui WhatsApp pada nomor
    <strong>0811-1254-0719</strong>.
</p>

<p style="margin:0 0 24px 0;font-size:14px;line-height:1.8;color:#475569;text-align:justify;">
    Demikian pemberitahuan ini kami sampaikan, agar dapat diketahui, dipahami, dan dilaksanakan dengan sebaik-baiknya. Terima kasih.
</p>

<p style="margin:0;font-size:14px;line-height:1.8;color:#475569;">
    Regards,<br><br><br>
    <strong style="color:#1e40af;">( HRD PT. Inti Surya Laboratorium )</strong>
</p>

@include('TemplateEmail.hrd.partials.shell-close')
