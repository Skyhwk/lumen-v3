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
                <th colspan="2" width="14%" class="custom">TANGGAL SAMPLING</th>
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
                    <td class="pd-5-{{ $rowClass }}-center">{{ $p }}</td>
                    <td class="pd-5-{{ $rowClass }}-left">
                        <sup style="font-size:5px; !important; margin-top:-10px;">{{ $value->no_sampel ?? '' }}</sup>{{ $value->nama_kendaraan ?? '' }}
                    </td>
                    <td class="pd-5-{{ $rowClass }}-center">{{ $value->bobot_kendaraan ?? '' }} TON</td>
                    <td class="pd-5-{{ $rowClass }}-center">{{ $value->tahun_kendaraan ?? '' }}</td>
                    <td class="pd-5-{{ $rowClass }}-center">{{ $hasil->CO ?? '' }}</td>
                    <td class="pd-5-{{ $rowClass }}-center">{{ $hasil->HC ?? '' }}</td>
                    <td class="pd-5-{{ $rowClass }}-center">{{ $baku->CO ?? '' }}</td>
                    <td class="pd-5-{{ $rowClass }}-center">{{ $baku->HC ?? '' }}</td>
                    <td class="pd-5-{{ $rowClass }}-center">{{ \App\Helpers\Helper::tanggal_indonesia($value->tanggal_sampling) ?? '' }}</td>

                </tr>
            @endforeach


        </tbody>
    </table>
</div>



