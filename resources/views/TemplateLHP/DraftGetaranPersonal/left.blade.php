@php
  
@endphp

@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
               <tr>
                    <th width="8%" class="custom" rowspan="2">NO</th>
                    <th width="25%" class="custom" rowspan="2" colspan="2">KETERANGAN</th>
                    <th width="15%" class="custom" rowspan="2">SUMBER GETARAN</th>
                    <th width="13%" class="custom" rowspan="2">DUARSI JAM PEMAPARAN PER HARI</th>
                    <th width="13%" class="custom">HASIL UJI</th>
                    <th width="11%" class="custom">NAB</th>
                    <th width="15%" class="custom" rowspan="2">TANGGAL SAMPLING</th>
                </tr>
                <tr>
                    <td class="custom" colspan="2">(m/det<sup>2</sup>)</td>
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
                        <td class="custom">{{ $yy['sumber_get'] }}</td>
                        <td class="custom">{{ $yy['w_paparan'] }}</td>
                        <td class="custom">{{ $yy['hasil'] }}</td>
                        <td class="custom">{{ $yy['nab'] }}</td>
                        <td class="custom">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
