@php
    $totData = $header->header_table ? count(json_decode($header->header_table)) : 0; // pastiin pake $header, bukan $data, sesuai konteks lo
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
                    <th width="25" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">NO</th>
                    <th width="200" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">PARAMETER</th>
                    <th width="60" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">HASIL UJI</th>
                    @if ($totData == 1)
                        <th width="85" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">BAKU MUTU**</th>
                    @else
                        <th {{ $colc }} class="pd-5-solid-top-center" rowspan="{{ $rowc }}">BAKU MUTU**</th>
                    @endif
                    <th width="50" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">SATUAN</th>
                    <th width="220" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">SPESIFIKASI METODE</th>
                </tr>
                @if ($totData > 1)
                    <tr>
                        @foreach (json_decode($header->header_table) as $val)
                            <th class="pd-5-solid-top-center" width="50">{{ $val }}</th>
                        @endforeach
                    </tr>
                @endif
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
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ str_replace('.', ',', $v['hasil_uji']) }}&nbsp;{{ $v['attr'] }}</td>
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

{{-- dan bagian data_custom juga diubah mirip ini, dengan asumsi $data_custom dari $header->data_custom --}}

<!-- @if (!empty($custom))
    @foreach ($custom as $key => $value)
        <div class="left">
            <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                <thead>
                    <tr>
                        <th width="25" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">NO</th>
                        <th width="200" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">PARAMETER</th>
                        <th width="60" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">HASIL UJI</th>
                        @if ($totData == 1)
                            <th width="85" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">BAKU MUTU**</th>
                        @else
                            <th {{ $colc }} class="pd-5-solid-top-center">BAKU MUTU**</th>
                        @endif
                        <th width="50" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">SATUAN</th>
                        <th width="220" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">SPESIFIKASI METODE</th>
                    </tr>
                    @if ($totData > 1)
                        <tr>
                            @foreach (json_decode($header->header_table) as $val)
                                <th class="pd-5-solid-top-center" width="50">{{ $val }}</th>
                            @endforeach
                        </tr>
                    @endif
                </thead>
                <tbody>
                    @php $totdat = count($value); @endphp
                    @foreach ($value as $k => $v)
                        @php
                            $i = $k + 1;
                            $akr = !empty($v['akr']) ? $v['akr'] : '&nbsp;&nbsp;';
                            $satuan = ($v['satuan'] != "null") ? $v['satuan'] : '';
                        @endphp
                        <tr>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $i }}</td>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{!! $akr !!}&nbsp;{{ $v['parameter'] }}</td>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ str_replace('.', ',', $v['hasil_uji']) }}&nbsp;{{ $v['attr'] }}</td>
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
    @endforeach
@endif -->

