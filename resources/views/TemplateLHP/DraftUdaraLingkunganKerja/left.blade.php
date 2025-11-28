
@if (!empty($header))
    @php 
        $totdat = count($detail); 
        $isManyNoSampel = $header->is_many_sampel == 1 ? true : false;
    @endphp
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                @if ($isManyNoSampel)
                <tr>
                    <th width="25" rowspan="2" class="pd-5-solid-top-center" >NO</th>
                    <th width="310" rowspan="2" class="pd-5-solid-top-center" >NO SAMPEL</th>
                    <th width="170" class="pd-5-solid-top-center" >HASIL UJI</th>
                    <th width="160" class="pd-5-solid-top-center" >BAKU MUTU</th>
                    <th width="110" rowspan="2" class="pd-5-solid-top-center">TANGGAL SAMPLING</th>
                </tr>
                <tr>
                    <th width="110" colspan="2" class="pd-5-solid-top-center">{{$detail[0]['satuan']}}</th>
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
                    @foreach ($detail as $k => $v)
                        @php
                            $i = $k + 1;
                            $akr = !empty($v['akr']) ? $v['akr'] : '&nbsp;&nbsp;';
                            $satuan = ($v['satuan'] != "null") ? $v['satuan'] : '';
                        @endphp
                        <tr>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $i }}</td>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{!! $akr !!}&nbsp;
                                {!! $isManyNoSampel 
                                    ? '<sup>'.$v['no_sampel'].'</sup> '.$v['deskripsi_titik'] 
                                    : $v['parameter'] 
                                !!}
                            </td>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ str_replace('.', ',', $v['hasil_uji']) }}&nbsp;{{ $v['attr'] }}</td>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['baku_mutu'] }}</td>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{\App\Helpers\Helper::tanggal_indonesia($v['tanggal_sampling'])}}</td>
                        </tr>
                    @endforeach
                @else
                    @foreach ($detail as $k => $v)
                        @php
                            $i = $k + 1;
                            $akr = !empty($v['akr']) ? $v['akr'] : '&nbsp;&nbsp;';
                            $satuan = ($v['satuan'] != "null") ? $v['satuan'] : '';
                        @endphp
                        <tr>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $i }}</td>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{!! $akr !!}&nbsp;
                                {!! $isManyNoSampel 
                                    ? '<sup>'.$v['no_sampel'].'</sup> '.$v['deskripsi_titik'] 
                                    : $v['parameter'] 
                                !!}
                            </td>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ str_replace('.', ',', $v['hasil_uji']) }}&nbsp;{{ $v['attr'] }}</td>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['baku_mutu'] }}</td>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['nama_header'] }}</td>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $satuan }}</td>
                            <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{{ $v['methode'] }}</td>
                        </tr>
                    @endforeach
                @endif
                
            </tbody>
        </table>
    </div>
@endif

