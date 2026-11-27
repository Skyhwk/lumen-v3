@php
    $count = count($items);
@endphp
@foreach ($items as $i => $name)
    <tr>
        @if ($i === 0)
            <td width="28%" rowspan="{{ $count }}" class="custom1 custom6" style="text-align: center;">{{ $label }}</td>
        @endif
        <td class="custom6" style="text-align: center;">{{ $name }}</td>
        <td width="18%" class="custom6"></td>
    </tr>
@endforeach
