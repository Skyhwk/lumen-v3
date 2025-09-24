@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
               <     <th width="6%" rowspan="2" class="custom">NO</th>
                    <th width="25%" rowspan="2" colspan="2" class="custom">LOKASI / KETERANGAN SAMPLE</th>
                    <th width="25%" class="custom"  colspan="3" >Kebisingan 24 Jam (dBA)</th>
                    <th width="17%" rowspan="2" class="custom">TITIK KOORDINAT</th>
                    <th width="17%" rowspan="2" class="custom">TANGGAL SAMPLING</th>
                </tr>
                <tr>
                    <th class="custom" >Ls (Siang)</th>
                    <th class="custom" >Lm (Malam)</th>
                    <th class="custom" >Ls-m (Siang-Malam)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($custom as $k => $yy)
                    <tr>
                        <td class="pd-5-solid-center">{{ $k + 1 }}</td>
                        <td width="5%" style="text-align: right; border-right: none; vertical-align: top;" class="pd-5-solid-left">
                            <sup style="font-size: 7px;">{{ $yy['no_sampel'] }}</sup>
                        </td>
                        <td width="20%" style="border-left: none; text-align: left; word-wrap: break-word; white-space: normal;" class="pd-5-solid-left">
                            {{ $yy['lokasi_keterangan'] }}
                        </td>
                        <td class="pd-5-solid-center">{{ $yy['leq_ls'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['leq_lm'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['leq_lsm'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['titik_koordinat'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['tanggal_sampling'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
