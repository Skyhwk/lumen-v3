@php
    $data = is_object($detail) && method_exists($detail, 'toArray')
        ? $detail->toArray()
        : (array) $detail;

    $data = collect($data)->map(fn ($r) => (array) $r);

    // group per no_sampel
    $groupedBySampel = $data->groupBy('no_sampel');

    $hasTerkoreksi = $data->contains(function ($item) {
        return isset($item['terkoreksi']) && !empty($item['terkoreksi']) && $item['terkoreksi'] !== '-';
    });

    $totalSampel = $groupedBySampel->count();
    $isSingleSampel = $totalSampel === 1;

    // untuk mode multi: parameter jadi TH
    $parameters = $data->pluck('parameter')->filter()->unique()->values();
@endphp

<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            @if ($isSingleSampel)
                {{-- =======================
                     HEADER SINGLE SAMPEL
                 ======================= --}}
                <tr>
                    <th width="25" rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center" style="white-space: nowrap;">NO</th>
                    <th width="250" rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center" style="white-space: nowrap;">PARAMETER</th>

                    @if ($hasTerkoreksi)
                        <th colspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">HASIL UJI</th>
                    @else
                        <th class="pd-5-solid-top-center" style="white-space: nowrap;">HASIL UJI</th>
                    @endif

                    <th width="75" rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center" style="white-space: nowrap;">BAKU MUTU</th>
                    <th rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center" style="white-space: nowrap;">SATUAN</th>
                    <th rowspan="{{ $hasTerkoreksi ? 2 : 1 }}" class="pd-5-solid-top-center" style="white-space: nowrap;">METODE</th>
                </tr>
                @if ($hasTerkoreksi)
                    <tr>
                        <th class="pd-5-solid-top-center" style="white-space: nowrap;">TERUKUR</th>
                        <th class="pd-5-solid-top-center" style="white-space: nowrap;">TERKOREKSI</th>
                    </tr>
                @endif
            @else
                {{-- =======================
                     HEADER MULTI SAMPEL (PIVOT)
                     Param jadi TH di bawah HASIL UJI & BAKU MUTU
                 ======================= --}}
                <tr>
                    <th width="25" rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">NO</th>
                    <th width="120" rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">NO SAMPEL</th>

                    {{-- HASIL UJI: total kolom = jumlah parameter * (1 atau 2) --}}
                    <th colspan="{{ $parameters->count() * ($hasTerkoreksi ? 2 : 1) }}" class="pd-5-solid-top-center" style="white-space: nowrap;">
                        HASIL UJI
                    </th>

                    {{-- BAKU MUTU: 1 kolom per parameter --}}
                    <th colspan="{{ $parameters->count() }}" class="pd-5-solid-top-center" style="white-space: nowrap;">
                        BAKU MUTU
                    </th>

                    <th rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">SATUAN</th>
                    <th rowspan="2" class="pd-5-solid-top-center" style="white-space: nowrap;">METODE</th>
                </tr>
                <tr>
                    {{-- HASIL UJI - PARAMETER --}}
                    @foreach ($parameters as $param)
                        @if ($hasTerkoreksi)
                            <th class="pd-5-solid-top-center" style="white-space: nowrap;">
                                {{ $param }}<br><small>TERUKUR</small>
                            </th>
                            <th class="pd-5-solid-top-center" style="white-space: nowrap;">
                                {{ $param }}<br><small>TERKOREKSI</small>
                            </th>
                        @else
                            <th class="pd-5-solid-top-center" style="white-space: nowrap;">
                                {{ $param }}
                            </th>
                        @endif
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
                        $rowClass = ($p == $totalRows) ? 'solid' : 'dot';
                        $akr = !empty($yy['akr']) ? $yy['akr'] : '&nbsp;&nbsp;';
                    @endphp
                    <tr>
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{{ $p }}</td>
                        <td class="pd-5-{{ $rowClass }}-left" style="white-space: nowrap;">
                            <sup>{!! $akr !!}</sup>&nbsp;{{ htmlspecialchars($yy['parameter'] ?? '') }}
                        </td>
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{!! $yy['hasil_uji'] ?? '-' !!}</td>

                        @if ($hasTerkoreksi)
                            <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{!! $yy['terkoreksi'] ?? '-' !!}</td>
                        @endif

                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{{ htmlspecialchars($yy['baku_mutu'] ?? '-') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{{ htmlspecialchars($yy['satuan'] ?? '-') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">{{ htmlspecialchars($yy['methode'] ?? '-') }}</td>
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
                        $rowClass = ($rowNo == $groupedBySampel->count()) ? 'solid' : 'dot';

                        $rows = collect($rows)->map(fn ($r) => (array) $r);
                        $rowsByParam = $rows->keyBy('parameter');

                        $ref = $rows->first();
                        $satuan = $ref['satuan'] ?? '-';
                        $methode = $ref['methode'] ?? '-';
                    @endphp

                    <tr>
                        {{-- NO --}}
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ $rowNo }}
                        </td>

                        {{-- NO SAMPEL --}}
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ htmlspecialchars($noSampel ?? '') }}
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

                        {{-- SATUAN & METODE (per no_sampel) --}}
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ htmlspecialchars($satuan) }}
                        </td>
                        <td class="pd-5-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ htmlspecialchars($methode) }}
                        </td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>
