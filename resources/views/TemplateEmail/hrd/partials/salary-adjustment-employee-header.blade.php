@php
    use App\Services\SalaryAdjustmentEmailViewData as EmailData;
    $request = $request ?? [];
    $photoSrc = $request['employee_photo'] ?? '';
    if ($photoSrc === '' && !empty($request['employee_photo_url'])) {
        $photoSrc = $request['employee_photo_url'];
    }
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin:0 0 24px 0;border:1px solid #e4e4e7;background-color:#fafafa;">
    <tr>
        <td class="photo-cell" width="200" style="padding:20px;vertical-align:top;text-align:center;border-right:1px solid #e4e4e7;">
            @if($photoSrc !== '')
                <img
                    class="employee-photo"
                    src="{{ $photoSrc }}"
                    alt="Foto {{ $request['nama_lengkap'] ?? 'Karyawan' }}"
                    width="180"
                    height="180"
                    style="display:block;width:180px;height:180px;object-fit:cover;border:1px solid #d4d4d8;background-color:#ffffff;margin:0 auto;"
                />
            @else
                <div class="employee-photo" style="width:180px;height:180px;background-color:#e4e4e7;border:1px solid #d4d4d8;margin:0 auto;line-height:180px;text-align:center;color:#71717a;font-size:12px;">
                    Foto tidak tersedia
                </div>
            @endif
        </td>
        <td style="padding:20px 24px;vertical-align:top;">
            <p style="margin:0 0 4px 0;font-size:11px;font-weight:600;color:#71717a;text-transform:uppercase;letter-spacing:0.08em;">
                Karyawan Internal
            </p>
            <p style="margin:0 0 16px 0;font-size:22px;line-height:1.3;font-weight:700;color:#18181b;">
                {{ $request['nama_lengkap'] ?? '-' }}
            </p>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;">
                @foreach([
                    'NIK Karyawan' => $request['nik_karyawan'] ?? '-',
                    'Jabatan' => $request['jabatan'] ?? '-',
                    'Departemen' => $request['department'] ?? '-',
                    'No. Dokumen' => $request['no_document'] ?? '-',
                    'Manager Pengaju' => $request['manager_nama'] ?? '-',
                    'Bulan Efektif' => EmailData::formatBulanEfektif($request['bulan_efektif'] ?? null),
                ] as $label => $value)
                    <tr>
                        <td style="padding:5px 12px 5px 0;width:38%;font-size:12px;color:#71717a;vertical-align:top;{{ $loop->first ? '' : 'border-top:1px solid #e4e4e7;' }}">{{ $label }}</td>
                        <td style="padding:5px 0;font-size:13px;color:#18181b;font-weight:600;vertical-align:top;{{ $loop->first ? '' : 'border-top:1px solid #e4e4e7;' }}">{{ $value }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
