@if (!empty($custom))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                <th rowspan="2" width="25" class="pd-5-solid-top-center">NO</th>
                <th rowspan="2" width="240" class="pd-5-solid-top-center">LOKASI / KETERANGAN SAMPEL</th>
                <th colspan="2" width="200" class="pd-5-solid-top-center">HASIL UJI</th>
                <th colspan="2" width="200" class="pd-5-solid-top-center">BAKUMUTU**</th>
            </tr>
            <tr>
                <th width="100" class="pd-5-solid-top-center">Jumlah BAKTERI TOTAL(CFU/m³)</th>
                <th width="100" class="pd-5-solid-top-center">FUNGAL COUNTS(CFU/m³)</th>
                <th width="100" class="pd-5-solid-top-center">JUMLAH BAKTERI TOTAL(CFU/m³)</th>
                <th width="100" class="pd-5-solid-top-center">FUNGAL COUNTS(CFU/m³)</th>
            </tr>
            </thead>
            <tbody>
                @php 
                    $detailCombine = $custom->groupBy('no_sampel');
                    $dataUse = [];
                    foreach ($detailCombine as $key => $value) {
                        $temp = (object)[];
                        $temp->fungal = '-';
                        $temp->bakteri = '-';
                        $temp->fungal_bakumutu = '-';
                        $temp->bakteri_bakumutu = '-';

                        foreach ($value as $k => $v) {
                            if($v->parameter == 'Fungal Counts'){
                                $temp->fungal = $v->hasil_uji;
                                $temp->fungal_bakumutu = $v->baku_mutu;
                            } else if($v->parameter == 'Jumlah Bakteri Total'){ 
                                $temp->bakteri = $v->hasil_uji;
                                $temp->bakteri_bakumutu = $v->baku_mutu;
                            }
                        }
                        $temp->no_sampel = $key;
                        $temp->keterangan = $value->first()->keterangan;
                        $temp->satuan = $value->first()->satuan;
                        $dataUse[] = $temp;
                    }
                    $totdat = count($dataUse); 
                @endphp
                @foreach ($dataUse as $k => $v)
                    @php
                        $i = $k + 1;
                    @endphp
                    <tr>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $i }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">
                            <sup style="font-size: 5px; margin-top: -10px;">{{ $v['no_sampel'] }}</sup>
                            {{ $v['keterangan'] }}
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['fungal'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['bakteri'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['fungal_bakumutu'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['bakteri_bakumutu'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif