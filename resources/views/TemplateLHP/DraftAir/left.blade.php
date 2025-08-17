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

<!-- <pagebreak /> -->
{{-- Bagian custom dengan AddPage --}}

<!-- @if (!empty($custom))
    
    @foreach ($custom as $key => $value)
        {{-- Jika bukan item custom pertama, tambahkan pagebreak --}}
        @if (!$loop->first)
            <pagebreak />
        @endif

        <div class="left" style="page-break-before: always;">
            <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                <thead>
                    <tr>
                        <th width="25" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">NO</th>
                        <th width="200" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">PARAMETER</th>
                        <th width="60" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">HASIL UJI</th>
                        <th width="85" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">{{ json_decode($header->header_table)[$key] }}</th>
                        <th width="50" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">SATUAN</th>
                        <th width="220" class="pd-5-solid-top-center" rowspan="{{ $rowc }}">SPESIFIKASI METODE</th>
                    </tr>
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
                            @foreach (json_decode($v['baku_mutu'] ?? '[]') as $key => $vv)
                                @if ($key !== 0 && ($vv === null || $vv === ''))
                                    @continue
                                @endif
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


