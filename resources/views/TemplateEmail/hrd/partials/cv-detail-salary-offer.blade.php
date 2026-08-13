@php
    $sectionTitle = 'margin:0 0 12px 0;font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#2563eb;';
    $cardStyle = 'width:100%;border-collapse:separate;border-spacing:0;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin:0 0 20px 0;';
    $labelStyle = 'padding:10px 16px;width:38%;font-size:13px;color:#64748b;border-top:1px solid #e2e8f0;vertical-align:top;';
    $valueStyle = 'padding:10px 16px;font-size:13px;color:#0f172a;font-weight:400;border-top:1px solid #e2e8f0;vertical-align:top;';
@endphp

<p style="margin:0 0 16px 0;font-size:18px;font-weight:700;color:#0f172a;">Detail Kandidat & Offering Salary</p>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="{{ $cardStyle }}">
    <tr>
        @if(!empty($photoUrl))
            <td class="photo-cell stack-column" width="120" style="padding:20px 16px 20px 20px;vertical-align:top;">
                <img src="{{ $photoUrl }}" alt="Foto Kandidat" width="96" height="96"
                    style="display:block;width:96px;height:96px;border-radius:16px;object-fit:cover;border:3px solid #dbeafe;">
            </td>
        @endif
        <td class="stack-column" style="padding:20px;vertical-align:top;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                @foreach([
                        'Nama' => $data->nama_lengkap ?? '-',
                        // 'Lokasi Penempatan' => $data->nama_cabang ?? '-',
                        'Posisi Dilamar' => $namaJabatanFormatted ?? \App\Services\HrdEmailViewData::getNamaJabatan($data),
                        'Shio' => $data->shio ?? '-',
                        'Elemen' => $data->elemen ?? '-',
                        'Gaji Terakhir' => !empty($data->gaji_terakhir) ? 'Rp ' . number_format($data->gaji_terakhir, 0, ',', '.') : '-',
                        'Ekspektasi Gaji' => !empty($data->ekspetasi_gaji) ? 'Rp ' . number_format($data->ekspetasi_gaji, 0, ',', '.') : '-',
                        // 'Penawaran Gaji HRD' => !empty($data->sallary_offer_hrd) ? 'Rp ' . number_format($data->sallary_offer_hrd, 0, ',', '.') : (!empty($data->sallary_offer->sallary_offer_hrd) ? 'Rp ' . number_format($data->sallary_offer->sallary_offer_hrd, 0, ',', '.') : '-'),
                    ] as $label => $value)
                    <tr>
                        <td class="info-label"
                            style="padding:4px 0;width:42%;font-size:13px;color:#64748b;vertical-align:top;">{{ $label }}
                        </td>
                        <td style="padding:4px 0 4px 8px;font-size:13px;color:#0f172a;font-weight:400;vertical-align:top;">:
                            {{ $value }}
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

@php
    // $data itu isinya NewRecruitment
    $catatanHrdRaw = $data->hrdInterview->catatan_interview
        ?? $data->hrdInterview->catatan
        ?? $data->hrd_interview->catatan_interview
        ?? $data->hrd_interview->catatan
        ?? '-';
    $catatanHrdClean = (!empty($catatanHrdRaw) && $catatanHrdRaw !== '-')
        ? trim(strip_tags(html_entity_decode($catatanHrdRaw)))
        : '-';
    $reviewHrdRows = [
        'Catatan' => !empty($catatanHrdClean) ? $catatanHrdClean : '-',
    ];

    $catatanUserRaw = $data->userInterview->catatan_interview
        ?? $data->userInterview->catatan
        ?? $data->user_interview->catatan_interview
        ?? $data->user_interview->catatan
        ?? '-';
    $catatanUserClean = (!empty($catatanUserRaw) && $catatanUserRaw !== '-')
        ? trim(strip_tags(html_entity_decode($catatanUserRaw)))
        : '-';
    $reviewUserRows = [
        'Catatan' => !empty($catatanUserClean) ? $catatanUserClean : '-',
    ];
@endphp

<p style="{{ $sectionTitle }}">Hasil Interview HRD</p>
@include('TemplateEmail.hrd.partials.info-table-salary-offer', ['rows' => $reviewHrdRows])

<p style="{{ $sectionTitle }}">Hasil Interview User</p>
@include('TemplateEmail.hrd.partials.info-table-salary-offer', ['rows' => $reviewUserRows])

@php
    $profile = $profile ?? $data->candidateProfile ?? null;

    $personalRows = [
        'ID Number (NIK)' => $profile->nik_ktp ?? $data->nik_ktp ?? '-',
        'No. KK' => $profile->no_kk ?? '-',
        'NPWP' => $profile->no_npwp ?? '-',
        'BPJS Kesehatan' => $profile->no_bpjs_ks ?? $data->bpjs_kesehatan ?? '-',
        'BPJS Ketenagakerjaan' => $profile->no_bpjs_tk ?? $data->bpjs_ketenagakerjaan ?? '-',
        'Religion' => $profile->agama ?? $data->agama ?? '-',
        'Marital Status' => $profile->status_pernikahan ?? $data->status_pernikahan ?? $data->status_nikah ?? '-',
        'Salutation' => $profile->nama_panggilan ?? $data->nama_panggilan ?? '-',
        'Birth Place' => $data->tempat_lahir ?? '-',
        'Date Of Birth' => \App\Services\HrdEmailViewData::formatTanggalLahir($data),
        'Gender' => $data->jenis_kelamin ?? $data->gender ?? '-',
        'Email' => $data->email ?? '-',
        'Status Tempat Tinggal' => $profile->status_tempat_tinggal ?? '-',
        'Kontak Darurat' => !empty($profile->nama_kontak_darurat)
            ? ($profile->nama_kontak_darurat . ' (' . ($profile->hubungan_kontak_darurat ?? 'Kontak') . ') - ' . ($profile->no_telepon_darurat ?? '-'))
            : '-',
    ];
@endphp

<p style="{{ $sectionTitle }}">Personal Information</p>
@include('TemplateEmail.hrd.partials.info-table-salary-offer    ', ['rows' => $personalRows])

@php
    $medical = $medical ?? $data->candidateMedicalInformation ?? null;

    $medicalRows = [
        'Tinggi Badan' => !empty($medical->tinggi_badan) ? (is_numeric($medical->tinggi_badan) ? $medical->tinggi_badan . ' cm' : $medical->tinggi_badan) : ($data->tinggi_badan ?? '-'),
        'Berat Badan' => !empty($medical->berat_badan) ? (is_numeric($medical->berat_badan) ? $medical->berat_badan . ' kg' : $medical->berat_badan) : ($data->berat_badan ?? '-'),
        'Mata' => $medical->mata ?? $data->mata ?? '-',
        'Golongan Darah' => $medical->golongan_darah ?? $profile->golongan_darah ?? $data->golongan_darah ?? '-',
        'Penyakit Bawaan Lahir' => $medical->penyakit_bawaan_lahir ?? $data->penyakit_bawaan_lahir ?? '-',
        'Penyakit Kronis' => $medical->penyakit_kronis ?? $data->penyakit_kronis ?? '-',
        'Riwayat Kecelakaan' => $medical->riwayat_kecelakaan ?? $data->riwayat_kecelakaan ?? '-',
    ];
@endphp

<p style="{{ $sectionTitle }}">Medical Information</p>
@include('TemplateEmail.hrd.partials.info-table-salary-offer', ['rows' => $medicalRows])

@php
    $addressRows = [
        'Phone' => $data->no_telepon ?? $data->no_hp ?? '-',
        'Current Address' => !empty($profile->alamat_domisili)
            ? ($profile->alamat_domisili)
            : ($data->alamat_domisili ?? '-'),
        'KTP Address' => !empty($profile->alamat_ktp)
            ? ($profile->alamat_ktp)
            : ($data->alamat_ktp ?? '-'),
    ];
@endphp

<p style="{{ $sectionTitle }}">Address & Phone</p>
@include('TemplateEmail.hrd.partials.info-table-salary-offer', ['rows' => $addressRows])

@if(!empty($pendidikan))
    <p style="{{ $sectionTitle }}">Education</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="{{ $cardStyle }}">
        @foreach($pendidikan as $item)
            <tr>
                <td style="padding:14px 16px;font-size:13px;line-height:1.6;color:#0f172a;border-top:1px solid #e2e8f0;">
                    <strong>{{ ($item['jenjang'] ?? '-') . ' - ' . ($item['jurusan'] ?? '-') }}</strong><br>
                    {{ $item['institusi'] ?? '-' }} ·
                    {{ ($item['tahun_masuk'] ?? '-') . ' - ' . ($item['tahun_lulus'] ?? '-') }}
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if(!empty($pengalamanKerja))
    <p style="{{ $sectionTitle }}">Job Experience</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="{{ $cardStyle }}">
        @foreach($pengalamanKerja as $item)
            <tr>
                <td style="padding:14px 16px;font-size:13px;line-height:1.6;color:#0f172a;border-top:1px solid #e2e8f0;">
                    <strong>{{ $item['posisi_kerja'] ?? '-' }}</strong> di {{ $item['nama_perusahaan'] ?? '-' }}<br>
                    {{ ($item['mulai_kerja'] ?? '-') . ' s/d ' . ($item['akhir_kerja'] ?? '-') }}<br>
                    <span style="color:#64748b;">Alasan keluar: {{ $item['alasan_keluar'] ?? '-' }}</span>
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if(!empty($skills))
    <p style="{{ $sectionTitle }}">Skill</p>
    @include('TemplateEmail.hrd.partials.list-table', ['items' => collect($skills)->map(fn($s) => 'Keahlian: ' . ($s['keahlian'] ?? '-') . ' · Rate: ' . ($s['rate'] ?? '-'))->all()])
@endif

@if(!empty($skillBahasa))
    <p style="{{ $sectionTitle }}">Language Skill</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="{{ $cardStyle }}">
        @foreach($skillBahasa as $lang)
            <tr>
                <td style="{{ $labelStyle }};border-top:1px solid #e2e8f0;">{{ $lang['bahasa'] ?? '-' }}</td>
                <td style="{{ $valueStyle }};border-top:1px solid #e2e8f0;">
                    Baca {{ $lang['baca'] ?? '-' }} · Tulis {{ $lang['tulis'] ?? '-' }} · Dengar {{ $lang['dengar'] ?? '-' }} ·
                    Bicara {{ $lang['bicara'] ?? '-' }}
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if(!empty($organisasi))
    <p style="{{ $sectionTitle }}">Organization Activities</p>
    @include('TemplateEmail.hrd.partials.list-table', ['items' => collect($organisasi)->map(fn($o) => ($o['posisi'] ?? '-') . ' di ' . ($o['nama'] ?? '-') . ' (' . ($o['mulai_org'] ?? '-') . ' - ' . ($o['akhir_org'] ?? '-') . ')')->all()])
@endif

@if(!empty($sertifikat))
    <p style="{{ $sectionTitle }}">Certification</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="{{ $cardStyle }}">
        @foreach($sertifikat as $cert)
            <tr>
                <td style="padding:14px 16px;font-size:13px;line-height:1.6;color:#0f172a;border-top:1px solid #e2e8f0;">
                    <strong>{{ $cert['nama'] ?? '-' }}</strong> · {{ $cert['tipe'] ?? '-' }}<br>
                    No. {{ $cert['nomor'] ?? '-' }} ·
                    {{ ($cert['tanggal_sertifikasi'] ?? '-') . ' s/d ' . ($cert['tanggal_expired'] ?? '-') }}
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if(!empty($kursus))
    <p style="{{ $sectionTitle }}">Course Information</p>
    @include('TemplateEmail.hrd.partials.list-table', ['items' => collect($kursus)->map(fn($k) => ($k['nama'] ?? '-') . ' di ' . ($k['institusi'] ?? '-') . ' (' . ($k['mulai_kursus'] ?? '-') . ' - ' . ($k['akhir_kursus'] ?? '-') . ')')->all()])
@endif