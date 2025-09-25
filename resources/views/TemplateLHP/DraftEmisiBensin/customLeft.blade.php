@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
            <thead>
                <tr>
                    <th rowspan="2" width="25" class="pd-5-solid-top-center">NO</th>
                    <th rowspan="2" width="250" class="pd-5-solid-top-center">JENIS / NAMA KENDARAAN</th>
                    <th rowspan="2" width="75" class="pd-5-solid-top-center">BOBOT</th>
                    <th rowspan="2" width="75" class="pd-5-solid-top-center">TAHUN</th>
                    <th colspan="2" class="pd-5-solid-top-center">HASIL UJI</th>
                    <th colspan="2" class="pd-5-solid-top-center">BAKU MUTU **</th>
                </tr>
                <tr>
                    <th class="pd-5-solid-top-center" width="75">CO (%)</th>
                    <th class="pd-5-solid-top-center" width="75">HC (ppm)</th>
                    <th class="pd-5-solid-top-center" width="75">CO (%)</th>
                    <th class="pd-5-solid-top-center" width="75">HC (ppm)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($custom as $k => $v)
                    @php
                        $number = $k + 1;

                        $no_sampel = isset($v['no_sampel']) ? $v['no_sampel'] : '';
                        $nama_kendaraan = isset($v['nama_kendaraan']) ? $v['nama_kendaraan'] : '';
                        $bobot_kendaraan = isset($v['bobot_kendaraan']) ? $v['bobot_kendaraan'] : '';
                        $tahun_kendaraan = isset($v['tahun_kendaraan']) ? $v['tahun_kendaraan'] : '';

                        $hasil = json_decode($v['hasil_uji'] ?? '{}');
                        $hasil_co = isset($hasil->CO) ? $hasil->CO : '';
                        $hasil_hc = isset($hasil->HC) ? $hasil->HC : '';

                        $baku = json_decode($v['baku_mutu'] ?? '{}');
                        $baku_co = isset($baku->CO) ? $baku->CO : '';
                        $baku_hc = isset($baku->HC) ? $baku->HC : '';

                        $totdat = count($custom);
                    @endphp
                    <tr>
                        <td class="{{ $k == $totdat - 1 ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $number }}</td>
                        <td class="{{ $k == $totdat - 1 ? 'pd-5-solid-left' : 'pd-5-dot-left' }}"><sup style="font-size:5px; !important; margin-top:-10px;">{{ $no_sampel }}</sup>{{ $nama_kendaraan }}</td>
                        <td class="{{ $k == $totdat - 1 ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $bobot_kendaraan }} TON</td>
                        <td class="{{ $k == $totdat - 1 ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $tahun_kendaraan }}</td>
                        <td class="{{ $k == $totdat - 1 ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $hasil_co }}</td>
                        <td class="{{ $k == $totdat - 1 ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $hasil_hc }}</td>
                        <td class="{{ $k == $totdat - 1 ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $baku_co }}</td>
                        <td class="{{ $k == $totdat - 1 ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $baku_hc }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
