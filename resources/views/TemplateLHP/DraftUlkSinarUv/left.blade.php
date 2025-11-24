@php
    $totData = $header->header_table ? count(json_decode($header->header_table)) : 0;
    $colc = '';
    $rowc = 1;

    if ($totData > 1) {
        $colc = 'colspan="' . $totData . '"';
        $rowc = 2;
    }
@endphp

@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="8%" class="custom" rowspan="2">NO</th>
                    <th width="30%" rowspan="2" class="custom">KETERANGAN</th>
                    <th width="30%" colspan="3" class="custom">HASIL UJI (mW/cm²)</th>
                    <th width="16%" rowspan="2" class="custom">NAB (mW/cm²)**</th>
                    <th width="16%" rowspan="2" class="custom">JUMLAH JAM PEMAPARAN PER HARI</th>
                    {{-- <th width="16%" rowspan="2" class="custom">TANGGAL SAMPLING</th> --}}
                </tr>
                <tr>
                    <th class="custom">MATA</th>
                    <th class="custom">SIKU</th>
                    <th class="custom">BETIS</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
                    @php
                        $akr = $yy['akr'] !== '' ? $yy['akr'] : '&nbsp;&nbsp;';
                          $hasil =    \App\Helpers\Helper::waktuPemaparan($yy['waktu_pemaparan']);
                    @endphp
                    <tr>
                        <td class="pd-5-solid-center">{{ $k + 1 }}</td>
                        <td class="pd-5-solid-left">
                            <sup style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup>
                            {{ $akr }} &nbsp;{{ $yy['keterangan'] }}
                        </td>
                        <td class="pd-5-solid-center">{{ $yy['mata'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['siku'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['betis'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['nab'] }}</td>
                        <td class="pd-5-solid-center">{{ $hasil }}</td>
                        {{-- <td class="pd-5-solid-center">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td> --}}
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
