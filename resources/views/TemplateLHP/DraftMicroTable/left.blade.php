
@if (!empty($header))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="25" class="pd-5-solid-top-center" >NO</th>
                    <th width="220" class="pd-5-solid-top-center" >LOKASI / KETERANGAN SAMPEL</th>
                    <th width="140" class="pd-5-solid-top-center" >SUHU<br/>(&#176;C)</th>
                    <th width="140" class="pd-5-solid-top-center" >KELEMBAPAN<br/>(%)</th>
                    <th width="140" class="pd-5-solid-top-center" >{{$detail[0]['parameter']}} <br/>({{ $detail[0]['satuan'] ?? 'CFU/m³'  }})</th>
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
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['suhu'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['kelembapan'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $v['hasil_uji'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

