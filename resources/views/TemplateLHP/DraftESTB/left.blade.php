<div class="left">

    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            <tr>
                <th width="25" class="pd-5-solid-top-center " style="white-space: nowrap;">NO</th>
                <th width="250" class="pd-5-solid-top-center" style="white-space: nowrap;">PARAMETER</th>
                <th class="pd-5-solid-top-center" style="white-space: nowrap;">HASIL UJI</th>
                <th class="pd-5-solid-top-center" style="white-space: nowrap;">TERKOREKSI</th>
                <th width="75" class="pd-5-solid-top-center" style="white-space: nowrap;">BAKU MUTU</th>
                <th class="pd-5-solid-top-center" style="white-space: nowrap;">SATUAN</th>
                <th class="pd-5-solid-top-center" style="white-space: nowrap;">SPESIFIKASI METODE</th>
            </tr>
        </thead>
        <tbody>
            @php $total = count((array) $detail); @endphp
                @foreach ($detail->toArray() as $kk => $yy)
                    @continue(!$yy)

                    @php
                        $p = $kk + 1;
                        $rowClass = ($p == $total) ? 'solid' : 'dot';
                        $akr = !empty($yy['akr']) ? $yy['akr'] : '&nbsp;&nbsp;';
                    @endphp
                    <tr>
                        <td class="pd-5-{{ $rowClass }}-center">{{ $p }}</td>
                        <td class="pd-5-{{ $rowClass }}-left"><sup>{!! $akr !!}</sup>&nbsp;{{ htmlspecialchars($yy['parameter'] ?? '') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center">{!! $yy['C'] ?? '' !!}</td>
                        <td class="pd-5-{{ $rowClass }}-center">{!! $yy['terkoreksi'] ?? '' !!}</td>
                        <td class="pd-5-{{ $rowClass }}-center">{{ htmlspecialchars($yy['baku_mutu'] ?? '') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center">{{ htmlspecialchars($yy['satuan'] ?? '') }}</td>
                        <td class="pd-5-{{ $rowClass }}-center">{{ htmlspecialchars($yy['spesifikasi_metode'] ?? '') }}</td>
                    </tr>
                @endforeach
        </tbody>
    </table>
</div>



