@if (!empty($custom))
    @php 
        $totdat = count($custom); 
        $isManyNoSampel = $header->is_many_sampel == 1 ? true : false;
    @endphp
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                @if ($isManyNoSampel)
                <tr>
                    <th width="25" rowspan="2" class="pd-5-solid-top-center" >NO</th>
                    <th width="310" rowspan="2" class="pd-5-solid-top-center" >NO SAMPEL</th>
                    <th width="170" class="pd-5-solid-top-center" >HASIL UJI</th>
                    <th width="160" class="pd-5-solid-top-center" >BAKU MUTU</th>
                </tr>
                <tr>
                    <th width="110" colspan="2" class="pd-5-solid-top-center">{{$custom[0]['satuan']}}</th>
                </tr>
                @else
                <tr>
                    <th width="25" class="pd-5-solid-top-center" >NO</th>
                    <th width="200" class="pd-5-solid-top-center" >PARAMETER</th>
                    <th width="60" class="pd-5-solid-top-center" >HASIL UJI</th>
                    <th width="50" class="pd-5-solid-top-center" >NILAI PERSYARATAN</th>
                    <th width="50" class="pd-5-solid-top-center" >JENIS PERSYARATAN</th>
                    <th width="60" class="pd-5-solid-top-center" >SATUAN</th>
                    <th width="220" class="pd-5-solid-top-center" >SPESIFIKASI METODE</th>
                </tr>
                @endif
                
            </thead>
            <tbody>
                @if ($isManyNoSampel)
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
                            $baku_mutu = isset($v['baku_mutu']) ? $v['baku_mutu'] : '';
                            $nama_header = isset($v['nama_header']) ? $v['nama_header'] : '';
                        @endphp
                        <tr>
                            <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $number }}</td>
                            <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">
                                {!! $akr !!}&nbsp;
                                {!! $isManyNoSampel 
                                    ? '<sup>'.$v['no_sampel'].'</sup> '.$v['deskripsi_titik'] 
                                    : $v['parameter'] 
                                !!}
                            </td>
                            <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $hasilUji }}&nbsp;{{ $attr }}</td>
                            <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $baku_mutu }}</td>
                        </tr>
                    @endforeach
                @else
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
                            $baku_mutu = isset($v['baku_mutu']) ? $v['baku_mutu'] : '';
                            $nama_header = isset($v['nama_header']) ? $v['nama_header'] : '';
                        @endphp
                        <tr>
                            <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $number }}</td>
                            <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">
                                {!! $akr !!}&nbsp;
                                {!! $isManyNoSampel 
                                    ? '<sup>'.$v['no_sampel'].'</sup> '.$v['deskripsi_titik'] 
                                    : $v['parameter'] 
                                !!}
                            </td>
                            <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $hasilUji }}&nbsp;{{ $attr }}</td>
                            <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $baku_mutu }}</td>
                            <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $nama_header }}</td>
                            <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $satuan }}</td>
                            <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{{ $methode }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
@endif