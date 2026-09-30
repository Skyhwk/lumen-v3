@php
    use App\Services\SalaryAdjustmentEmailViewData as EmailData;
    use App\Services\SalaryAdjustmentEmailService as EmailRole;
    $request = $bundle['request'] ?? ($data ?? []);
    $showHrd = EmailData::showHrdColumn($request);
    $showFinanceDiff = EmailData::showFinanceDiff($request);
    $stageLabel = $approverLabel ?? EmailRole::LABEL_APPROVAL;
    $isFinalStage = ($approverRole ?? '') === EmailRole::ROLE_BAPAK;
@endphp

@include('TemplateEmail.hrd.partials.salary-adjustment-shell-open', [
    'title' => 'Permohonan Penyesuaian Gaji - ' . $stageLabel,
    'heading' => $stageLabel,
    'subheading' => 'Dokumen evaluasi internal karyawan — mohon keputusan melalui tombol di bawah',
])

<p style="margin:0 0 20px 0;font-size:14px;line-height:1.75;color:#3f3f46;">
    Yth. Direktur,
</p>

<p style="margin:0 0 24px 0;font-size:14px;line-height:1.75;color:#52525b;">
    @if($isFinalStage)
        Berikut permohonan penyesuaian gaji karyawan internal yang telah melalui evaluasi HRD, review Finance,
        dan tahap {{ EmailRole::LABEL_APPROVAL }}. Mohon keputusan {{ EmailRole::LABEL_APPROVAL_FINAL }} melalui tombol di bawah.
    @else
        Berikut permohonan penyesuaian gaji karyawan internal yang telah melalui evaluasi HRD dan review Finance.
        Mohon keputusan {{ EmailRole::LABEL_APPROVAL }} melalui tombol di bawah.
    @endif
</p>

@if($isFinalStage && !empty($request['ibu_approved_at']))
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px 0;border:1px solid #bbf7d0;background-color:#f0fdf4;">
        <tr>
            <td style="padding:12px 16px;">
                <strong style="font-size:12px;color:#166534;">Tahap Sebelumnya</strong>
                <p style="margin:6px 0 0 0;font-size:13px;line-height:1.65;color:#3f3f46;">
                    {{ EmailRole::LABEL_APPROVAL }} telah disetujui pada {{ EmailData::formatLogTime($request['ibu_approved_at']) }}.
                </p>
            </td>
        </tr>
    </table>
@endif

@include('TemplateEmail.hrd.partials.salary-adjustment-employee-header', ['request' => $request])

{{-- Nominal --}}
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin:0 0 8px 0;border:1px solid #e4e4e7;">
    <tr>
        <td style="padding:10px 14px;background-color:#f4f4f5;border-bottom:1px solid #e4e4e7;">
            <strong style="font-size:12px;color:#18181b;text-transform:uppercase;letter-spacing:0.06em;">Komposisi Gaji Saat Ini</strong>
        </td>
    </tr>
    <tr>
        <td style="padding:14px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="stack-column" style="padding:4px 12px 4px 0;vertical-align:top;width:50%;">
                        <span style="font-size:11px;color:#71717a;display:block;">Gaji Pokok</span>
                        <strong style="font-size:15px;color:#18181b;">{{ EmailData::formatRupiah($request['current_gaji_pokok'] ?? 0) }}</strong>
                    </td>
                    <td class="stack-column" style="padding:4px 0;vertical-align:top;width:50%;">
                        <span style="font-size:11px;color:#71717a;display:block;">Tunjangan Kerja</span>
                        <strong style="font-size:15px;color:#18181b;">{{ EmailData::formatRupiah($request['current_tunjangan_kerja'] ?? 0) }}</strong>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin:0 0 20px 0;">
    <tr>
        <td class="stack-column" width="{{ $showHrd ? '33' : '50' }}%" style="padding:0 6px 0 0;vertical-align:top;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e4e4e7;">
                <tr><td style="padding:10px 12px;background-color:#f4f4f5;border-bottom:1px solid #e4e4e7;font-size:11px;font-weight:700;color:#52525b;text-transform:uppercase;letter-spacing:0.04em;">Pengajuan User</td></tr>
                @foreach(EmailData::nominalBlock($request, 'submitted') as $label => $value)
                    <tr>
                        <td style="padding:8px 12px;{{ $loop->first ? '' : 'border-top:1px solid #e4e4e7;' }}">
                            <span style="font-size:11px;color:#71717a;display:block;">{{ $label }}</span>
                            <strong style="font-size:13px;color:#18181b;">{{ $value }}</strong>
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>

        @if($showHrd)
        <td class="stack-column" width="33%" style="padding:0 3px;vertical-align:top;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #d4d4d8;">
                <tr><td style="padding:10px 12px;background-color:#f4f4f5;border-bottom:1px solid #e4e4e7;font-size:11px;font-weight:700;color:#52525b;text-transform:uppercase;letter-spacing:0.04em;">Keputusan HRD</td></tr>
                @foreach(EmailData::nominalBlock($request, 'hrd') as $label => $value)
                    <tr>
                        <td style="padding:8px 12px;{{ $loop->first ? '' : 'border-top:1px solid #e4e4e7;' }}">
                            <span style="font-size:11px;color:#71717a;display:block;">{{ $label }}</span>
                            <strong style="font-size:13px;color:#18181b;">{{ $value }}</strong>
                        </td>
                    </tr>
                @endforeach
            </table>
            @if(!empty($request['hrd_final_adjustment_notes']))
                <p style="margin:6px 0 0 0;font-size:11px;color:#71717a;">Catatan Penyesuaian HRD: {{ $request['hrd_final_adjustment_notes'] }}</p>
            @endif
            @if(!empty($request['hrd_approval_notes']))
                <p style="margin:6px 0 0 0;font-size:11px;color:#71717a;">Catatan Approval HR: {{ $request['hrd_approval_notes'] }}</p>
            @endif
        </td>
        @endif

        <td class="stack-column" width="{{ $showHrd ? '33' : '50' }}%" style="padding:0 0 0 6px;vertical-align:top;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:2px solid #2563eb;">
                <tr>
                    <td style="padding:10px 12px;background-color:#eff6ff;border-bottom:1px solid #bfdbfe;font-size:11px;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.04em;">
                        Keputusan Finance
                        @if($showFinanceDiff)
                            <span style="display:inline-block;background-color:#fef3c7;color:#92400e;font-size:10px;padding:2px 6px;border:1px solid #fde68a;margin-left:6px;">Disesuaikan</span>
                        @endif
                    </td>
                </tr>
                @foreach(EmailData::nominalBlock($request) as $label => $value)
                    @if(in_array($label, ['Delta Gaji Pokok', 'Delta Tunjangan', 'Target Gaji Pokok', 'Target Tunjangan'], true))
                        <tr>
                            <td style="padding:8px 12px;{{ $loop->first ? '' : 'border-top:1px solid #e4e4e7;' }}">
                                <span style="font-size:11px;color:#71717a;display:block;">{{ $label }}</span>
                                <strong style="font-size:13px;color:#18181b;">{{ $value }}</strong>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </table>
            @if(!empty($request['finance_final_adjustment_notes']))
                <p style="margin:6px 0 0 0;font-size:11px;color:#71717a;">Catatan Finance: {{ $request['finance_final_adjustment_notes'] }}</p>
            @endif
        </td>
    </tr>
</table>

@if(!empty($request['catatan_tambahan']))
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px 0;border:1px solid #fde68a;background-color:#fffbeb;">
        <tr>
            <td style="padding:12px 16px;">
                <strong style="font-size:12px;color:#92400e;">Catatan Pengaju</strong>
                <p style="margin:6px 0 0 0;font-size:13px;line-height:1.65;color:#78350f;white-space:pre-wrap;">{{ $request['catatan_tambahan'] }}</p>
            </td>
        </tr>
    </table>
@endif

@if(!empty($btn))
    @include('TemplateEmail.hrd.partials.salary-adjustment-action-buttons', ['btn' => $btn])
@endif

<div style="height:1px;background-color:#e4e4e7;margin:28px 0;"></div>

@include('TemplateEmail.hrd.partials.salary-adjustment-full-detail', ['bundle' => $bundle ?? ['request' => $request]])

@include('TemplateEmail.hrd.partials.salary-adjustment-shell-close')
