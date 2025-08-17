@php
  
@endphp

@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="8%" class="custom">NO</th>
                    <th width="30%" class="custom">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="20%"  class="custom">KECEPATAN ANGIN (mph)</th>
                    <th width="21%" class="custom">SUHU TEMPERATUR AKTUAL (°C)</th>
                    <th width="21%" class="custom">KONDISI</th>
                </tr>
               
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
       
                    <tr>
                        <td class="pd-5-solid-center">{{ $k + 1 }}</td>
                        <td class="pd-5-solid-left">
                            <sup style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup>
                          {{ $yy['keterangan'] }}
                        </td>
                        <td class="pd-5-solid-center">{{ $yy['kecepatan_angin'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['suhu_temperatur'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['kondisi'] }}</td>
                      
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
