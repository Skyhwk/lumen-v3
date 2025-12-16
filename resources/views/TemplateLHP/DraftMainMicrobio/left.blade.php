@php
    use App\Models\TabelRegulasi;
    $data = is_object($detail) && method_exists($detail, 'toArray') ? $detail->toArray() : (array) $detail;

    $data = collect($data)->map(fn($r) => (array) $r);

    $groupedBySampel = $data->groupBy('no_sampel');

    $hasTerkoreksi = $data->contains(function ($item) {
        return isset($item['terkoreksi']) && !empty($item['terkoreksi']) && $item['terkoreksi'] !== '-';
    });

    $totalSampel = $groupedBySampel->count(); // jumlah no_sampel unik
    $parameters = $data->pluck('parameter')->filter()->unique(); // parameter unik
    $totalParam = $parameters->count();

    // KONDISI:
    $isMultipleParameter = $totalParam > 1;
    $id_reg = [];
    if(!$isMultipleParameter){
        foreach (json_decode($header->regulasi, true) as $reg) {
            $id_reg[] = explode('-', $reg)[0];
        }
        $isTable = TabelRegulasi::whereJsonContains('id_regulasi', $id_reg)
            ->where('is_active', 1)->get();

        $isUsingTable = !$isTable->isEmpty();
        $isNotUsingTable = !$isUsingTable;
    }
    $isMultiSampelOneParam = $totalSampel > 1 && $totalParam === 1;
    $isMultiSampelMultiParam = $totalSampel > 1 && $totalParam > 1;
    $satuan = $data->pluck('satuan')->filter()->first();

@endphp

<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            @if ($isMultipleParameter)
                {{-- =======================
                     HEADER SINGLE SAMPEL
                 ======================= --}}

                <tr>
                    <th width="25" rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">NO</th>
                    <th width="240" rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">
                        LOKASI / KETERANGAN SAMPEL</th>

                    {{-- HASIL UJI: total kolom = jumlah parameter * (1 atau 2) --}}
                    <th width="160" colspan="{{ $parameters->count() }}" class="pd-5-solid-top-center"
                        style="white-space: nowrap;">
                        HASIL UJI
                    </th>

                    {{-- BAKU MUTU: 1 kolom per parameter --}}
                    <th width="160" colspan="{{ $parameters->count() }}" class="pd-5-solid-top-center"
                        style="white-space: nowrap;">
                        BAKU MUTU
                    </th>
                    <th width="160" rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">
                        TANGGAL SAMPLING </th>
                </tr>
                <tr>
                    {{-- HASIL UJI - PARAMETER --}}
                    @foreach ($parameters as $param)
                        <th class="pd-5-solid-top-center">
                            @php
                                foreach ($detail as $row) {
                                    if ($row['parameter'] === $param) {
                                        $akr = $row['akr'];
                                        $onean = $row['satuan'];
                                        break;
                                    }
                                }
                            @endphp
                            <sup>{{ $akr }}</sup>&nbsp;{{ $param }} ({{ $onean }})
                        </th>
                    @endforeach

                    {{-- BAKU MUTU - PARAMETER --}}
                    @foreach ($parameters as $param)
                        <th class="pd-5-solid-top-center">
                            @php
                                foreach ($detail as $row) {
                                    if ($row['parameter'] === $param) {
                                        $akr = $row['akr'];
                                        $onean = $row['satuan'];
                                        break;
                                    }
                                }
                            @endphp
                            {{ $param }} ({{ $onean }})
                        </th>
                    @endforeach
                </tr>
            @elseif ($isUsingTable)
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
                                foreach ($detail as $row) {
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
            @else
                {{-- =======================
                     HEADER MULTI SAMPEL (PIVOT)
                     Param jadi TH di bawah HASIL UJI & BAKU MUTU
                 ======================= --}}
                <tr>
                    <th width="25" rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">NO</th>
                    <th width="240" rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">
                        LOKASI / KETERANGAN SAMPEL</th>

                    {{-- HASIL UJI: total kolom = jumlah parameter * (1 atau 2) --}}
                    <th width="160" class="pd-5-solid-top-center"
                        style="white-space: nowrap;">
                        HASIL UJI
                    </th>

                    {{-- BAKU MUTU: 1 kolom per parameter --}}
                    <th width="160" class="pd-5-solid-top-center"
                        style="white-space: nowrap;">
                        BAKU MUTU
                    </th>
                    <th width="160" rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">
                        TANGGAL SAMPLING </th>
                </tr>
                
                <tr>
                    <th colspan="2" class="pd-5-solid-top-center">Satuan = {{ $satuan }}</th>
                </tr>
            @endif
        </thead>

        <tbody>
            @if ($isMultipleParameter)
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
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ $rowNo }}
                        </td>

                        {{-- NO SAMPEL --}}
                        <td class="pd-5-{{ $rowClass }}-left" style="white-space: nowrap;">
                            <sup>{{ htmlspecialchars($noSampel) }}</sup>&nbsp;{{ htmlspecialchars($keterangan) }}
                        </td>

                        {{-- HASIL UJI per parameter --}}
                        @foreach ($parameters as $param)
                            @php
                                $r = $rowsByParam->get($param, []);
                                $hasil = $r['hasil_uji'] ?? '-';
                                $terkoreksi = $r['terkoreksi'] ?? '-';
                            @endphp

                            @if ($hasTerkoreksi)
                                <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                                    {!! $hasil !!}
                                </td>
                                <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                                    {!! $terkoreksi !!}
                                </td>
                            @else
                                <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                                    {!! $hasil !!}
                                </td>
                            @endif
                        @endforeach

                        {{-- BAKU MUTU per parameter --}}
                        @foreach ($parameters as $param)
                            @php
                                $r = $rowsByParam->get($param, []);
                                $baku = $r['baku_mutu'] ?? '-';
                            @endphp
                            <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                                {{ htmlspecialchars($baku) }}
                            </td>
                        @endforeach
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ \App\Helpers\Helper::tanggal_indonesia($tanggal_sampling) }}
                        </td>
                    </tr>
                @endforeach
            @elseif ($isUsingTable)
                @php
                    $rowNo = 0;
                    // group by keterangan saja (kalau 1 sampel bisa punya beberapa baris dengan keterangan sama, ini jaga2)
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
            @else
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
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ $rowNo }}
                        </td>

                        {{-- NO SAMPEL --}}
                        <td class="pd-5-{{ $rowClass }}-left" style="white-space: nowrap;">
                            <sup>{{ htmlspecialchars($noSampel) }}</sup>&nbsp;{{ htmlspecialchars($keterangan) }}
                        </td>

                        {{-- HASIL UJI per parameter --}}
                        @foreach ($parameters as $param)
                            @php
                                $r = $rowsByParam->get($param, []);
                                $hasil = $r['hasil_uji'] ?? '-';
                                $terkoreksi = $r['terkoreksi'] ?? '-';
                            @endphp

                            @if ($hasTerkoreksi)
                                <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                                    {!! $hasil !!}
                                </td>
                                <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                                    {!! $terkoreksi !!}
                                </td>
                            @else
                                <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                                    {!! $hasil !!}
                                </td>
                            @endif
                        @endforeach

                        {{-- BAKU MUTU per parameter --}}
                        @foreach ($parameters as $param)
                            @php
                                $r = $rowsByParam->get($param, []);
                                $baku = $r['baku_mutu'] ?? '-';
                            @endphp
                            <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                                {{ htmlspecialchars($baku) }}
                            </td>
                        @endforeach


                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ \App\Helpers\Helper::tanggal_indonesia($tanggal_sampling) }}
                        </td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>
