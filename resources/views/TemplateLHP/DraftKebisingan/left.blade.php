@php
  
@endphp

@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="8%" class="custom">NO</th>
                    <th width="40%"  class="custom">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="21%"  class="custom">HASIL UJI (dBA)</th>
                    <th width="21%" class="custom">JUMLAH JAM PEMAPARAN PER HARI</th>
                </tr>
               
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
       
                    <tr>
                        <td class="pd-5-solid-center">{{ $k + 1 }}</td>
                        <td class="pd-5-solid-left">
                            <sup style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup>
                          {{ $yy['lokasi_keterangan'] }}
                        </td>
                        <td class="pd-5-solid-center">{{ $yy['hasil_uji'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['paparan'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
