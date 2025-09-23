@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
               <tr>
                    <th width="25" rowspan="2" class="pd-5-solid-top-center">NO</th>
                    <th width="200" rowspan="2" class="pd-5-solid-top-center">LOKASI / KETERANGAN SAMPLE</th>
                    <th width="100" class="pd-5-solid-top-center" >Kebisingan 24 Jam (dBA)</th>
                    <th width="80" rowspan="2" class="pd-5-solid-top-center">TITIK KOORDINAT</th>
                </tr>
                <tr>
                    <th class="pd-5-solid-top-center" >Ls (Siang)</th>
                    <th class="pd-5-solid-top-center" >Lm (Malam)</th>
                    <th class="pd-5-solid-top-center" >Ls-m (Siang-Malam)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($custom as $k => $yy)
                    <tr>
                        <td class="pd-5-solid-center">{{ $k + 1 }}</td>
                        <td class="pd-5-solid-left"><sup style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup>
                            {{ $yy['lokasi_keterangan'] }}
                        </td>
                        <td class="pd-5-solid-center">{{ $yy['leq_ls'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['leq_lm'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['leq_lsm'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['titik_koordinat'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
