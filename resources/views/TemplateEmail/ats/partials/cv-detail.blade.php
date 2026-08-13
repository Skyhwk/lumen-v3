@php
    $sectionTitle = 'margin:0 0 12px 0;font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#2563eb;';
    $cardStyle = 'width:100%;border-collapse:separate;border-spacing:0;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin:0 0 20px 0;';
    $labelStyle = 'padding:10px 16px;width:38%;font-size:13px;color:#64748b;border-top:1px solid #e2e8f0;vertical-align:top;';
    $valueStyle = 'padding:10px 16px;font-size:13px;color:#0f172a;font-weight:600;border-top:1px solid #e2e8f0;vertical-align:top;';
@endphp

<p style="margin:0 0 16px 0;font-size:18px;font-weight:700;color:#0f172a;">Candidate Details</p>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="{{ $cardStyle }}">
    <tr>
        @if(!empty($photoUrl))
            <td class="photo-cell stack-column" width="120" style="padding:20px 16px 20px 20px;vertical-align:top;">
                <img src="{{ $photoUrl }}" alt="Foto Kandidat" width="96" height="96" style="display:block;width:96px;height:96px;border-radius:16px;object-fit:cover;border:3px solid #dbeafe;">
            </td>
        @endif
        <td class="stack-column" style="padding:20px;vertical-align:top;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                @foreach([
                    'Name' => $data->nama_lengkap ?? '-',
                    'Placement Location' => $data->nama_cabang ?? '-',
                    'Applied Position' => $data->nama_jabatan ?? '-',
                    'Zodiac' => $data->shio ?? '-',
                    'Element' => $data->elemen ?? '-',
                ] as $label => $value)
                    <tr>
                        <td class="info-label" style="padding:4px 0;width:42%;font-size:13px;color:#64748b;vertical-align:top;">{{ $label }}</td>
                        <td style="padding:4px 0 4px 8px;font-size:13px;color:#0f172a;font-weight:600;vertical-align:top;">: {{ $value }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>



@php
    $personalRows = [
        'Nationality' => $data->kebangsaan ?? '-',
        'Birth Place' => $data->tempat_lahir ?? '-',
        'Gender' => $data->gender ?? '-',
        'Marital Status' => $data->status_nikah ?? '-',
        'Marital Date' => $data->tgl_nikah ?? '-',
        'Marital Place' => $data->tempat_nikah ?? '-',
        'Health Insurance (BPJS)' => $data->bpjs_kesehatan ?? '-',
        'Internal Reference' => $data->orang_dalam ?? '-',
        'ID Number' => $data->nik_ktp ?? '-',
        'Date Of Birth' => $data->tanggal_lahir ?? '-',
        'Religion' => $data->agama ?? '-',
        'Salutation' => $data->nama_panggilan ?? '-',
        'Email' => $data->email ?? '-',
        'ID Expired Date' => $data->tgl_exp_identitas ?? '-',
        'Employment Insurance (BPJS)' => $data->bpjs_ketenagakerjaan ?? '-',
    ];
@endphp

<p style="{{ $sectionTitle }}">Personal Information</p>
@include('TemplateEmail.ats.partials.info-table', ['rows' => $personalRows])

@php
    $med = $medicalInfo ?? null;
    $medicalRows = [
        'Height' => $med->tinggi_badan ?? '-',
        'Weight' => $med->berat_badan ?? '-',
        'Vision' => $med->mata ?? '-',
        'Blood Type' => $med->golongan_darah ?? '-',
        'Congenital Disease' => $med->penyakit_bawaan_lahir ?? '-',
        'Chronic Disease' => $med->penyakit_kronis ?? '-',
        'Accident History' => $med->riwayat_kecelakaan ?? '-',
    ];
@endphp

<p style="{{ $sectionTitle }}">Medical Information</p>
@include('TemplateEmail.ats.partials.info-table', ['rows' => $medicalRows])

@php
    $addressRows = [
        'Phone' => $data->no_hp ?? '-',
        'Current Address' => $data->alamat_ktp ?? '-',
        'KTP Address' => $data->alamat_domisili ?? '-',
    ];
@endphp

<p style="{{ $sectionTitle }}">Address & Phone</p>
@include('TemplateEmail.ats.partials.info-table', ['rows' => $addressRows])

@if(!empty($pendidikan))
    <p style="{{ $sectionTitle }}">Education</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="{{ $cardStyle }}">
        @foreach($pendidikan as $item)
            <tr>
                <td style="padding:14px 16px;font-size:13px;line-height:1.6;color:#0f172a;border-top:1px solid #e2e8f0;">
                    <strong>{{ ($item['jenjang'] ?? '-') . ' - ' . ($item['jurusan'] ?? '-') }}</strong><br>
                    {{ $item['institusi'] ?? '-' }} · {{ ($item['tahun_masuk'] ?? '-') . ' - ' . ($item['tahun_lulus'] ?? '-') }}
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
                    <strong>{{ $item['posisi_kerja'] ?? '-' }}</strong> at {{ $item['nama_perusahaan'] ?? '-' }}<br>
                    {{ ($item['mulai_kerja'] ?? '-') . ' to ' . ($item['akhir_kerja'] ?? '-') }}<br>
                    <span style="color:#64748b;">Reason for leaving: {{ $item['alasan_keluar'] ?? '-' }}</span>
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if(!empty($skills))
    <p style="{{ $sectionTitle }}">Skill</p>
    @include('TemplateEmail.ats.partials.list-table', ['items' => collect($skills)->map(fn($s) => 'Skill: ' . ($s['keahlian'] ?? '-') . ' · Rating: ' . ($s['rate'] ?? '-'))->all()])
@endif

@if(!empty($skillBahasa))
    <p style="{{ $sectionTitle }}">Language Skill</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="{{ $cardStyle }}">
        @foreach($skillBahasa as $lang)
            <tr>
                <td style="{{ $labelStyle }};border-top:1px solid #e2e8f0;">{{ $lang['bahasa'] ?? '-' }}</td>
                <td style="{{ $valueStyle }};border-top:1px solid #e2e8f0;">
                    Read {{ $lang['baca'] ?? '-' }} · Write {{ $lang['tulis'] ?? '-' }} · Listen {{ $lang['dengar'] ?? '-' }} · Speak {{ $lang['bicara'] ?? '-' }}
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if(!empty($organisasi))
    <p style="{{ $sectionTitle }}">Organization Activities</p>
    @include('TemplateEmail.ats.partials.list-table', ['items' => collect($organisasi)->map(fn($o) => ($o['posisi'] ?? '-') . ' at ' . ($o['nama'] ?? '-') . ' (' . ($o['mulai_org'] ?? '-') . ' - ' . ($o['akhir_org'] ?? '-') . ')')->all()])
@endif

@if(!empty($sertifikat))
    <p style="{{ $sectionTitle }}">Certification</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="{{ $cardStyle }}">
        @foreach($sertifikat as $cert)
            <tr>
                <td style="padding:14px 16px;font-size:13px;line-height:1.6;color:#0f172a;border-top:1px solid #e2e8f0;">
                    <strong>{{ $cert['nama'] ?? '-' }}</strong> · {{ $cert['tipe'] ?? '-' }}<br>
                    No. {{ $cert['nomor'] ?? '-' }} · {{ ($cert['tanggal_sertifikasi'] ?? '-') . ' to ' . ($cert['tanggal_expired'] ?? '-') }}
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if(!empty($kursus))
    <p style="{{ $sectionTitle }}">Course Information</p>
    @include('TemplateEmail.ats.partials.list-table', ['items' => collect($kursus)->map(fn($k) => ($k['nama'] ?? '-') . ' at ' . ($k['institusi'] ?? '-') . ' (' . ($k['mulai_kursus'] ?? '-') . ' - ' . ($k['akhir_kursus'] ?? '-') . ')')->all()])
@endif
