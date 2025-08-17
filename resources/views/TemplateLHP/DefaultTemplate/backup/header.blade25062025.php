<table width="100%" style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
    @if ($mode == 'downloadWSDraft' || $mode == 'downloadLHP')
        <tr>
            <td style="text-align: center;">
                <span style="font-weight: bold; border-bottom: 1px solid #000">LAPORAN HASIL PENGUJIAN</span>
            </td>
        </tr>
    @elseif ($mode == 'downloadLHPFinal')
    @php

    @endphp
        <tr>
            <td style="width: 33.33%; text-align: left; padding-left: 30px; vertical-align: top;">
                <img src="{{ public_path('img/isl_logo.png') }}" alt="ISL" style="height: 40px;">
            </td>
            <td style="width: 33.33%; text-align: center; vertical-align: middle;">
                <span style="font-weight: bold; border-bottom: 1px solid #000;">LAPORAN HASIL PENGUJIAN</span>
            </td>
            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                @if ($showKan)
                <img src="{{ public_path('img/logo_kan.png') }}" alt="KAN" style="height: 50px;">
                @endif
            </td>
        </tr>
    @endif
</table>

@include($view . '.right', ['header' => $header, 'detail' => $detail, 'custom' => $custom, 'mode' => $mode])
