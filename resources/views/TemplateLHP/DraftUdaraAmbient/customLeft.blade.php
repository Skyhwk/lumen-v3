@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="25" class="pd-5-solid-top-center" >NO</th>
                    <th width="200" class="pd-5-solid-top-center" >PARAMETER</th>
                    <th width="60" class="pd-5-solid-top-center" >DURASI</th>
                    <th width="60" class="pd-5-solid-top-center" >HASIL UJI</th>
                    <th width="50" class="pd-5-solid-top-center" >BAKU MUTU</th>
                    <th width="60" class="pd-5-solid-top-center" >SATUAN</th>
                    <th width="220" class="pd-5-solid-top-center" >SPESIFIKASI METODE</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totdat = count($custom);
                @endphp
                @foreach ($custom as $k => $v)
                    @php
                        $number = $k + 1;
                        $akr = !empty($v['akr']) ? $v['akr'] : '&nbsp;&nbsp;';
                        $satuan = (isset($v['satuan']) && $v['satuan'] != "null") ? $v['satuan'] : '';
                        $hasilUji = isset($v['hasil_uji']) ? str_replace('.', ',', $v['hasil_uji']) : '';
                        $attr = isset($v['attr']) ? $v['attr'] : '';
                        $methode = isset($v['methode']) ? $v['methode'] : '';
                        $durasi = isset($v['durasi']) ? $v['durasi'] : '';
                        $parameter = isset($v['parameter']) ? $v['parameter'] : '';
                        $bakuMutu = ($v['baku_mutu'] != "null") ? $v['baku_mutu'] : '';
                    @endphp
                    <tr>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $number }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{!! $akr !!}&nbsp;{{ $parameter }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $durasi }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $hasilUji }}&nbsp;{{ $attr }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $bakuMutu }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $satuan }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{{ $methode }}</td>
                    </tr>
                @endforeach

            </tbody>
        </table>
    </div>
@endif