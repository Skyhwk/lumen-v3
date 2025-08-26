@php
  
@endphp

@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="8%" class="custom">NO</th>
                    <th width="30%" class="custom">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="20%"  class="custom">INDEX SUHU BASAH DAN BOLA (°C)</th>
                    <th width="21%" class="custom">AKTIVITAS PEKERJAAN</th>
                    <th width="21%" class="custom">DURASI PAPARAN TERHADAP PEKERJAAN PER JAM</th>
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
                        <!-- <td class="pd-5-solid-center">{{ $yy['indeks_suhu_basah'] }}</td> -->
                        <td class="pd-5-solid-center">{{ $yy['hasil'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['aktivitas_pekerjaan'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['durasi_paparan'] }} Jam</td>
                      
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
