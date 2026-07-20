@php
    $pdfService = app(\App\Services\RenderLimsPsDocumentPdf::class);
    $verification = $document->approvals->where('action', 'verify')->first();
    $approvals = $document->approvals->where('action', 'approve')->values();
    $legalization = $document->approvals->where('action', 'legalize')->first();
    $pengesahanDate = $document->tanggal_pengesahan ?? optional($legalization)->approved_at;
@endphp

<div class="doc-content">
    {!! $content !!}
</div>

<div class="auth-section">
    @if($legalization)
    <div class="auth-title">
        Tanggal Pengesahan : {{ $pdfService->formatIndonesianDate($pengesahanDate) }}
    </div>
    @endif

    <div class="auth-note">
        <ul>
            <li>Dokumen ini disusun, ditinjau, dan disahkan melalui sistem manajemen dokumen elektronik yang terintegrasi dalam sistem manajemen laboratorium.</li>
            <li>Seluruh proses persetujuan dilakukan secara digital melalui mekanisme otorisasi berbasis akun pengguna yang telah ditetapkan sesuai dengan tanggung jawab dan kewenangan masing-masing fungsi.</li>
        </ul>
    </div>

    <table class="auth-table">
        <thead>
            <tr>
                <th style="width:18%;"></th>
                <th style="width:28%;">Nama</th>
                <th style="width:28%;">Jabatan</th>
                <th style="width:26%;">Tanggal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="auth-role">Disusun Oleh</td>
                <td>{{ $document->disusun_oleh ?? '-' }}</td>
                <td>{{ $document->jabatan_penyusun ?? '-' }}</td>
                <td>{{ $pdfService->formatIndonesianDate($document->tanggal_disusun ?? $document->created_at) }}</td>
            </tr>

            @if($verification)
                <tr>
                    <td class="auth-role">Diverifikasi Oleh</td>
                    <td>{{ $verification->nama }}</td>
                    <td>{{ $verification->jabatan ?? '-' }}</td>
                    <td>{{ $pdfService->formatIndonesianDate($verification->approved_at) }}</td>
                </tr>
            @endif

            @if($approvals->isNotEmpty())
                @foreach($approvals as $index => $approval)
                    <tr>
                        <td class="auth-role">{{ $index === 0 ? 'Disetujui Oleh' : '' }}</td>
                        <td>{{ $approval->nama }}</td>
                        <td>{{ $approval->jabatan ?? '-' }}</td>
                        <td>{{ $pdfService->formatIndonesianDate($approval->approved_at) }}</td>
                    </tr>
                @endforeach
            @endif

            @if($legalization)
            <tr>
                <td class="auth-role">Disahkan Oleh</td>
                <td>{{ $legalization->nama ?? $document->pengesahan ?? '-' }}</td>
                <td>{{ $legalization->jabatan ?? '-' }}</td>
                <td>{{ $pdfService->formatIndonesianDate($legalization->approved_at ?? $document->disahkan_pada) }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
