@php
    $data = is_object($detail) && method_exists($detail, 'toArray') ? $detail->toArray() : (array) $detail;

    $data = collect($data)->map(fn($r) => (array) $r);

    $groupedBySampel = $data->groupBy('no_sampel');

    $parameters = $data->pluck('parameter')->filter()->unique();

    // $satuan = $data->pluck('satuan')->filter()->unique();

@endphp

<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;" width="100%">
        <thead>
            <tr>
                <th width="6%" rowspan="2" class="pd-5-solid-top-center">NO</th>
                <th width="30%" rowspan="2" colspan="2" class="pd-5-solid-top-center">
                    LOKASI / KETERANGAN SAMPEL</th>

                {{-- HASIL UJI: total kolom = jumlah parameter * (1 atau 2) --}}
                <th width="24%" colspan="{{ $parameters->count() }}" class="pd-5-solid-top-center">
                    HASIL UJI
                </th>

                {{-- BAKU MUTU: 1 kolom per parameter --}}
                <th width="24%" colspan="{{ $parameters->count() }}" class="pd-5-solid-top-center">
                    BAKU MUTU
                </th>
                <th width="16%" rowspan="2" class="pd-5-solid-top-center">
                    TANGGAL SAMPLING </th>
            </tr>
            <tr>
                {{-- HASIL UJI - PARAMETER --}}
                @foreach ($parameters as $param)
                    <th class="pd-5-solid-top-center" style="white-space: nowrap;">
                        @php
                            foreach ($detail as $row) {
                                if ($row['parameter'] === $param) {
                                    $akr = $row['akr'];
                                    $satuan = $row['satuan'];
                                    break;
                                }
                            }
                        @endphp
                        <sup>{{ $akr }}</sup>&nbsp;{{ $param }}
                        <br>
                        ({{ $satuan }})
                    </th>
                @endforeach

                {{-- BAKU MUTU - PARAMETER --}}
                @foreach ($parameters as $param)
                    @php
                        foreach ($detail as $row) {
                            if ($row['parameter'] === $param) {
                                $akr = $row['akr'];
                                $satuan = $row['satuan'];
                                break;
                            }
                        }
                    @endphp
                    <th class="pd-5-solid-top-center" style="white-space: nowrap;">
                        {{ $param }}
                        <br>
                        ({{ $satuan }})
                    </th>
                @endforeach
            </tr>

        </thead>

        <tbody>

            {{-- =======================
                     BODY MULTI SAMPEL (PIVOT)
                     1 baris = 1 no_sampel, param jadi kolom
                 ======================= --}}
            @php $rowNo = 0; @endphp
            @foreach ($groupedBySampel as $noSampel => $rows)
                @php
                    $rowNo++;
                    $rowClass = $rowNo == $groupedBySampel->count() ? 'solid' : 'dot';

                    $rows = collect($rows)->map(fn($r) => (array) $r);
                    $rowsByParam = $rows->keyBy('parameter');

                    $ref = $rows->first();
                    $noSampel = $ref['no_sampel'] ?? '';
                    $keterangan = $ref['keterangan'] ?? '';
                    $satuan = $ref['satuan'] ?? '-';
                    $methode = $ref['methode'] ?? '-';
                    $tanggal_sampling = $ref['tanggal_sampling'] ?? '-';
                @endphp

                <tr>
                    {{-- NO --}}
                    <td class="pd-5-{{ $rowClass }}-center">
                        {{ $rowNo }}
                    </td>

                    {{-- NO SAMPEL --}}
                    <td class="pd-3-{{ $rowClass }}" style="text-align: center; border-right: none;">
                        <sup style="font-size: 8px; margin-top: -10px;">{{ $noSampel }}</sup>
                    </td>
                    <td class="pd-3-{{ $rowClass }}" style="border-left: none; text-align: left;">
                        {{ $keterangan }}
                    </td>

                    {{-- HASIL UJI per parameter --}}
                    @foreach ($parameters as $param)
                        @php
                            $r = $rowsByParam->get($param, []);
                            $hasil = $r['hasil_uji'] ?? '-';
                            $terkoreksi = $r['terkoreksi'] ?? '-';
                        @endphp


                        <td class="pd-5-{{ $rowClass }}-center">
                            {!! $hasil !!}
                        </td>
                    @endforeach

                    {{-- BAKU MUTU per parameter --}}
                    @foreach ($parameters as $param)
                        @php
                            $r = $rowsByParam->get($param, []);
                            $baku = $r['baku_mutu'] ?? '-';
                        @endphp
                        <td class="pd-5-{{ $rowClass }}-center">
                            {{ $baku }}
                        </td>
                    @endforeach


                    <td class="pd-5-{{ $rowClass }}-center">
                        {{ \App\Helpers\Helper::tanggal_indonesia($tanggal_sampling) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
