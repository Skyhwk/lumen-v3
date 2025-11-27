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
        <table style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th width="8%" class="custom" rowspan="2">NO</th>
                    <th width="50%"  class="custom" rowspan="2" colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="21%"  class="custom" >HASIL UJI</th>
                    <th width="21%" class="custom" rowspan="2">TANGGAL SAMPLING</th>
                </tr>
                <tr>
                    <th class="custom">{{ $satuan }}</th>
                </tr>
            </thead>
            <tbody>
                @php $totdat = count($detail); @endphp
                @foreach ($detail as $kk => $yy)
                    @php
                        $i = $kk + 1;
                    @endphp
                    <tr>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $i }}</td>
                        <td class="{{ $i == $totdat ? 'pd-3-solid' : 'pd-3-dot' }}" width="7%" style="text-align: right; border-right: none;"> 
                            <sup  style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup> 
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-3-solid' : 'pd-3-dot' }}" width="43%" style="border-left: none; text-align: left;"> 
                            {{ $yy['keterangan'] }}
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['percepatan'] ?? $yy['kecepatan'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
