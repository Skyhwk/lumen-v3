<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-family:roboto,sans-serif;">
    <tr>
        <td width="35%" style="vertical-align:middle;padding-bottom:4px;">
            @php
                $logoPath = public_path('img/isl_logo.png');
                if (!file_exists($logoPath)) {
                    $logoPath = public_path('isl_logo.png');
                }
            @endphp
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="ISL" style="height:42px;">
            @else
                <span style="font-size:12px;font-weight:bold;color:#1a4f8f;">INTI SURYA LABORATORIUM</span>
            @endif
        </td>
        <td width="65%" style="vertical-align:middle;text-align:right;padding-bottom:4px;">
            <div style="font-size:12px;font-weight:bold;color:#888;">PT INTI SURYA LABORATORIUM</div>
            <div style="font-size:22px;font-weight:bold;color:#888;letter-spacing:1px;line-height:1.2;">
                {{ strtoupper($document->nama_dokumen ?: 'PANDUAN SISTEM') }}
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
        <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding: 0 10px;font-size:11px;">NO. DOKUMEN</td>
        <td colspan="9" style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:4px 6px;font-size:11px;">{{ strtoupper($document->header_dokumen ?? '-') }}</td>
    </tr>
    <tr>
        <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:6px 4px;font-size:12px;">{{ $document->no_dokumen ?? '-' }}</td>
        <td colspan="9" style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:6px 8px;font-size:19px;">
            {{ strtoupper($document->sub_header_dokumen ?: '-') }}
        </td>
    </tr>
    <tr>
        <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:3px 2px;font-size:9px;">TANGGAL CETAK</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#333;padding:3px 2px;font-size:9px;">
            @if($document->tanggal_cetak)
                {{ \Carbon\Carbon::parse($document->tanggal_cetak)->locale('id')->isoFormat('D-MMMM-Y') }}
            @else
                -
            @endif
        </td>
        <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:3px 2px;font-size:9px;">TERBITAN</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#333;padding:3px 2px;font-size:9px;">{{ $document->terbitan ?? '-' }}</td>
        <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:3px 2px;font-size:9px;">REVISI</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#333;padding:3px 2px;font-size:9px;">{{ $document->revisian ?? '-' }}</td>
        <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:3px 2px;font-size:9px;">CETAKAN</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#333;padding:3px 2px;font-size:9px;">{{ $document->cetakan ?? '-' }}</td>
        <td style="border:0.25pt solid #000;text-align:center;font-weight:bold;color:#888;padding:3px 2px;font-size:9px;">HALAMAN</td>
        <td style="border:0.25pt solid #000;text-align:center;color:#333;padding:3px 2px;font-size:9px;">{PAGENO} / {nbpg}</td>
    </tr>
</table>
