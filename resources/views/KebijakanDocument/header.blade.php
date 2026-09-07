<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-family:roboto,sans-serif;">
    <tr>
        <td width="30%" style="vertical-align:middle;padding-bottom:4px;">
            @php
                $logoPath = public_path('img/isl_logo.png');
                if (!file_exists($logoPath)) {
                    $logoPath = public_path('isl_logo.png');
                }
            @endphp
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="ISL" style="height:50px; padding-bottom: 5px;">
            @else
                <!-- <span style="font-size:12px;font-weight:bold;color:#1a4f8f;">INTI SURYA LABORATORIUM</span> -->
                <span style="font-size:12px;font-weight:bold;color:#000;">INTI SURYA LABORATORIUM</span>
            @endif
        </td>
        <td width="70%" style="vertical-align:middle;text-align:right;padding-bottom:4px;">
            <!-- <div style="font-size:12px;font-weight:bold;color:#888;">PT INTI SURYA LABORATORIUM</div>
            <div style="font-size:22px;font-weight:bold;color:#888;letter-spacing:1px;line-height:1.2;"> -->
            <div style="font-size:13px;font-weight:bold;color:#000;">PT INTI SURYA LABORATORIUM</div>
            <div style="font-size:22px;font-weight:bold;color:#000;letter-spacing:1px;line-height:1.2;">
                {{ strtoupper($meta['doc_type_title'] ?? 'KETETAPAN PERUSAHAAN') }}
            </div>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-family:roboto,sans-serif;border:0.25pt solid #000;table-layout:fixed;">
    <colgroup>
        <col style="width:11%">
        <col style="width:11%">
        <col style="width:10%">
        <col style="width:6%">
        <col style="width:9%">
        <col style="width:6%">
        <col style="width:10%">
        <col style="width:6%">
        <col style="width:10%">
        <col style="width:21%">
    </colgroup>
    <tr>
        <!-- <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:0 10px;font-size:11px;">NO. DOKUMEN</td>
        <td colspan="9" style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:4px 6px;font-size:15px;">{{ strtoupper($meta['header_dokumen'] ?? '-') }}</td> -->
        <td style="border:0.25pt solid #000;text-align:center;color:#000;padding:0 10px;font-size:11px;">NO. DOKUMEN</td>
        <td colspan="9" style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#000;padding:4px 6px;font-size:16px;">{{ strtoupper($meta['header_dokumen'] ?? '-') }}</td>
    </tr>
    <tr>
        <!-- <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:6px 4px;font-size:12px;">{{ $meta['no_dokumen'] ?? '-' }}</td>
        <td colspan="9" style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:6px 8px;font-size:19px;"> -->
        <td style="border:0.25pt solid #000;text-align:center;color:#000;padding:6px 4px;font-size:11px;">{{ $meta['no_dokumen'] ?? '-' }}</td>
        <td colspan="9" style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#000;padding:6px 8px;font-size:19px;">
            {{ strtoupper($meta['sub_header_dokumen'] ?? '-') }}
        </td>
    </tr>
    <tr>
        <!-- <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:3px 2px;font-size:10px;">TANGGAL CETAK</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#333;padding:3px 2px;font-size:9px;"> -->
        <td style="border:0.25pt solid #000;text-align:center;color:#000;padding:3px 2px;font-size:11px;">TANGGAL KETETAPAN</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#000;padding:3px 2px;font-size:11px;">
            @if(!empty($meta['tanggal_ketetapan']))
                {{ app(\App\Services\RenderKebijakanDocumentPdf::class)->formatIndonesianDate($meta['tanggal_ketetapan']) }}
            @else
                -
            @endif
        </td>
        <!-- <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:3px 2px;font-size:9px;">TERBITAN</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#333;padding:3px 2px;font-size:9px;">{{ $meta['terbitan'] ?? '-' }}</td>
        <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:3px 2px;font-size:9px;">REVISI</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#333;padding:3px 2px;font-size:9px;">{{ $meta['revisian'] ?? '-' }}</td>
        <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:3px 2px;font-size:9px;">CETAKAN</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#333;padding:3px 2px;font-size:9px;">{{ $meta['cetakan'] ?? '-' }}</td>
        <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:3px 2px;font-size:9px;">HALAMAN</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#333;padding:3px 2px;font-size:9px;">{PAGENO} / {nbpg}</td> -->
        <td style="border:0.25pt solid #000;text-align:center;color:#000;padding:3px 2px;font-size:11px;">TERBITAN</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#000;padding:3px 2px;font-size:11px;">{{ $meta['terbitan'] ?? '-' }}</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#000;padding:3px 2px;font-size:11px;">REVISI</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#000;padding:3px 2px;font-size:11px;">{{ $meta['revisian'] ?? '-' }}</td>
        <!-- <td style="border:0.25pt solid #000;text-align:center;color:#000;padding:3px 2px;font-size:11px;">CETAKAN</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#000;padding:3px 2px;font-size:11px;">{{ $meta['cetakan'] ?? '-' }}</td> -->
        <td style="border:0.25pt solid #000;text-align:center;color:#000;padding:3px 2px;font-size:11px;">HALAMAN</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#000;padding:3px 2px;font-size:11px;">{PAGENO} / {nbpg}</td>
    </tr>
</table>
