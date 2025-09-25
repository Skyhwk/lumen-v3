<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            <tr>
                <th width="5%" rowspan="2" class="pd-5-solid-top-center">NO</th>
                <th width="25%" class="pd-5-solid-top-center" rowspan="2">LOKASI / KETERANGAN SAMPEL</th>
                <th width="15%" class="pd-5-solid-top-center">HASIL UJI</th>
                {{-- <th width="15%" class="pd-5-solid-top-center">STANDART</th> --}}
                <th width="10%" class="pd-5-solid-top-center" rowspan="2">SUMBER PENCAHAYAAN</th>
                <th width="10%" class="pd-5-solid-top-center" rowspan="2">JENIS PENGUKURAN</th>
                <th width="10%" class="pd-5-solid-top-center" rowspan="2">TANGGAL SAMPLING</th>

            </tr>
            <tr>
                <th class="pd-5-solid-top-center">Satuan = LUX</th>
            </tr>
        </thead>
        <tbody>
            @php $totdat = count($detail); @endphp
            @foreach ($detail as $kk => $yy)
                @php
                    $p = $kk + 1;
                @endphp
                <tr>
                    <td class="pd-5-solid-center">{{ $p }}</td>
                    <td class="pd-5-solid-left"><sup
                            style="font-size:5px; !important; margin-top:-10px;">{{ $yy['no_sampel'] }}</sup>{{ $yy['lokasi_keterangan'] }}
                    </td>
                    <td class="pd-5-solid-center">{{ $yy['hasil_uji'] }}</td>
                    {{-- <td class="pd-5-solid-center">{{ $yy['nab'] }}</td> --}}
                    <td class="pd-5-solid-center">{{ $yy['sumber_cahaya'] }}</td>
                    <td class="pd-5-solid-center">{{ $yy['jenis_pengukuran'] }}</td>
                    <td class="pd-5-solid-center">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
