@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                  <tr>
                    <th width="8%" class="pd-5-solid-top-center" rowspan="2">NO</th>
                    <th width="25%" class="pd-5-solid-top-center" rowspan="2" colspan="2">KETERANGAN</th>
                    <th width="15%" class="pd-5-solid-top-center" rowspan="2">SUMBER GETARAN</th>
                    <th width="13%" class="pd-5-solid-top-center" rowspan="2">DUARSI JAM PEMAPARAN PER HARI</th>
                    <th width="13%" class="pd-5-solid-top-center">HASIL UJI (m/s<sup>2</sup>)</th>
                    <th width="11%" class="pd-5-solid-top-center">NAB <sup>**</sup></th>
                    <th width="15%" class="pd-5-solid-top-center" rowspan="2">TANGGAL SAMPLING</th>
                </tr>
                <tr>
                    <td class="pd-5-solid-center" colspan="2">(m/det<sup>2</sup>)</td>
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
                        $w_paparan = isset($v['w_paparan']) ? $v['w_paparan'] : '';
                        $sumber_get = isset($v['sumber_get']) ? $v['sumber_get'] : '';
                        $hasil = isset($v['hasil']) ? $v['hasil'] : '';
                        $nab = isset($v['nab']) ? $v['nab'] : '';
                        $tanggal_sampling = isset($v['tanggal_sampling']) ? \App\Helpers\Helper::tanggal_indonesia($v['tanggal_sampling']) : '';
                      
                    @endphp
                    <tr>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $number }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}" width="7%" style="text-align: right; border-right: none;"><sup  style="font-size: 5px; margin-top: -10px;">{!! $no_sampel !!}</sup></td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}" width="18%" style="border-left: none; text-align: left;">{{ $keterangan }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $sumber_get }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $w_paparan }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $hasil }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $nab }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $tanggal_sampling }}</td>

                    </tr>
                @endforeach

            </tbody>
        </table>
    </div>
@endif