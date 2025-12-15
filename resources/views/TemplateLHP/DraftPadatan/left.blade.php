@php
    $totData = $header->header_table ? count(json_decode($header->header_table)) : 0;
    $colc = '';
    $rowc = 1;

    if ($totData > 1) {
        $colc = 'colspan="' . $totData . '"';
        $rowc = 2;
    }
@endphp

@if (!empty($header))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="25" class="pd-5-solid-top-center" >NO</th>
                    <th width="200" class="pd-5-solid-top-center" >PARAMETER</th>
                    <th width="60" class="pd-5-solid-top-center" >HASIL UJI</th>
                    <th width="85" class="pd-5-solid-top-center" >{{ json_decode($header->header_table)[0] }}</th>
                    <th width="50" class="pd-5-solid-top-center" >SATUAN</th>
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
                    @endphp
                    <tr>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $i }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{!! $akr !!}&nbsp;{{ $v['parameter'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['hasil_uji'] }}&nbsp;{{ $v['attr'] }}</td>
                        @foreach (json_decode($v['baku_mutu'] ?? '[]') as $vv)
                            <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $vv }}</td>
                        @endforeach
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $satuan }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{{ $v['methode'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif