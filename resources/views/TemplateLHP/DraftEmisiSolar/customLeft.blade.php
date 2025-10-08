@if (!empty($custom))
    <div class="left" style="page-break-before: always;">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
            <thead>
                <tr>
                    <th rowspan="2" width="6%" class="custom">NO</th>
                    <th rowspan="2" width="30%" class="custom">JENIS / NAMA KENDARAAN</th>
                    <th rowspan="2" width="12%" class="custom">BOBOT</th>
                    <th rowspan="2" width="12%" class="custom">TAHUN</th>
                    <th width="12%" class="custom">HASIL UJI</th>
                    <th width="12%" class="custom">BAKU MUTU **</th>
                    <th rowspan="2" width="16%" class="custom">TANGGAL SAMPLING</th>
                </tr>
               <tr>
                <th class="custom" colspan="2">Satuan = Opasitas (%)</th>
            </tr>
            </thead>
            <tbody>
                @foreach ($custom as $k => $v)
                    @php
                    $p = $k + 1;
                    $baku = json_decode($v->baku_mutu ?? '{}');
                    $hasil = json_decode($v->hasil_uji ?? '{}');
                    @endphp
                    <tr>
                    <td class="custom">{{ $p }}</td>
                    <td class="custom4">
                        <sup style="font-size:5px; !important; margin-top:-10px;">{{ $v->no_sampel ?? '' }}</sup>{{ $v->nama_kendaraan ?? '' }}
                    </td>
                    <td class="custom">{{ $v->bobot_kendaraan ?? '' }} TON</td>
                    <td class="custom">{{ $v->tahun_kendaraan ?? '' }}</td>
                    <td class="custom">{{ $hasil->OP ?? '' }}</td>
                    <td class="custom">{{ $baku->OP ?? '' }}</td>
                    <td class="custom">{{ \App\Helpers\Helper::tanggal_indonesia($v->tanggal_sampling) ?? '' }}</td>

                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
