@php
    $methode_sampling = $header->metode_sampling ? $header->metode_sampling : '-';
    $period = explode(" - ", $header->periode_analisa);
    $period = array_filter($period);
    $period1 = '';
    $period2 = '';
    $temptArrayPush = [];

    if(!empty($custom)){
        foreach ($custom as $key => $value) {
            foreach ($value as $kk => $yy) {
                 if (!empty($yy['akr']) && !in_array($yy['akr'], $temptArrayPush)) {
                    $temptArrayPush[] = $yy['akr'];
                }
                if (!empty($yy['attr']) && !in_array($yy['attr'], $temptArrayPush)) {
                    $temptArrayPush[] = $yy['attr'];
                }
            }
        }
    } else {
        foreach ($detail as $kk => $yy) {
                $p = $kk + 1;
                if (!empty($yy['akr']) && !in_array($yy['akr'], $temptArrayPush)) {
                    $temptArrayPush[] = $yy['akr'];
                }
                if (!empty($yy['attr']) && !in_array($yy['attr'], $temptArrayPush)) {
                    $temptArrayPush[] = $yy['attr'];
                }
        }
    }

    if (!empty($period)) {
        $period1 = \App\Helpers\Helper::tanggal_indonesia($period[0]);
        $period2 = \App\Helpers\Helper::tanggal_indonesia($period[1]);
    }

    $data_keterangan = implode(", ", json_decode($header->keterangan, true));
@endphp

<div class="right" style="margin-top: {{ $mode == 'downloadLHPFinal' ? '0px' : '14px' }};">
    <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
        <tr>
            <td>
                <table style="border-collapse: collapse; text-align: center;" width="100%">
                    <tr>
                        <td class="custom" width="120">No. LHP <sup style="font-size: 8px;"><u>a</u></sup></td>
                        <td class="custom" width="120">No. SAMPEL</td>
                        <td class="custom" width="200">JENIS SAMPEL</td>
                    </tr>
                    <tr>
                        <td class="custom" width="120">{{ $header->no_lhp }}</td>
                        <td class="custom" width="120">{{ $header->no_sampel }}</td>
                        <td class="custom" width="200">{{ $header->sub_kategori }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                {{-- Informasi Pelanggan --}}
                <table style="padding: 20px 0px 0px 0px;" width="100%">
                    <tr>
                        <td><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span></td>
                    </tr>
                    <tr>
                        <td class="custom5" width="120">Nama Pelanggan</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ $header->nama_pelanggan }}</td>
                    </tr>
                </table>

                {{-- Alamat Sampling --}}
                <table style="padding: 10px 0px 0px 0px;" width="100%">
                    <tr>
                        <td class="custom5" width="120">Alamat / Lokasi Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ $header->alamat_sampling }}</td>
                    </tr>
                </table>

                {{-- Informasi Sampling --}}
                <table style="padding: 10px 0px 0px 0px;" width="100%">
                    <tr>
                        <td class="custom5" colspan="7"><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span></td>
                    </tr>
                    <tr>
                        <td class="custom5">Tanggal Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5" colspan="5">{{ \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling) }}</td>
                    </tr>
                    <tr>
                        <td class="custom5">Periode Analisa</td>
                        <td class="custom5">:</td>
                        <td class="custom5" colspan="5">{{ $period1 }} - {{ $period2 }}</td>
                    </tr>
                    <tr>
                            <td class="custom5" width="120">Keterangan</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">{{ $data_keterangan }}</td>
                        </tr>
                </table>

                <table style="padding: 10px 0px 0px 0px;" width="100%">
                    @if ($header->regulasi != null)
                        @foreach (json_decode($header->regulasi) as $key => $value)
                            <tr>
                                <td class="custom5" colspan="3">**  {{ $value }}</td>
                            </tr>
                        @endforeach
                    @endif
                </table>

                <table style="padding: 10px 0px 0px 0px;" width="100%">
                    @if ($header->keterangan != null)
                        @foreach (json_decode($header->keterangan) as $key => $value)
                        @php
                            $found = false;
                            foreach ($temptArrayPush as $symbol) {
                                if (strpos($vx, $symbol) === 0) {
                                    $found = true;
                                    break;
                                }
                            }
                        @endphp
                        @if ($found)
                            <tr>
                                <td class="custom5" colspan="3">{{ $value }}</td>
                            </tr>
                        @endif
                        @endforeach
                    @endif
                </table>
            </td>
        </tr>
    </table>
</div>
