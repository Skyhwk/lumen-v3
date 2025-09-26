@php
  $satuan = '';
  $regulasi = json_decode($header->regulasi)[0];
  $arrRegulasi = explode('-', $regulasi);
  if(count($arrRegulasi) > 1){
      $idRegulasi = $arrRegulasi[0];
        if(in_array($idRegulasi, [61, 204, 60])) {
            $satuan = '(mm/detik)';
        } else if (in_array($idRegulasi, [228])) {
            $satuan = '(10^-6 m)';
        }
  }

@endphp

@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="8%" class="custom" rowspan="2">NO</th>
                    <th width="40%"  class="custom" rowspan="2" colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="21%"  class="custom" colspan="2">HASIL UJI {{ $satuan }}</th>
                    <th width="21%" class="custom" rowspan="2">JUMLAH JAM PEMAPARAN PER HARI</th>
                    <th width="21%" class="custom" rowspan="2">TANGGAL SAMPLING</th>
                </tr>
                 <tr>
                    <td class="pd-5-solid-center" >KECEPATAN</td> 
                    <td class="pd-5-solid-center" >PERCEPATAN</td> 
                </tr>
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
                    <tr>
                        <td class="pd-5-solid-center">{{ $k + 1 }}</td>
                        <td class="pd-5-solid-center" width="8%" style="text-align: right; border-right: none;"> 
                             <sup  style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup> 
                        </td>
                        <td class="pd-5-solid-left" width="32%" style="border-left: none; text-align: left;"> 
                            {{ $yy['keterangan'] }}
                        </td>
                        <td class="pd-5-solid-center">{{ $yy['kecepatan'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['percepatan'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['paparan'] }}</td>
                        <td class="pd-5-solid-center">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>

                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
