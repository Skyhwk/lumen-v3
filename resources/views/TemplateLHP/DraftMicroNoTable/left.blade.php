@if (!empty($header))
<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
        <thead>
            <tr>
                <th rowspan="2" width="25" class="pd-5-solid-top-center">NO</th>
                <th rowspan="2" width="300" class="pd-5-solid-top-center">LOKASI / KETERANGAN SAMPEL</th>
                <th width="170" class="pd-5-solid-top-center">HASIL UJI</th>
                <th width="170" class="pd-5-solid-top-center">BAKUMUTU**</th>
            </tr>
            <tr>
                <th colspan="2" width="140" class="pd-5-solid-top-center">SATUAN = <br />({{ $detail[0]['satuan'] ?? 'CFU/m³'  }})</th>
            </tr>
        </thead>
        <tbody>
            @php $totdat = count($detail); @endphp
            @foreach ($detail as $k => $v)
            @php
            $i = $k + 1;
            @endphp
            <tr>
                <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $i }}</td>
                <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">
                    <sup style="font-size: 5px; margin-top: -10px;">{{ $v['no_sampel'] }}</sup>
                    {{ $v['keterangan'] }}
                </td>
                <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['hasil_uji'] }}</td>
                <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['baku_mutu'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif