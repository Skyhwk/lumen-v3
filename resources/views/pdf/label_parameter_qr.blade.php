<!DOCTYPE html>
<html>

<head>
    <style>
        .colom1 {
            text-align: center;
            padding-right: 40px;
        }

        .line {
            border-width: 10;
            color: black;
        }
    </style>
</head>

<body>

    <table width="100%">
        @php
            $counter = 0;
            $date = \Carbon\Carbon::parse($selectedDate)->translatedFormat('d/M/y');
        @endphp

        @foreach ($data as $item)
            @if ($counter % 2 == 0)
                <tr>
            @endif

            @php
                $padding = $counter % 2 == 0 ? '8% 40% 0% 0%' : '8% 0% 0% 0%';
            @endphp

            <td style="text-align: center; padding: {{ $padding }}">
                @php
                    $qrCode = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(80)->margin(0)->generate($item->no_sampel));
                @endphp
                <img src="data:image/svg+xml;base64,{{ $qrCode }}" style="margin-bottom: 5px;"/>
                <div>
                    <span style="font-size: 16px; font-weight: bold;">
                        {{ $item->no_sampel }}
                    </span>
                    <br>
                    <span style="font-size: 12px; font-weight: bold;">
                        {{ $selectedParameter }}
                    </span>
                </div>
            </td>

            @if ($counter % 2 == 1)
                </tr>
            @endif

            @php $counter++; @endphp
        @endforeach
    </table>

</body>

</html>
