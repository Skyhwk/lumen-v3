@php
    use App\Models\TabelRegulasi;
    $data = is_object($custom) && method_exists($custom, 'toArray') ? $custom->toArray() : (array) $custom;

    $data = collect($data)->map(fn($r) => (array) $r);

    $groupedBySampel = $data->groupBy('no_sampel');

    $hasTerkoreksi = $data->contains(function ($item) {
        return isset($item['terkoreksi']) && !empty($item['terkoreksi']) && $item['terkoreksi'] !== '-';
    });

    $totalSampel = $groupedBySampel->count(); // jumlah no_sampel unik
    $parameters = $data->pluck('parameter')->filter()->unique(); // parameter unik

    $satuan = $data->pluck('satuan')->filter()->first();

@endphp

<div class="left" style="page-break-before: always;">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            <tr>
                <!-- NO: 2 baris -->
                <th class="pd-5-solid-top-center" rowspan="2">
                    NO
                </th>

                <!-- LOKASI / KETERANGAN SAMPEL: 2 baris -->
                <th width="240" class="pd-5-solid-top-center" rowspan="2">
                    LOKASI / KETERANGAN SAMPEL
                </th>

                <!-- HASIL UJI: baris 1 -->
                <th class="pd-5-solid-top-center">
                    Suhu
                </th>

                <!-- BAKU MUTU***: baris 1 -->
                <th class="pd-5-solid-top-center">
                    Kelembapan
                </th>

                @foreach ($parameters as $param)
                    <th class="pd-5-solid-top-center">
                        @php
                            foreach ($custom as $row) {
                                if ($row['parameter'] === $param) {
                                    $akr = $row['akr'];
                                    break;
                                }
                            }
                        @endphp
                        <sup>{{ $akr }}</sup>&nbsp;{{ $param }}
                    </th>
                @endforeach
                <th width="160" rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">
                    TANGGAL SAMPLING </th>
            </tr>

            <tr>
                <th class="pd-5-solid-top-center">(°C)</th>
                <th class="pd-5-solid-top-center">(%)</th>
                <th class="pd-5-solid-top-center">{{ $satuan }}
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
                    $suhu = $ref['suhu'] ?? '';
                    $kelembapan = $ref['kelembapan'] ?? '';
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
                        {!! $suhu !!}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                        {!! $kelembapan !!}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                        {!! $hasilUji !!}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                        {{ \App\Helpers\Helper::tanggal_indonesia($tanggal_sampling) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
