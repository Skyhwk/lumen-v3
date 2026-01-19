<!-- Text Title -->
<div class="text-title">
    <div class="certificate-title">SERTIFIKAT</div>
    <!-- <div class="certificate-number">{!! $no_sertifikat !!}</div> -->
    <img src="{{ public_path('background-sertifikat/elemen-biru.png') }}" alt="elemen" style="width: 30%; height: auto;">
</div>

<!-- Text Container -->
<div class="text-container">
    <div class="certificate-name">{{ htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') }}</div>

    <!-- Webinar Detail Container -->
    <div class="webinar-detail-container" style="margin-top: 30px; text-align: center;">
        <div class="webinar-title">Telah Mengikuti {{ $webinar_title }}</div>
        <div class="webinar-topic" style="font-weight: bold;">{{ $webinar_topic }}</div>
        <div style="font-size: 18pt; font-weight: bold;">( Peraturan Pemerintah Lingkungan Hidup No. 11 Tahun 2025 )</div>
        {!! $webinar_date !!}
    </div>

    <!-- Template Content (Pemateri & QR Code) -->
    <div class="pemateri-container" style="margin-top: 20px; text-align: center;">
        <div style="font-weight: bold; margin-bottom: 10px; font-size: 12pt;">Pemateri</div>
        <ul style="margin-top: 20px;">
            @foreach($pemateri as $item)
            <li>{!! $item !!}</li>
            @endforeach
        </ul>
    </div>
</div>

<!-- QR Code Container -->
<div class="qr-code-container" style="top: 80%;text-align: right;">
    <div style="font-weight: bold; margin-bottom: 10px; font-size: 7.5pt; color: #fff; margin-bottom: 30px;">{!! $no_sertifikat !!}</div>
    @if($qr_code)
        {!! $qr_code !!}
    @endif
</div>