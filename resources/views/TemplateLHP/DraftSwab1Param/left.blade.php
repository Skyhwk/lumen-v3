@php
    $data = is_object($detail) && method_exists($detail, 'toArray') ? $detail->toArray() : (array) $detail;

    $data = collect($data)->map(fn($r) => (array) $r);

    $satuan = $data->pluck('satuan')->filter()->first();

@endphp

<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            <tr>
                <!-- NO: 2 baris -->
                <th width="10%" class="pd-5-solid-top-center" rowspan="2">
                    NO
                </th>

                <!-- LOKASI / KETERANGAN SAMPEL: 2 baris -->
                <th width="45%" class="pd-5-solid-top-center" rowspan="2" colspan="2">
                    LOKASI / KETERANGAN SAMPEL
                </th>

                <!-- HASIL UJI: baris 1 -->
                <th width="10%" class="pd-5-solid-top-center">
                    HASIL UJI
                </th>

                <!-- BAKU MUTU***: baris 1 -->
                <th width="15%" class="pd-5-solid-top-center">
                    BAKU MUTU
                </th>

                <th width="20%" rowspan="2" class="pd-5-solid-top-center">
                    Tanggal Sampling
                </th>
            </tr>

            <tr>
                <th colspan="2" class="pd-5-solid-top-center">
                    Satuan = {{ $satuan }}
                </th>
            </tr>
        </thead>

        <tbody>

            @php
                $rowNo = 0;
                $groupedByKet = $data->groupBy('keterangan');
            @endphp

            @foreach ($groupedByKet as $keterangan => $rows)
                @php
                    $rowNo++;
                    $rows = collect($rows)->map(fn($r) => (array) $r);
                    $ref = $rows->first();

                    $rowClass = $rowNo == $groupedByKet->count() ? 'solid' : 'dot';

                    $noSampel = $ref['no_sampel'] ?? '';
                    $hasilUji = $ref['hasil_uji'] ?? '-';
                    $bakuMutu = $ref['baku_mutu'] ?? '-';
                    $satuan = $ref['satuan'] ?? '-';
                    $tanggal_sampling = $ref['tanggal_sampling'] ?? '-';
                @endphp

                <tr>
                    <td class="pd-5-{{ $rowClass }}-center">
                        {{ $rowNo }}
                    </td>
                    <td class="pd-3-{{ $rowClass }}" width="8%" style="text-align: right; border-right: none;">
                        <sup style="font-size: 5px; margin-top: -10px;">{{ $noSampel }}</sup>
                    </td>
                    <td class="pd-3-{{ $rowClass }}" width="37%" style="border-left: none; text-align: left;">
                        {{ $keterangan }}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-center">
                        {!! $hasilUji !!}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-center">
                        {{ $bakuMutu }}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-center">
                        {{ \App\Helpers\Helper::tanggal_indonesia($tanggal_sampling) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
