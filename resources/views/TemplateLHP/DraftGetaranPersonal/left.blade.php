@php
  
@endphp

@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
               <tr>
                    <th width="8%" class="pd-5-solid-top-center" rowspan="2">NO</th>
                    <th width="25%" class="pd-5-solid-top-center" rowspan="2" colspan="2">KETERANGAN</th>
                    <th width="15%" class="pd-5-solid-top-center" rowspan="2">SUMBER GETARAN</th>
                    <th width="13%" class="pd-5-solid-top-center" rowspan="2">DUARSI JAM PEMAPARAN PER HARI</th>
                    <th width="13%" class="pd-5-solid-top-center">HASIL UJI (m/s<sup>2</sup>)</th>
                    <th width="11%" class="pd-5-solid-top-center">NAB <sup>**</sup></th>
                    <th width="15%" class="pd-5-solid-top-center" rowspan="2">TANGGAL SAMPLING</th>
                </tr>
                <tr>
                    <td class="pd-5-solid-center" colspan="2">(m/det<sup>2</sup>)</td>
                </tr>
               
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
                    <tr>
                        <td class="pd-5-solid-center">{{ $k + 1 }}</td>
                        <td class="pd-5-solid-center" width="7%" style="text-align: right; border-right: none;"> 
                             <sup  style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup> 
                        </td>
                        <td class="pd-5-solid-center" width="18%" style="border-left: none; text-align: left;"> 
                       {{ $yy['keterangan'] }}
                        </td>
                        <td class="pd-5-solid-center">{{ $yy['sumber_get'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['w_paparan'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['hasil'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['nab'] }}</td>
                        <td class="pd-5-solid-center">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
