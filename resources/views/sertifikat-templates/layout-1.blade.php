<!-- Text Container -->
<div class="text-container">
    <div class="certificate-name">{{ htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') }}</div>

    <!-- Webinar Detail Container -->
    <div class="webinar-detail-container" style="margin-top: 20px; text-align: center;">
        <div class="webinar-title">Telah Mengikuti {{ $webinar_title }}</div>
        <div class="webinar-topic" style="font-weight: bold;">{{ $webinar_topic }}</div>
        {!! $webinar_date !!}
    </div>

    <!-- Template Content (Pemateri & QR Code) -->
    <div class="pemateri-container" style="margin-top: 20px; text-align: center;">
        <div style="font-weight: bold; margin-bottom: 10px; font-size: 12pt;">Pemateri</div>
        <ul>
            @foreach($pemateri as $item)
            <li>{{ $item }}</li>
            @endforeach
        </ul>
    </div>
</div>

<!-- QR Code Container -->
<div class="qr-code-container" style="top: 80%;text-align: right;">
    @if($qr_code)
        {!! $qr_code !!}
    @endif
</div>