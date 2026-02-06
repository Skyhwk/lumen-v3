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
                <span style="font-size: 18px; font-weight: bold;">
                    {{ $item->no_sampel }}
                </span>
                <br>

                <span style="font-size: 14px; font-weight: bold;">
                    {{ $selectedParameter }}
                </span>
                <br>

                <hr>

                <span style="font-size: 16px; font-weight: bold;">
                    {{ $date }}
                </span>
            </td>

            @if ($counter % 2 == 1)
                </tr>
            @endif

            @php $counter++; @endphp
        @endforeach
    </table>

</body>

</html>
