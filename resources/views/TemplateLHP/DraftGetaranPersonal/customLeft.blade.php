@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                  <tr>
                    <th width="8%" class="custom" rowspan="2">NO</th>
                    <th width="25%" class="custom" rowspan="2" colspan="2">KETERANGAN</th>
                    <th width="15%" class="custom" rowspan="2">SUMBER GETARAN</th>
                    <th width="13%" class="custom" rowspan="2">DUARSI JAM PEMAPARAN PER HARI</th>
                    <th width="13%" class="custom">HASIL UJI</th>
                    <th width="11%" class="custom">NAB <sup>**</sup></th>
                    <th width="15%" class="custom" rowspan="2">TANGGAL SAMPLING</th>
                </tr>
                <tr>
                    <td class="custom" colspan="2">(m/det<sup>2</sup>)</td>
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
                        <td class="{{ $k == ($totdat - 1) ? 'custom' : 'custom' }}">{{ $number }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'custom4' : 'custom4' }}" width="7%" style="text-align: right; border-right: none;"><sup  style="font-size: 5px; margin-top: -10px;">{!! $no_sampel !!}</sup></td>
                        <td class="{{ $k == ($totdat - 1) ? 'custom4' : 'custom4' }}" width="18%" style="border-left: none; text-align: left;">{{ $keterangan }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'custom' : 'custom' }}">{{ $sumber_get }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'custom' : 'custom' }}">{{ $w_paparan }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'custom' : 'custom' }}">{{ $hasil }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'custom' : 'custom' }}">{{ $nab }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'custom' : 'custom' }}">{{ $tanggal_sampling }}</td>

                    </tr>
                @endforeach

            </tbody>
        </table>
    </div>
@endif