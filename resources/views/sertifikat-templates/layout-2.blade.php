<!-- Text Container -->
<div class="text-container">
    <div class="certificate-name">{{ htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') }}</div>

    <!-- Webinar Detail Container -->
    <div class="webinar-detail-container" style="margin-top: 20px; text-align: center;">
        <div class="webinar-title">Telah Mengikuti {{ $webinar_title }}</div>
        <div class="webinar-topic" style="font-weight: bold;">{{ $webinar_topic }}</div>
        {!! $webinar_date !!}
    </div>

    <!-- Pemateri -->
    @php
    $pemateriLength = count($pemateri);
    $pemateriHalf = (int) ceil($pemateriLength / 2);
    $pemateriLeft = array_slice($pemateri, 0, $pemateriHalf);
    $pemateriRight = array_slice($pemateri, $pemateriHalf);
    @endphp

    <div class="pemateri-container" style="margin-top: 20px; text-align: center;">
        <div style="font-weight: bold; margin-bottom: 10px; font-size: 12pt;">
            Pemateri
        </div>

        <table width="70%" align="center" cellpadding="0" cellspacing="0">
            <tr>
                <td width="50%" valign="top" style="text-align: center;">
                    <ul style="list-style: none; margin: 0; padding: 0;">
                        @foreach($pemateriLeft as $item)
                        <li style="list-style: none; margin-bottom: 8px;">{!! $item !!}</li>
                        @endforeach
                    </ul>
                </td>

                <td width="50%" valign="top" style="text-align: center;">
                    <ul style="list-style: none; margin: 0; padding: 0;">
                        @foreach($pemateriRight as $item)
                        <li style="list-style: none; margin-bottom: 8px;">{!! $item !!}</li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- QR Code Container -->
<div class="qr-code-container" style="top: 80%;text-align: center;">
    @if($qr_code)
    {!! $qr_code !!}
    @endif
</div>