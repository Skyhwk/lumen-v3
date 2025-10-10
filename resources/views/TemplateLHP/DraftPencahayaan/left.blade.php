<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            <tr>
                <th width="5%" rowspan="2" class="custom">NO</th>
                <th width="25%" class="custom" rowspan="2">LOKASI / KETERANGAN SAMPEL</th>
                <th width="15%" class="custom">HASIL UJI</th>
                {{-- <th width="15%" class="custom">STANDART</th> --}}
                <th width="10%" class="custom" rowspan="2">SUMBER PENCAHAYAAN</th>
                <th width="10%" class="custom" rowspan="2">JENIS PENGUKURAN</th>
                <th width="10%" class="custom" rowspan="2">TANGGAL SAMPLING</th>

            </tr>
            <tr>
                <th class="custom">Satuan = LUX</th>
            </tr>
        </thead>
        <tbody>
            @php $totdat = count($detail); @endphp
            @foreach ($detail as $kk => $yy)
                @php
                    $p = $kk + 1;
                @endphp
                <tr>
                    <td class="custom">{{ $p }}</td>
                    <td class="custom4"><sup
                            style="font-size:5px; !important; margin-top:-10px;">{{ $yy['no_sampel'] }}</sup>{{ $yy['lokasi_keterangan'] }}
                    </td>
                    <td class="custom">{{ $yy['hasil_uji'] }}</td>
                    {{-- <td class="custom">{{ $yy['nab'] }}</td> --}}
                    <td class="custom">{{ $yy['sumber_cahaya'] }}</td>
                    <td class="custom">{{ $yy['jenis_pengukuran'] }}</td>
                    <td class="custom">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
