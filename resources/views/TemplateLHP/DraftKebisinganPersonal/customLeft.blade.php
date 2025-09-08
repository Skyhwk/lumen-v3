@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="25" rowspan="2" class="pd-5-solid-top-center">NO</th>
                    <th width="200" rowspan="2" class="pd-5-solid-top-center">NAMA PEKERJA</th>
                    <th width="150" rowspan="2" class="pd-5-solid-top-center">LOKASI SAMPLING</th>
                    <th width="100" class="pd-5-solid-top-center" rowspan="2">DURASI PAPARAN PEKERJA PER JAM</th>
                    <th width="80" class="pd-5-solid-top-center">HASIL UJI</th>
                    <th width="80" class="pd-5-solid-top-center">NAB**</th>
                </tr>
                <tr>
                    <th class="pd-5-solid-top-center" colspan="2">(dBA)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($custom as $k => $yy)
       
                    <tr>
                        <td class="pd-5-solid-center">{{ $k + 1 }}</td>
                        <td class="pd-5-solid-left"><sup style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup>
                          {{ $yy['nama_pekerja'] }}
                        </td>
                        <td class="pd-5-solid-center">{{ $yy['lokasi_keterangan'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['paparan'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['hasil_uji'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['nab'] }}</td>
                      
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
