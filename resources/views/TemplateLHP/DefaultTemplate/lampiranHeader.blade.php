<table width="100%" style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif; {{$mode == 'downloadLHPFinal' ? 'margin-bottom: 33px;' : ''}}">
    @if ($mode == 'downloadWSDraft' || $mode == 'downloadLHP')
        <tr>
        </tr>
    @elseif ($mode == 'downloadLHPFinal')
    @php

    @endphp
        <tr>
            <td style="width: 33.33%; text-align: left; padding-left: 30px; vertical-align: top;">
                <img src="{{ public_path('img/isl_logo.png') }}" alt="ISL" style="height: 40px;">
            </td>
            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                @if ($showKan)
                <img src="{{ public_path('img/logo_kan.png') }}" alt="KAN" style="height: 50px;">
                @endif
            </td>
        </tr>
    @endif
</table>

