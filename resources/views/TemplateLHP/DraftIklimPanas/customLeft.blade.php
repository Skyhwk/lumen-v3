@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="8%" class="custom">NO</th>
                    <th width="25%" class="custom" colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="15%"  class="custom">INDEX SUHU BASAH DAN BOLA (°C)</th>
                    <th width="16%" class="custom">AKTIVITAS PEKERJAAN</th>
                    <th width="16%" class="custom">DURASI PAPARAN TERHADAP PEKERJAAN PER JAM</th>
                    <th width="20%" class="custom">TANGGAL SAMPLING</th>
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
                            <sup style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] ?? '' }}</sup> 
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-3-solid' : 'pd-3-dot' }}" width="18%" style="border-left: none; text-align: left;"> 
                            {{ $yy['keterangan'] ?? '' }}
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['hasil'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['aktivitas_pekerjaan'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['durasi_paparan'] }} Jam</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
