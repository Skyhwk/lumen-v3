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
                    <th width="8%" class="custom" >NO</th>
                    <th width="40%"  class="custom"  colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="21%"  class="custom" >HASIL UJI {{ $satuan }}</th>
                    <th width="21%" class="custom" >TANGGAL SAMPLING</th>
                </tr>
            
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
                    <tr>
                        <td class="custom">{{ $k + 1 }}</td>
                        <td class="custom" width="8%" style="text-align: right; border-right: none;"> 
                             <sup  style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup> 
                        </td>
                        <td class="custom3" width="32%" style="border-left: none; text-align: left;"> 
                            {{ $yy['keterangan'] }}
                        </td>
                        @php
                            $hasil = '';
                            $regulasi = json_decode($header->regulasi)[0];
                            $arrRegulasi = explode('-', $regulasi);
                            if(count($arrRegulasi) > 1){
                                $idRegulasi = $arrRegulasi[0];
                                    if(in_array($idRegulasi, [61, 204, 60])) {
                                        $hasil =  $yy['kecepatan'];
                                    } else if (in_array($idRegulasi, [228])) {
                                        $hasil =  $yy['percepatan'];
                                    }
                            }
                        @endphp
                        <td class="custom">{{ $hasil }}</td>
                        <td class="custom">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>

                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
