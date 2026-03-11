@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="25" class="pd-5-solid-top-center" >NO</th>
                    <th width="200" class="pd-5-solid-top-center" >PARAMETER</th>
                    <th width="60" class="pd-5-solid-top-center" >HASIL UJI</th>
                    <th width="85" class="pd-5-solid-top-center" >
                        @php
                            $headerTable = json_decode($header->header_table ?? '[]', true);
                        @endphp
                        {{ isset($headerTable[$page]) ? $headerTable[$page] : '' }}
                    </th>
                    <th width="50" class="pd-5-solid-top-center" >SATUAN</th>
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
                        $hasilUji = isset($v['hasil_uji']) ? $v['hasil_uji'] : '';
                        $attr = isset($v['attr']) ? $v['attr'] : '';
                        $bakuMutu = isset($v['baku_mutu']) ? json_decode($v['baku_mutu'], true) : [];
                        $methode = isset($v['methode']) ? $v['methode'] : '';
                        $parameter = isset($v['parameter']) ? $v['parameter'] : '';
                    @endphp
                    <tr>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $number }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{!! $akr !!}&nbsp;{{ $parameter }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $hasilUji }}&nbsp;{{ $attr }}</td>
                        @if(is_array($bakuMutu))
                            @foreach ($bakuMutu as $key => $vv)
                                @if ($key !== 0 && ($vv === null || $vv === ''))
                                    @continue
                                @endif
                                <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $vv }}</td>
                            @endforeach
                        @endif
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $satuan }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{{ $methode }}</td>
                    </tr>
                @endforeach

            </tbody>
        </table>
    </div>
@endif