@php
    $data = is_object($detail) && method_exists($detail, 'toArray') ? $detail->toArray() : (array) $detail;

    $data = collect($data)->map(fn($r) => (array) $r);

    $satuan = $data->pluck('satuan')->filter()->first();

@endphp

<div class="left">
    {{-- ================== DATA HASIL PENGUKURAN ================== --}}
    <div style="margin-bottom: 30px">
        <p style="text-align: left; margin: 0; font-family: Arial, Helvetica, sans-serif; font-size: 9px;"><strong>DATA
                HASIL PENGUKURAN</strong></p>
        <table
            style="border-collapse: collapse;  border: 1px solid black; font-family: Arial, Helvetica, sans-serif; font-size: 10px;"
            width="100%">
            <thead>
                <tr>
                    <th width="5%" class="pd-3-solid-top-center" style="white-space: nowrap;">NO</th>
                    <th width="40%" class="pd-3-solid-top-center" style="white-space: nowrap;">PARAMETER</th>
                    <th width="10%" class="pd-3-solid-top-center" style="white-space: nowrap;">HASIL UJI</th>
                    <th width="10%" class="pd-3-solid-top-center" style="white-space: nowrap;">NAB</th>
                    <th width="10%" class="pd-3-solid-top-center" style="white-space: nowrap;">SATUAN</th>
                    <th width="25%" class="pd-3-solid-top-center" style="white-space: nowrap;">SPESIFIKASI METODE
                    </th>
                </tr>
            </thead>
            <tbody>
                @php $totalRows = count($data); @endphp
                @foreach ($data as $kk => $yy)
                    @continue(!$yy)
                    @php
                        $p = $kk + 1;
                        $rowClass = $p == $totalRows ? 'dot' : 'solid';
                        $akr = !empty($yy['akr']) ? $yy['akr'] : '&nbsp;&nbsp;';
                    @endphp
                    <tr>
                        <td class="pd-3-{{ $rowClass }}-center" style="white-space: nowrap;">{{ $p }}
                        </td>
                        <td class="pd-3-{{ $rowClass }}-left" style="white-space: nowrap;">
                            {!! $akr !!}&nbsp;{{ htmlspecialchars($yy['parameter'] ?? '') }}
                        </td>
                        <td class="pd-3-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {!! $yy['hasil_uji'] ?? '-' !!}
                        </td>
                        <td class="pd-3-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ htmlspecialchars($yy['nab'] ?? '-') }}
                        </td>
                        <td class="pd-3-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ htmlspecialchars($yy['satuan'] ?? '-') }}
                        </td>
                        <td class="pd-3-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ htmlspecialchars($yy['methode'] ?? '-') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>


    </div>


    {{-- ================== INFORMASI LOKASI ================== --}}
    <div style="margin-bottom: 30px">
        <p style="text-align: left; margin: 0; font-family: Arial, Helvetica, sans-serif; font-size: 9px;">
            <strong>INFORMASI LOKASI(AREA) ATAU OBJEK SAMPLING</strong>
        </p>

        @php
            $informasiSampling = json_decode($header->informasi_sampling ?? '[]', true) ?? [];
            $listInformasiSampling = [];

            foreach ($informasiSampling as $i => $value) {
                if ($i === 0) {
                    $listInformasiSampling = $informasiSampling[0] ?? [];
                    break;
                }
            }
        @endphp

        <table
            style="border-collapse: collapse; border: 1px solid black; font-family: Arial, Helvetica, sans-serif; font-size: 10px; margin-top: 3px;"
            width="100%">
            <thead>
                <tr>
                    <th width="5%" class="pd-3-solid-top-center" style="white-space: nowrap;">NO</th>
                    <th width="40%" class="pd-3-solid-top-center" style="white-space: nowrap;">PARAMETER</th>
                    <th width="55%" class="pd-3-solid-top-center" style="white-space: nowrap;">DATA</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($listInformasiSampling as $i => $value)
                    @php
                        $p = $i + 1;
                        $rowClass = $p == count($listInformasiSampling) ? 'dot' : 'solid';
                    @endphp
                    <tr>
                        <td class="pd-3-{{ $rowClass }}-center" style="white-space: nowrap;">{{ $p }}
                        </td>
                        <td class="pd-3-{{ $rowClass }}-left" style="white-space: nowrap;">
                            {{ htmlspecialchars($value['parameter'] ?? '') }}
                        </td>
                        <td class="pd-3-{{ $rowClass }}-center" style="white-space: nowrap;">
                            {{ htmlspecialchars($value['data'] ?? '') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ================== HASIL OBSERVASI ================== --}}
    <div style="margin-bottom: 30px">
        <table
            style="border-collapse: collapse; border: 1px solid black;font-family: Arial, Helvetica, sans-serif; font-size: 10px; margin-top: 3px;"
            width="100%">
            <thead>
                <tr>
                    <th colspan="2" class="pd-3-solid-center" style="white-space: nowrap; text-align: left;">
                        HASIL OBSERVASI
                    </th>
                </tr>
            </thead>
            <tbody>
                @php
                    $observasiAll = json_decode($header->hasil_observasi ?? '[]', true) ?? [];
                    $listObservasi = [];
                    $totalObservasi = 0;
                    $extraRows = 3;

                    foreach ($observasiAll as $i => $value) {
                        if ($i === 0) {
                            $listObservasi = $observasiAll[0] ?? [];
                            $totalObservasi = count($listObservasi);
                            break;
                        }
                    }
                @endphp

                {{-- baris berisi data --}}
                @foreach ($listObservasi as $i => $satuHasil)
                    <tr>
                        <td width="5%" class="pd-3-dot-center">{{ $i + 1 }}</td>
                        <td width="95%" class="pd-3-dot-left">{{ $satuHasil }}</td>
                    </tr>
                @endforeach

                {{-- baris bayangan kosong --}}
                @for ($j = 1; $j <= $extraRows; $j++)
                    <tr>
                        <td width="5%" class="pd-3-dot-center">{{ $totalObservasi + $j }}</td>
                        <td width="95%" class="pd-3-dot-left">&nbsp;</td>
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>

    {{-- ================== KESIMPULAN ================== --}}
    <div style="margin-bottom: 30px">
        <table
            style="border-collapse: collapse;  border: 1px solid black; font-family: Arial, Helvetica, sans-serif; font-size: 10px; margin-top: 3px;"
            width="100%">
            <thead>
                <tr>
                    <th colspan="2" class="pd-3-solid-center" style="white-space: nowrap; text-align: left;">
                        KESIMPULAN
                    </th>
                </tr>
            </thead>
            <tbody>
                @php
                    $kesimpulanAll = json_decode($header->kesimpulan ?? '[]', true) ?? [];
                    $listKesimpulan = [];
                    $totalKesimpulan = 0;
                    $extraRowsKesimpulan = 3;

                    foreach ($kesimpulanAll as $i => $value) {
                        if ($i === 0) {
                            $listKesimpulan = $kesimpulanAll[0] ?? [];
                            $totalKesimpulan = count($listKesimpulan);
                            break;
                        }
                    }
                @endphp

                {{-- baris berisi data --}}
                @foreach ($listKesimpulan as $i => $satuHasil)
                    <tr>
                        <td width="5%" class="pd-3-dot-center">{{ $i + 1 }}</td>
                        <td width="95%" class="pd-3-dot-left">{{ $satuHasil }}</td>
                    </tr>
                @endforeach

                {{-- baris bayangan kosong --}}
                @for ($j = 1; $j <= $extraRowsKesimpulan; $j++)
                    <tr>
                        <td width="5%" class="pd-3-dot-center">{{ $totalKesimpulan + $j }}</td>
                        <td width="95%" class="pd-3-dot-left">&nbsp;</td>
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>

</div>
