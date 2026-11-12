@php
    $count = count($rows);
@endphp
@foreach ($rows as $i => $row)
    <tr>
        @if ($i === 0)
            <td rowspan="{{ $count }}" class="custom1 custom6" style="text-align: center;">{!! $label !!}</td>
        @endif
        <td class="custom6" style="text-align: center;">{{ $row['nama'] }}</td>
        <td class="custom6"></td>
        <td class="custom6" style="text-align: center; font-weight: bold;">{{ $row['jumlah'] }}</td>
        <td class="custom6"></td>
    </tr>
@endforeach
