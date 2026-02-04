@if (!empty($custom))
@php
    $data = $custom;

    $hasTerkoreksi = collect($data)->contains(function ($item) {
        return !empty($item['terkoreksi']) && $item['terkoreksi'] !== '-';
    });

    $total = count($data);
    
    $customRegulasi = json_decode($header->regulasi_custom, true);
    $pages = collect($customRegulasi)->pluck('page')->sort()->values();

    $secondLast = $pages[$pages->count() - 2];
    $last       = $pages[$pages->count() - 1];
@endphp

<div class="left" style="page-break-before: always;">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
    <tr>
        <th width="25" rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center" style="white-space: nowrap;">NO</th>
        <th width="250" rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center" style="white-space: nowrap;">PARAMETER</th>

        @if ($hasTerkoreksi)
            <th colspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">HASIL UJI</th>
        @else
            <th rowspan="1" class="pd-5-solid-top-center" style="white-space: nowrap;">HASIL UJI</th>
        @endif
        @if ($page != $last)
        <th width="75" rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center" style="white-space: nowrap;">BAKU MUTU</th>
        @endif
        <th rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center" style="white-space: nowrap;">SATUAN</th>
        <th width="200" rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center" style="white-space: nowrap;">SPESIFIKASI METODE</th>
    </tr>

    @if ($hasTerkoreksi)
        <tr>
            <th class="pd-5-solid-top-center" style="white-space: nowrap;">TERUKUR</th>
            <th class="pd-5-solid-top-center" style="white-space: nowrap;">TERKOREKSI</th>
        </tr>
    @endif
</thead>
    <tbody>
        @foreach ($data as $kk => $yy)
            @continue(!$yy)
            @php
                $p = $kk + 1;
                $rowClass = ($p == $total) ? 'solid' : 'dot';
                $akr = !empty($yy['akr']) ? $yy['akr'] : '&nbsp;&nbsp;';
            @endphp
            <tr>
                <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{{ $p }}</td>
                <td class="pd-5-{{ $rowClass }}-left" style="white-space: nowrap;">
                    {!! $akr !!}&nbsp;{{ htmlspecialchars($yy['parameter'] ?? '') }}
                </td>

                {{-- hasil ukur --}}
                <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{!! $yy['hasil_uji'] ?? '' !!}</td>

                {{-- hasil terkoreksi (hanya jika ada koreksi) --}}
                @if ($hasTerkoreksi)
                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{!! $yy['terkoreksi'] ?? '' !!}</td>
                @endif
                @if ($page != $last)
                <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{{ htmlspecialchars($yy['baku_mutu'] ?? '') }}</td>
                @endif
                <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{{ htmlspecialchars($yy['satuan'] ?? '') }}</td>
                <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{{ htmlspecialchars($yy['spesifikasi_metode'] ?? '') }}</td>
            </tr>
        @endforeach
    </tbody>

    </table>
</div>
@endif
