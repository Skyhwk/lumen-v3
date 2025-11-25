@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; width: 100%;">
            <thead>
                <tr>
                    <th width="6%" class="custom">NO</th>
                    <th width="42%" class="custom" colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="14%" class="custom">HASIL UJI (dBA)</th>
                    <th width="18%" class="custom">JUMLAH JAM PEMAPARAN PER HARI</th>
                    <th width="20%" class="custom">TANGGAL SAMPLING</th>
                </tr>
            </thead>
            <tbody>
                @php $totdat = count($detail); @endphp
                @foreach ($detail as $k => $yy)
                    @php
                    $i = $k + 1;
                    @endphp
                    <tr>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $i }}</td>
                        <td class="{{ $i == $totdat ? 'pd-3-solid' : 'pd-3-dot' }}" width="7%" style="text-align: right; border-right: none;"> 
                            <sup  style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup> 
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-3-solid' : 'pd-3-dot' }}" width="35%" style="border-left: none; text-align: left;"> 
                            {{ $yy['lokasi_keterangan'] }}
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['hasil_uji'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['paparan'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
