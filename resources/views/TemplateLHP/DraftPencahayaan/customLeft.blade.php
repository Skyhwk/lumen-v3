@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="5%" rowspan="2" class="pd-5-solid-top-center">NO</th>
                    <th width="25%" class="pd-5-solid-top-center" rowspan="2">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="15%" class="pd-5-solid-top-center">HASIL UJI</th>
                    <th width="15%" class="pd-5-solid-top-center">STANDART</th>
                    <th width="10%" class="pd-5-solid-top-center" rowspan="2">SUMBER PENCAHAYAAN</th>
                    <th width="10%" class="pd-5-solid-top-center" rowspan="2">JENIS PENGUKURAN</th>
                    <th width="10%" class="pd-5-solid-top-center" rowspan="2">TANGGAL SAMPLING</th>

                </tr>
                <tr>
                    <th class="pd-5-solid-top-center">Satuan = LUX</th>
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
                        $keterangan =
                            isset($v['lokasi_keterangan']) && $v['lokasi_keterangan'] != 'null'
                                ? $v['lokasi_keterangan']
                                : '';
                        $sumber_get = isset($v['sumber_cahaya']) ? $v['sumber_cahaya'] : '';
                        $hasil = isset($v['hasil_uji']) ? $v['hasil_uji'] : '';
                        $jenis_pengukuran = isset($v['jenis_pengukuran']) ? $v['jenis_pengukuran'] : '';
                        $nab = isset($v['nab']) ? $v['nab'] : '';
                        $sumber_cahaya = isset($v['sumber_cahaya']) ? $v['sumber_cahaya'] : '';
                        $tanggal_sampling = isset($v['tanggal_sampling']) ? \App\Helpers\Helper::tanggal_indonesia($v['tanggal_sampling']) : '';
                    @endphp
                    <tr>
                        <td class="pd-5-solid-center">
                            {{ $number }}</td>
                        <td class="pd-5-solid-left"><sup
                                style="font-size:5px; !important; margin-top:-10px;">{{ $no_sampel }}</sup>{{ $keterangan }}
                        </td>
                        <td class="pd-5-solid-center">
                            {{ $hasil }}</td>
                        <td class="pd-5-solid-center">
                            {{ $nab }}</td>
                        <td class="pd-5-solid-center">
                            {{ $sumber_cahaya }}</td>
                        <td class="pd-5-solid-center">
                            {{ $jenis_pengukuran }}</td>
                        <td class="pd-5-solid-center">
                            {{ $tanggal_sampling }}</td>
                    </tr>
                @endforeach

            </tbody>
        </table>
    </div>
@endif
