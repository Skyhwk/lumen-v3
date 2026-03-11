@php
    $data = is_object($custom) && method_exists($custom, 'toArray') ? $custom->toArray() : (array) $custom;

    $data = collect($data)->map(fn($r) => (array) $r);

    // group per no_sampel
    $groupedBySampel = $data->groupBy('no_sampel');

    $hasTerkoreksi = $data->contains(function ($item) {
        return isset($item['terkoreksi']) && !empty($item['terkoreksi']) && $item['terkoreksi'] !== '-';
    });

    $totalSampel = $groupedBySampel->count(); // jumlah no_sampel unik
    $parameters = $data->pluck('parameter')->filter()->unique(); // parameter unik
    $totalParam = $parameters->count();

    // KONDISI:
    $isSingleSampel = $totalSampel === 1;
    $isMultiSampelOneParam = $totalSampel > 1 && $totalParam === 1;
    $isMultiSampelMultiParam = $totalSampel > 1 && $totalParam > 1;

    $satuan = $data->pluck('satuan')->filter()->first();

@endphp

<div class="left" style="page-break-before: always;">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            @if ($isSingleSampel)
                {{-- =======================
                     HEADER SINGLE SAMPEL
                 ======================= --}}
                <tr>
                    <th width="25" rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center"
                        style="white-space: nowrap;">NO</th>
                    <th width="240" rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center"
                        style="white-space: nowrap;">PARAMETER</th>

                    @if ($hasTerkoreksi)
                        <th colspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">HASIL UJI</th>
                    @else
                        <th width="80" class="pd-5-solid-top-center" style="white-space: nowrap;">HASIL UJI</th>
                    @endif

                    <th width="80" rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center"
                        style="white-space: nowrap;">BAKU MUTU</th>
                    <th width="80" rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center"
                        style="white-space: nowrap;">SATUAN</th>
                    <th width="80" rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center"
                        style="white-space: nowrap;">SPESIFIKASI METHOD</th>
                </tr>
                @if ($hasTerkoreksi)
                    <tr>
                        <th class="pd-5-solid-top-center" style="white-space: nowrap;">TERUKUR</th>
                        <th class="pd-5-solid-top-center" style="white-space: nowrap;">TERKOREKSI</th>
                    </tr>
                @endif
            @elseif ($isMultiSampelOneParam)
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
                    <th width="160" colspan="{{ $parameters->count() }}" class="pd-5-solid-top-center" style="white-space: nowrap;">
                        HASIL UJI
                    </th>

                    {{-- BAKU MUTU: 1 kolom per parameter --}}
                    <th width="160" colspan="{{ $parameters->count() }}" class="pd-5-solid-top-center" style="white-space: nowrap;">
                        BAKU MUTU
                    </th>
                    <th  width="160" rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">
                        TANGGAL SAMPLING </th>
                </tr>
                <tr>
                    {{-- HASIL UJI - PARAMETER --}}
                    @foreach ($parameters as $param)
                        <th class="pd-5-solid-top-center" style="white-space: nowrap;">
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

                    {{-- BAKU MUTU - PARAMETER --}}
                    @foreach ($parameters as $param)
                        <th class="pd-5-solid-top-center" style="white-space: nowrap;">
                            {{ $param }}
                        </th>
                    @endforeach
                </tr>
            @endif
        </thead>

        <tbody>
            @if ($isSingleSampel)
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

                        @if ($hasTerkoreksi)
                            <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                                {!! $yy['terkoreksi'] ?? '-' !!}</td>
                        @endif

                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ htmlspecialchars($yy['baku_mutu'] ?? '-') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ htmlspecialchars($yy['satuan'] ?? '-') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ htmlspecialchars($yy['methode'] ?? '-') }}</td>
                    </tr>
                @endforeach
            @elseif ($isMultiSampelOneParam)
                {{-- =======================
             BODY MULTI SAMPEL, 1 PARAMETER
             1 baris = 1 keterangan, superscript = no_sampel
         ======================= --}}
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
