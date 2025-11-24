@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; width: 100%;">
            <thead>
                <tr>
                    <th width="6%" class="custom" rowspan="2">NO</th>
                    <th width="32%" class="custom" rowspan="2">NAMA PEKERJA</th>
                    <th width="18%" class="custom" rowspan="2">LOKASI SAMPLING</th>
                    <th width="14%" class="custom" rowspan="2">DURASI PAPARAN PEKERJA PER JAM</th>
                    <th width="7%" class="custom">HASIL UJI</th>
                    <th width="7%" class="custom">NAB</th>
                    <th width="16%" class="custom" rowspan="2">TANGGAL SAMPLING</th>
                </tr>
                <tr>
                    <th class="custom" colspan="2">(dBA)</th>
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
                        <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}">
                            <sup style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup>
                            {{ $yy['nama_pekerja'] }}
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['lokasi_keterangan'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['paparan'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['hasil_uji'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['nab'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
