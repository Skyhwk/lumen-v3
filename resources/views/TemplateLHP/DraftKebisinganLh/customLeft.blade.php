@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; table-layout: auto; width: 100%;">
            <thead>
                <tr>
                    <th width="6%" class="custom">NO</th>
                    <th width="30%" class="custom" colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="10%" class="custom">HASIL UJI</th>
                    <th width="30%" class="custom">TITIK KOORDINAT</th>
                    <th width="24%" class="custom">TANGGAL SAMPLING</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($custom as $k => $yy)
                    <tr>
                        <td class="custom">{{ $k + 1 }}</td>
                        <td style="text-align: right; border-right: none; vertical-align: top;" class="custom4" width="6%">
                            <sup style="font-size: 7px;">{{ $yy['no_sampel'] }}</sup>
                        </td>
                        <td style="border-left: none; text-align: left; word-wrap: break-word; white-space: normal;" class="custom" width="24%">
                            {{ $yy['lokasi_keterangan'] }}
                        </td>
                        <td class="custom" width="10%">{{ $yy['hasil_uji'] }}</td>
                        <td class="custom" width="30%">{{ $yy['titik_koordinat'] }}</td>
                        <td class="custom" width="24%">{{ $yy['tanggal_sampling'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
