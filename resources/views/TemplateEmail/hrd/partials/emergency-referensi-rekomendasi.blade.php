@php
    $sectionTitle = $sectionTitle ?? 'margin:0 0 12px 0;font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#2563eb;';
    $cardStyle = $cardStyle ?? 'width:100%;border-collapse:separate;border-spacing:0;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin:0 0 20px 0;';
    $infoTableView = $infoTableView ?? 'TemplateEmail.hrd.partials.info-table-salary-offer';

    $record = $data ?? $recruitment ?? null;
    $profileModel = $profile ?? null;
    if (!$profileModel && $record && !empty($record->candidateProfile)) {
        $profileModel = $record->candidateProfile;
    }
    if (!$profileModel && $record && !empty($record->id)) {
        $profileModel = \App\Models\CandidateProfile::where('new_recruitment_id', $record->id)->first();
    }

    $emergencyRows = \App\Services\HrdEmailViewData::emergencyContactRows($profileModel, $record);
    $referensiList = $referensi ?? \App\Services\HrdEmailViewData::normalizePersonReferenceList($record->referensi ?? null);
    $rekomendasiList = $rekomendasi ?? \App\Services\HrdEmailViewData::normalizePersonRekomendasiList($record->rekomendasi ?? null);
@endphp

<p style="{{ $sectionTitle }}">Informasi Kontak Darurat</p>
@include($infoTableView, ['rows' => $emergencyRows])

<p style="{{ $sectionTitle }}">Informasi Referensi</p>
@if(!empty($referensiList))
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="{{ $cardStyle }}">
        @foreach($referensiList as $ref)
            <tr>
                <td style="padding:14px 16px;font-size:13px;line-height:1.6;color:#0f172a;{{ $loop->first ? '' : 'border-top:1px solid #e2e8f0;' }}">
                    {{ \App\Services\HrdEmailViewData::formatPersonReferenceLine($ref) }}
                </td>
            </tr>
        @endforeach
    </table>
@else
    @include($infoTableView, ['rows' => ['Referensi' => 'Tidak ada data']])
@endif

<p style="{{ $sectionTitle }}">Rekomendasi / Kenalan Internal</p>
@if(!empty($rekomendasiList))
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="{{ $cardStyle }}">
        @foreach($rekomendasiList as $rek)
            <tr>
                <td style="padding:14px 16px;font-size:13px;line-height:1.6;color:#0f172a;{{ $loop->first ? '' : 'border-top:1px solid #e2e8f0;' }}">
                    {{ \App\Services\HrdEmailViewData::formatPersonRekomendasiLine($rek) }}
                </td>
            </tr>
        @endforeach
    </table>
@else
    @include($infoTableView, ['rows' => ['Rekomendasi internal' => 'Tidak ada data']])
@endif

<p style="{{ $sectionTitle }}">Sumber Informasi Lowongan</p>
@include($infoTableView, [
    'rows' => [
        'Mendapatkan informasi dari' => \App\Services\HrdEmailViewData::formatSumberInformasi($record),
    ],
])
