<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; width: 100%;">
        <thead>
            <tr>
                <th width="6%" class="custom">NO</th>
                <th width="42%" class="custom" colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                <th width="14%" class="custom">PARAMETER</th>
                <th width="18%" class="custom">HASIL UJI</th>
                <th width="20%" class="custom">NILAI RUJUKAN</th>
            </tr>
        </thead>

        <tbody>
            @php
                $grouped = collect($detail)->groupBy('nomor_sampel');
                $rowNumber = 1;
                $totalGroup = $grouped->count();
                $groupIndex = 1;
            @endphp

            @foreach ($grouped as $nomorSampel => $items)
                @php
                    $rowspan = count($items);
                @endphp

                @foreach ($items as $index => $yy)
                    @php
                        $isLastGroup = $groupIndex == $totalGroup;
                        $classCenter = $isLastGroup ? 'pd-5-solid-center' : 'pd-5-dot-center';
                        $classText = $isLastGroup ? 'pd-3-solid' : 'pd-3-dot';
                    @endphp

                    <tr>

                        {{-- NO --}}
                        @if ($index == 0)
                            <td rowspan="{{ $rowspan }}" class="{{ $classCenter }}">
                                {{ $rowNumber }}
                            </td>

                            {{-- NOMOR SAMPEL --}}
                            <td rowspan="{{ $rowspan }}"
                                class="{{ $classText }}"
                                width="10%"
                                style="text-align: right; border-right: none;">
                                <sup style="font-size: 5px; margin-top: -10px;">
                                    {{ $yy['nomor_sampel'] }}
                                </sup>
                            </td>

                            {{-- LOKASI --}}
                            <td rowspan="{{ $rowspan }}"
                                class="{{ $classText }}"
                                width="32%"
                                style="border-left: none; text-align: left;">
                                {{ $yy['lokasi_pengambilan_sampel'] }}
                            </td>
                        @endif

                        {{-- PARAMETER --}}
                        <td class="{{ $classCenter }}">
                            {{ $yy['parameter'] }}
                        </td>

                        {{-- HASIL UJI --}}
                        <td class="{{ $classCenter }}">
                            {{ $yy['hasil_uji'] }}
                        </td>

                        {{-- NILAI RUJUKAN --}}
                        <td class="{{ $classCenter }}">
                            -
                        </td>

                    </tr>
                @endforeach

                @php
                    $rowNumber++;
                    $groupIndex++;
                @endphp
            @endforeach
        </tbody>
    </table>
</div>