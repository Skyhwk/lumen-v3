
@if (!empty($header))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="25" class="pd-5-solid-top-center" >NO</th>
                    <th width="200" class="pd-5-solid-top-center" >PARAMETER</th>
                    <th width="60" class="pd-5-solid-top-center" >HASIL UJI</th>
                    <th width="50" class="pd-5-solid-top-center" >NAB</th>
                    <th width="50" class="pd-5-solid-top-center" >PSD/KTD</th>
                    <th width="60" class="pd-5-solid-top-center" >SATUAN</th>
                    <th width="220" class="pd-5-solid-top-center" >SPESIFIKASI METODE</th>
                </tr>
            </thead>
            <tbody>
                @php $totdat = count($detail); @endphp
                @foreach ($detail as $k => $v)
                    @php
                        $i = $k + 1;
                        $akr = !empty($v['akr']) ? $v['akr'] : '&nbsp;&nbsp;';
                        $satuan = ($v['satuan'] != "null") ? $v['satuan'] : '';
                        $nab = '-';
                        $psd_ktd = '-';
                        if ($v['nama_header'] && $v['nama_header'] == 'NAB' && $v['baku_mutu']) {
                            $nab = $v['baku_mutu'];
                        }
                        if ($v['nama_header'] && $v['nama_header'] == 'PSD/KTD' && $v['baku_mutu']) {
                            $psd_ktd = $v['baku_mutu'];
                        }
                    @endphp
                    <tr>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $i }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}"><sup>{!! $akr !!}</sup>&nbsp;{{ $v['parameter'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ str_replace('.', ',', $v['hasil_uji']) }}&nbsp;{{ $v['attr'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $nab }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $psd_ktd }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $satuan }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{{ $v['methode'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

