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
                    <th width="3%" class="custom" rowspan="2">NO</th>
                    <th width="28%" rowspan="2" class="custom" colspan="2">KETERANGAN</th>
                    <th width="10%" rowspan="2" class="custom">SUMBER RADIASI</th>
                    <th width="22%" colspan="3" class="custom">HASIL UJI (mW/cm²)</th>
                    <th width="7%" rowspan="2" class="custom">NAB (mW/cm²)</th>
                    <th width="15%" rowspan="2" class="custom">JUMLAH JAM PEMAPARAN PER HARI</th>
                    <th width="15%" rowspan="2" class="custom">TANGGAL SAMPLING</th>
                </tr>
                <tr>
                    <th class="custom">MATA</th>
                    <th class="custom">SIKU</th>
                    <th class="custom">BETIS</th>
                </tr>
            </thead>
            <tbody>
                @php $totdat = count($detail); @endphp
                @foreach ($detail as $k => $yy)
                    @php
                    $i = $k + 1;
                    
                    $akr = $yy['akr'] !== '' ? $yy['akr'] : '&nbsp;&nbsp;';
                    $hasil =    \App\Helpers\Helper::waktuPemaparan($yy['waktu_pemaparan']);
                    @endphp
                    <tr>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $i }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-left' : 'pd-5-dot-left' }}" style="text-align: left; border-right: none;">
                            {{ $yy['no_sampel'] }}
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-3-solid' : 'pd-3-dot' }}" style="border-left: none; text-align: left;"> 
                            {{ $yy['keterangan'] }}
                        </td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['sumber_radiasi'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['mata'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['siku'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['betis'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $yy['nab'] }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{ $hasil }}</td>
                        <td class="{{ $i == $totdat ? 'pd-5-solid-center' : 'pd-5-dot-center' }}">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
