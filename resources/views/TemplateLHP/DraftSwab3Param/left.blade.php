@php
    $data = is_object($detail) && method_exists($detail, 'toArray') ? $detail->toArray() : (array) $detail;

    $data = collect($data)->map(fn($r) => (array) $r);

    $satuan = $data->pluck('satuan')->filter()->first();

@endphp

<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            {{-- =======================
                     HEADER SINGLE SAMPEL
                 ======================= --}}
            <tr>
                <th width="25" class="pd-5-solid-top-center" style="white-space: nowrap;">NO</th>
                <th width="240" class="pd-5-solid-top-center" style="white-space: nowrap;">PARAMETER</th>


                <th width="80" class="pd-5-solid-top-center" style="white-space: nowrap;">HASIL UJI</th>

                <th width="80" class="pd-5-solid-top-center" style="white-space: nowrap;">BAKU MUTU</th>
                <th width="80" class="pd-5-solid-top-center" style="white-space: nowrap;">SATUAN</th>
                <th width="80" class="pd-5-solid-top-center" style="white-space: nowrap;">SPESIFIKASI METHOD</th>
            </tr>
        </thead>

        <tbody>
            {{-- =======================
                     BODY SINGLE SAMPEL (VERSI LAMA)
                 ======================= --}}
            @php $totalRows = count($data); @endphp
            @foreach ($data as $kk => $yy)
                @continue(!$yy)
                @php
                    $p = $kk + 1;
                    $rowClass = $p == $totalRows ? 'solid' : 'dot';
                    $akr = !empty($yy['akr']) ? $yy['akr'] : '&nbsp;&nbsp;';
                @endphp
                <tr>
                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{{ $p }}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-left" style="white-space: nowrap;">
                        {!! $akr !!}&nbsp;{{ htmlspecialchars($yy['parameter'] ?? '') }}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                        {!! $yy['hasil_uji'] ?? '-' !!}</td>

                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                        {{ htmlspecialchars($yy['baku_mutu'] ?? '-') }}</td>
                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                        {{ htmlspecialchars($yy['satuan'] ?? '-') }}</td>
                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                        {{ htmlspecialchars($yy['methode'] ?? '-') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
