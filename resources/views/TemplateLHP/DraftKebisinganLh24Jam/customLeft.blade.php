@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;" width="100%">
            <thead>
                <tr>
                    <th width="5%" rowspan="2" class="custom">NO</th>
                    <th width="30%" rowspan="2" class="custom" colspan="2">LOKASI / KETERANGAN SAMPLE</th>
                    <th width="25%" class="custom"  colspan="3" >Kebisingan 24 Jam (dBA)</th>
                    <th width="23%" rowspan="2" class="custom">TITIK KOORDINAT</th>
                    <th width="17%" rowspan="2" class="custom">TANGGAL SAMPLING</th>
                </tr>
                <tr>
                    <th class="custom" >Ls (Siang)</th>
                    <th class="custom" >Lm (Malam)</th>
                    <th class="custom" >Ls-m (Siang-Malam)</th>
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
                        <td class="{{ $i == $totdat ? 'pd-3-solid' : 'pd-3-dot' }}" width="7%" style="text-align: right; border-right: none;"> 
                            <sup  style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup> 
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-3-solid' : 'pd-3-dot' }}" width="23%" style="border-left: none; text-align: left;"> 
                            {{ $yy['lokasi_keterangan'] }}
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['leq_ls'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['leq_lm'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['leq_lsm'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">{{ $yy['titik_koordinat'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>

                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
