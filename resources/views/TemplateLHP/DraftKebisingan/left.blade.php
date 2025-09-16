@php
  
@endphp

@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="8%" class="custom">NO</th>
                    <th width="40%" class="custom" colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="15%" class="custom">HASIL UJI (dBA)</th>
                    <th width="18%" class="custom">JUMLAH JAM PEMAPARAN PER HARI</th>
                    <th width="18%" class="custom">TANGGAL SAMPLING</th>
                </tr>
               
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
       
                    <tr>
                        <td class="pd-5-solid-center">{{ $k + 1 }}</td>
                        <td class="bordered" width="8%" style="text-align: right; border-right: none;"> <!--style="border-right: none;"-->
                            <sup style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup> <!--style="margin-top: -10px;"-->
                        </td>
                        <td class="bordered" width="40%" style="border-left: none; text-align: left;"> <!--style="border-left: none;"-->
                          {{ $yy['lokasi_keterangan'] }}
                        </td>
                        <td class="pd-5-solid-center">{{ $yy['hasil_uji'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['paparan'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['tanggal_sampling'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
