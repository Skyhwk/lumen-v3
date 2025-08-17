<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            <tr>
                <th rowspan="2" width="25" class="pd-5-solid-top-center">NO</th>
                <th rowspan="2" width="250" class="pd-5-solid-top-center">PARAMETER</th>
                <th colspan="3" class="pd-5-solid-top-center">HASIL UJI</th>
                <th rowspan="2" width="75" class="pd-5-solid-top-center">BAKU MUTU</th>
                <th rowspan="2" class="pd-5-solid-top-center">SATUAN</th>
                <th rowspan="2" class="pd-5-solid-top-center">SPESIFIKASI METODE</th>
            </tr>
            <tr>
                <th class="pd-5-solid-top-center" width="75">C</th>
                <th class="pd-5-solid-top-center" width="75">C1</th>
                <th class="pd-5-solid-top-center" width="75">C2</th>
            </tr>
        </thead>
        <tbody>
            @php $total = count((array) $detail); @endphp
                @foreach ((array) $detail as $kk => $yy)
                    @continue(!$yy)

                    @php
                        $p = $kk + 1;
                        $rowClass = ($p == $total) ? 'solid' : 'dot';
                        $akr = !empty($yy['akr']) ? $yy['akr'] : '&nbsp;&nbsp;';
                    @endphp
                    <tr>
                        <td class="pd-5-{{ $rowClass }}-center">{{ $p }}</td>
                        <td class="pd-5-{{ $rowClass }}-left">{!! $akr !!}&nbsp;{{ htmlspecialchars($yy['parameter'] ?? '') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center">{{ htmlspecialchars($yy['C'] ?? '') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center">{{ htmlspecialchars($yy['C1'] ?? '') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center">{{ htmlspecialchars($yy['C2'] ?? '') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center">{{ htmlspecialchars($yy['baku_mutu'] ?? '') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center">{{ htmlspecialchars($yy['satuan'] ?? '') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center">{{ htmlspecialchars($yy['spesifikasi_metode'] ?? '') }}</td>
                    </tr>
                @endforeach
        </tbody>
    </table>
</div>



