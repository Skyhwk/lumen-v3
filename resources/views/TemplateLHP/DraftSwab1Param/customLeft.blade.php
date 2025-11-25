@php
    $data = is_object($custom) && method_exists($custom, 'toArray') ? $custom->toArray() : (array) $custom;

    $data = collect($data)->map(fn($r) => (array) $r);

    $satuan = $data->pluck('satuan')->filter()->first();

@endphp

<div class="left" style="page-break-before: always;">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            <tr>
                <!-- NO: 2 baris -->
                <th width="25" class="pd-5-solid-top-center" style="white-space: nowrap;" rowspan="2">
                    NO
                </th>

                <!-- LOKASI / KETERANGAN SAMPEL: 2 baris -->
                <th width="240" class="pd-5-solid-top-center" style="white-space: nowrap;" rowspan="2">
                    LOKASI / KETERANGAN SAMPEL
                </th>

                <!-- HASIL UJI: baris 1 -->
                <th width="160" class="pd-5-solid-top-center" style="white-space: nowrap;">
                    HASIL UJI
                </th>

                <!-- BAKU MUTU***: baris 1 -->
                <th width="160" class="pd-5-solid-top-center" style="white-space: nowrap;">
                    BAKU MUTU
                </th>

                <th width="160" rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">
                    Tanggal Sampling
                </th>
            </tr>

            <tr>
                <th colspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">
                    Satuan = {{ $satuan }}
                </th>
            </tr>
        </thead>

        <tbody>

            {{-- =======================
             BODY MULTI SAMPEL, 1 PARAMETER
             1 baris = 1 keterangan, superscript = no_sampel
         ======================= --}}
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
                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                        {{ $rowNo }}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-left" style="white-space: nowrap;">
                        <sup>{{ htmlspecialchars($noSampel) }}</sup>&nbsp;{{ htmlspecialchars($keterangan) }}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                        {!! $hasilUji !!}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                        {{ htmlspecialchars($bakuMutu) }}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                        {{ \App\Helpers\Helper::tanggal_indonesia($tanggal_sampling) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
