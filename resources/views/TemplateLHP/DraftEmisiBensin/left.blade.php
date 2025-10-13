<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px; width: 100%;">
        <thead>
            <tr>
                <th rowspan="2" width="6%" class="custom">NO</th>
                <th rowspan="2" width="20%" class="custom">JENIS / NAMA KENDARAAN</th>
                <th rowspan="2" width="10%" class="custom">BOBOT</th>
                <th rowspan="2" width="10%" class="custom">TAHUN</th>
                <th colspan="2" class="custom">HASIL UJI</th>
                <th colspan="2" class="custom">BAKU MUTU **</th>
                <th rowspan="2" width="14%" class="custom">TANGGAL SAMPLING</th>
            </tr>
            <tr>
                <th class="custom" width="10%">CO (%)</th>
                <th class="custom" width="10%">HC (ppm)</th>
                <th class="custom" width="10%">CO (%)</th>
                <th class="custom" width="10%">HC (ppm)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $total = count($detail);
            @endphp

            @foreach ($detail as $key => $value)
                @continue(!$value)

                @php
                    $p = $key + 1;
                    $rowClass = ($p == $total) ? 'solid' : 'dot';
                    $baku = json_decode($value->baku_mutu ?? '{}');
                    $hasil = json_decode($value->hasil_uji ?? '{}');
                @endphp
                <tr>
                    <td class="custom">{{ $p }}</td>
                    <td class="custom">
                        <sup style="font-size:5px; !important; margin-top:-10px;">{{ $value->no_sampel ?? '' }}</sup>{{ $value->nama_kendaraan ?? '' }}
                    </td>
                    <td class="custom">{{ $value->bobot_kendaraan ?? '' }} TON</td>
                    <td class="custom">{{ $value->tahun_kendaraan ?? '' }}</td>
                    <td class="custom">{{ $hasil->CO ?? '' }}</td>
                    <td class="custom">{{ $hasil->HC ?? '' }}</td>
                    <td class="custom">{{ $baku->CO ?? '' }}</td>
                    <td class="custom">{{ $baku->HC ?? '' }}</td>
                    <td class="custom">{{ \App\Helpers\Helper::tanggal_indonesia($value->tanggal_sampling) ?? '' }}</td>

                </tr>
            @endforeach


        </tbody>
    </table>
</div>



