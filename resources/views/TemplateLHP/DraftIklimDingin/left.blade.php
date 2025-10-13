@php
  
@endphp

@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="8%" class="custom">NO</th>
                    <th width="25%" class="custom" colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="15%"  class="custom">KECEPATAN ANGIN (mph)</th>
                    <th width="16%" class="custom">SUHU TEMPERATUR AKTUAL (°C)</th>
                    <th width="16%" class="custom">KONDISI</th>
                    <th width="20%" class="custom">TANGGAL SAMPLING</th>

                </tr>
               
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
       
                    <tr>
                        <td class="custom">{{ $k + 1 }}</td>
                         <td class="custom4" width="7%" style="text-align: right; border-right: none;"> 
                             <sup  style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup> 
                        </td>
                        <td class="custom4" width="18%" style="border-left: none; text-align: left;"> 
                            {{ $yy['keterangan'] }}
                        </td>
                        <td class="custom">{{ $yy['kecepatan_angin'] }}</td>
                        <td class="custom">{{ $yy['suhu_temperatur'] }}</td>
                        <td class="custom">{{ $yy['kondisi'] }}</td>
                        <td class="custom">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
