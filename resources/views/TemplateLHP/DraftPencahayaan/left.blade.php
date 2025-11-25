<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            <tr>
                <th width="5%" rowspan="2" class="custom">NO</th>
                <th width="50%" class="custom" rowspan="2" colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                <th width="10%" class="custom">HASIL UJI</th>
                <th width="10%" class="custom" rowspan="2">SUMBER PENCAHAYAAN</th>
                <th width="10%" class="custom" rowspan="2">JENIS PENGUKURAN</th>
                <th width="15%" class="custom" rowspan="2">TANGGAL SAMPLING</th>

            </tr>
            <tr>
                <th class="custom">Satuan = LUX</th>
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
                        <sup style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup> 
                    </td>
                    <td class="{{ $i == $totdat ? 'pd-3-solid' : 'pd-3-dot' }}" width="40%" style="border-left: none; text-align: left;">
                        {{ $yy['lokasi_keterangan'] }}
                    </td>
                    <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['hasil_uji'] }}</td>
                    <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['sumber_cahaya'] }}</td>
                    <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['jenis_pengukuran'] }}</td>
                    <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
