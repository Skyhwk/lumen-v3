@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; width: 100%;">
            <thead>
                <tr>
                    <th width="6%" class="custom">NO</th>
                    <th width="42%" class="custom" colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="14%" class="custom">HASIL UJI (dBA)</th>
                    <th width="18%" class="custom">JUMLAH JAM PEMAPARAN PER HARI</th>
                    <th width="20%" class="custom">TANGGAL SAMPLING</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
                    <tr>
                        <td class="custom">{{ $k + 1 }}</td>
                        <td width="7%" style="text-align: right; border-right: none; vertical-align: top;" class="custom4">
                            <sup style="font-size: 7px;">{{ $yy['no_sampel'] }}</sup>
                        </td>
                        <td width="35%" style="border-left: none; text-align: left; word-wrap: break-word; white-space: normal;" class="custom">
                            {{ $yy['lokasi_keterangan'] }}
                        </td>
                        <td class="custom">{{ $yy['hasil_uji'] }}</td>
                        <td class="custom">{{ $yy['paparan'] }}</td>
                        <td class="custom">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
