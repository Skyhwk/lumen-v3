@include('TemplateEmail.hrd.partials.shell-open', [
    'title' => 'Penawaran Gaji - PT Inti Surya Laboratorium',
    'heading' => 'Penawaran Gaji',
    'subheading' => 'Konfirmasi penawaran gaji kandidat',
])

@php
    $salutation = \App\Services\GenerateMessageAtsEmail::resolveSalutation($data);
    $nama = $data->nama_lengkap ?? '-';
    $posisi = \App\Services\HrdEmailViewData::getNamaJabatan($data);
    $offerAmount = $data->sallaryOffer->sallary_offer_hrd
        ?? $data->sallary_offer->sallary_offer_hrd
        ?? $data->sallary_offer_hrd
        ?? $data->ekspetasi_gaji
        ?? null;
    $offerFormatted = !empty($offerAmount)
        ? 'Rp ' . number_format((float) $offerAmount, 0, ',', '.')
        : '-';
@endphp

<p style="margin:0 0 14px 0;font-size:15px;line-height:1.7;color:#334155;">
    Yth. {{ $salutation }} <strong>{{ $nama }}</strong>,
</p>

<p style="margin:0 0 16px 0;font-size:14px;line-height:1.8;color:#475569;text-align:justify;">
    Terima kasih atas partisipasi Anda dalam proses rekrutmen PT Inti Surya Laboratorium untuk posisi
    <strong style="color:#1e40af;">{{ $posisi }}</strong>.
</p>

<p style="margin:0 0 16px 0;font-size:14px;line-height:1.8;color:#475569;text-align:justify;">
    Setelah melalui tahapan evaluasi, kami bermaksud menginformasikan penawaran gaji berikut. Mohon dicatat bahwa
    <strong>penawaran ini belum merupakan keputusan penerimaan kerja</strong> — keputusan final akan ditetapkan
    setelah menyelesaikan tahapan administrasi berikutnya.
</p>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;border-spacing:0;background:linear-gradient(180deg,#eff6ff 0%,#f8fafc 100%);border:1px solid #bfdbfe;border-radius:14px;overflow:hidden;margin:0 0 24px 0;">
    @foreach([
        'Posisi' => $posisi,
        'Penawaran Gaji' => $offerFormatted,
    ] as $label => $value)
        <tr>
            <td style="padding:12px 18px;width:38%;font-size:13px;color:#64748b;{{ $loop->first ? '' : 'border-top:1px solid #dbeafe;' }}vertical-align:top;">{{ $label }}</td>
            <td style="padding:12px 18px;font-size:14px;color:#0f172a;font-weight:600;{{ $loop->first ? '' : 'border-top:1px solid #dbeafe;' }}vertical-align:top;">{{ $value }}</td>
        </tr>
    @endforeach
</table>

<p style="margin:24px 0 0 0;font-size:13px;line-height:1.8;color:#64748b;text-align:justify;">
    Apabila Anda memiliki pertanyaan, silakan hubungi tim HRD PT Inti Surya Laboratorium.
</p>

<p style="margin:16px 0 0 0;font-size:14px;line-height:1.8;color:#475569;">
    Hormat kami,<br>
    <strong style="color:#1e40af;">HRD & Talent Acquisition Division</strong><br>
    PT Inti Surya Laboratorium
</p>

@include('TemplateEmail.hrd.partials.shell-close')
