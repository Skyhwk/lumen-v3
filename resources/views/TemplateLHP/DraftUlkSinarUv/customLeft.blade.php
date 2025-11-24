@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="8%" class="custom" rowspan="2">NO</th>
                    <th width="30%" rowspan="2" class="custom">KETERANGAN</th>
                    <th width="30%" colspan="3" class="custom">HASIL UJI (mW/cm²)</th>
                    <th width="16%" rowspan="2" class="custom">NAB (mW/cm²)**</th>
                    <th width="16%" rowspan="2" class="custom">JUMLAH JAM PEMAPARAN PER HARI</th>
                    {{-- <th width="16%" rowspan="2" class="custom">TANGGAL SAMPLING</th> --}}
                </tr>
                <tr>
                    <th class="custom">MATA</th>
                    <th class="custom">SIKU</th>
                    <th class="custom">BETIS</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totdat = count($custom);
                @endphp
                @foreach ($custom as $k => $v)
                    @php
                        $number = $k + 1;
                        $no_sampel = !empty($v['no_sampel']) ? $v['no_sampel'] : '';
                        $keterangan = (isset($v['keterangan']) && $v['keterangan'] != "null") ? $v['keterangan'] : '';
                        $mata = isset($v['mata']) ? str_replace('.', ',', $v['mata']) : '';
                        $siku = isset($v['siku']) ? $v['siku'] : '';
                        $betis = isset($v['betis']) ? $v['betis'] : '';
                        $waktu_pemaparan = isset($v['waktu_pemaparan']) ? \App\Helpers\Helper::waktuPemaparan($v['waktu_pemaparan']) : '';
                        $nab = isset($v['nab']) ? $v['nab'] : '';
                        // $tanggal_sampling = isset($v['tanggal_sampling']) ? \App\Helpers\Helper::tanggal_indonesia($v['tanggal_sampling']) : '';
                        
                      
                    @endphp
                    <tr>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $number }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-left' : 'pd-5-dot-left' }}"><sup>{!! $no_sampel !!}</sup>{{ $keterangan }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $mata }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $siku }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $betis }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $nab }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $waktu_pemaparan }}</td>
                        {{-- <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $tanggal_sampling }}</td> --}}
                    </tr>
                @endforeach

            </tbody>
        </table>
    </div>
@endif