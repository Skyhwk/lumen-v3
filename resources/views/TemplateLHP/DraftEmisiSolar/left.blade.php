<div class="left">
    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <thead>
            <tr>
                <th rowspan="2" width="25" class="pd-5-solid-top-center">NO</th>
                <th rowspan="2" width="310" class="pd-5-solid-top-center">JENIS / NAMA KENDARAAN</th>
                <th rowspan="2" width="85" class="pd-5-solid-top-center">BOBOT</th>
                <th rowspan="2" width="85" class="pd-5-solid-top-center">TAHUN</th>
                <th width="105" class="pd-5-solid-top-center">HASIL UJI</th>
                <th width="105" class="pd-5-solid-top-center">BAKU MUTU **</th>
            </tr>
            <tr>
                <th class="pd-5-solid-top-center" colspan="2">Satuan = Opasitas (%)</th>
            </tr>
        </thead>
        <tbody>
            @php $total = count($detail); @endphp
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
                    <td class="pd-5-{{ $rowClass }}-center">{{ $hasil->OP ?? '' }}</td>
                    <td class="pd-5-{{ $rowClass }}-center">{{ $baku->OP ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>



