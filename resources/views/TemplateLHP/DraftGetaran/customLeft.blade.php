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

@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
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
                @php
                    $totdat = count($custom);
                @endphp
                @foreach ($custom as $k => $v)
                    @php
                        $number = $k + 1;
                        $no_sampel = !empty($v['no_sampel']) ? $v['no_sampel'] : '';
                        $keterangan = (isset($v['keterangan']) && $v['keterangan'] != "null") ? $v['keterangan'] : '';
                        $kecepatan = isset($v['kecepatan']) ? str_replace('.', ',', $v['kecepatan']) : '';
                        $percepatan = isset($v['percepatan']) ? $v['percepatan'] : '';
                        $w_paparan = isset($v['w_paparan']) ? $v['w_paparan'] : '';
                        $tanggal_sampling = isset($v['tanggal_sampling']) ? \App\Helpers\Helper::tanggal_indonesia($v['tanggal_sampling']) : '';
                    @endphp
                    <tr>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $number }}</td>
                                   <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-left' : 'pd-5-dot-left' }}" width="8%" style="text-align: right; border-right: none;"><sup  style="font-size: 5px; margin-top: -10px;">{!! $no_sampel !!}</sup></td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-left' : 'pd-5-dot-left' }}" width="32%" style="border-left: none; text-align: left;">{{ $keterangan }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $kecepatan }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $percepatan }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{{ $w_paparan }}</td>
                        <td class="{{ $k == ($totdat - 1) ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $tanggal_sampling }}</td>
                    </tr>
                @endforeach

            </tbody>
        </table>
    </div>
@endif