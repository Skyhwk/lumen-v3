@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; table-layout: auto; width: 100%;">
            <thead>
                <tr>
                    <th width="6%" class="custom">NO</th>
                    <th width="30%" class="custom" colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="10%" class="custom">HASIL UJI</th>
                    <th width="30%" class="custom">TITIK KOORDINAT</th>
                    <th width="24%" class="custom">TANGGAL SAMPLING</th>
                </tr>
            </thead>
            <tbody>
            @php $totdat = count($custom); @endphp
                @foreach ($custom as $k => $yy)
                    @php
                    $i = $k + 1;
                    @endphp
                    <tr>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $i }}</td>
                        <td class="{{ $i == $totdat ? 'pd-3-solid' : 'pd-3-dot' }}" width="9%" style="text-align: right; border-right: none;"> 
                            <sup  style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup> 
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-3-solid' : 'pd-3-dot' }}" width="21%" style="border-left: none; text-align: left;"> 
                            {{ $yy['lokasi_keterangan'] }}
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['hasil_uji'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{{ $yy['titik_koordinat'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
