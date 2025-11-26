@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;
    use App\Models\DetailLingkunganKerja;
    use Carbon\Carbon;

    // $tanggal_sampling = '';
    // if($header->status_sampling == 'S24'){
    //     $detailLapangan = DetailLingkunganKerja::where('no_sampel', $header->no_sampel)->where('shift_pengambilan', 'L2')->first();

    //     $tanggalAwal = $header->tanggal_sampling;

    //     $tanggalAkhir = Carbon::parse($tanggalAwal)->addDay()->format('Y-m-d');
    //     $tanggalAwal = Carbon::parse($tanggalAwal)->format('Y-m-d');

    //     if ($tanggalAwal || $tanggalAkhir) {
    //         if ($tanggalAwal == $tanggalAkhir) {
    //         $tanggal_sampling = \App\Helpers\Helper::tanggal_indonesia($tanggalAwal);
    //         } else {
    //             $tanggal_sampling = \App\Helpers\Helper::tanggal_indonesia($tanggalAwal) . ' - ' . \App\Helpers\Helper::tanggal_indonesia($tanggalAkhir);
    //         }
    //     } else {
    //         $tanggal_sampling = '-';
    //     }
    // } else {
    
    $isManyNoSampel = $header->is_many_sampel == 1 ? true : false;
    if ($header->tanggal_sampling_awal || $header->tanggal_sampling_akhir) {
        if ($header->tanggal_sampling_awal == $header->tanggal_sampling_akhir) {
            $tanggal_sampling = \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling_awal);
        } elseif ($header->tanggal_sampling_akhir == null) {
            $tanggal_sampling = \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling_awal);
        } else {
            $tanggal_sampling =
                \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling_awal) .
                ' - ' .
                \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling_akhir);
        }
    } elseif ($header->tanggal_sampling || $header->tanggal_terima) {
        if ($header->tanggal_sampling == $header->tanggal_terima) {
            $tanggal_sampling = \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling);
        } else {
            $tanggal_sampling =
                \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling) .
                ' - ' .
                \App\Helpers\Helper::tanggal_indonesia($header->tanggal_terima);
        }
    } else {
        $tanggal_sampling = '-';
    }
    // }
@endphp
<div class="right" style="margin-top: {{ $mode == 'downloadLHPFinal' ? '0px' : '14px' }};">
    <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
        <tr>
            <td>
                <table style="border-collapse: collapse; text-align: center;" width="100%">
                    <tr>
                        <td class="custom" width="33%">No. LHP {!! $showKan ? '<sup><u>a</u></sup>' : '' !!}</td>
                        @if (!$isManyNoSampel)
                            <td class="custom" width="33%">No. SAMPEL</td>
                        @endif
                        <td class="custom" width="33%">JENIS SAMPEL</td>
                    </tr>
                    <tr>
                        <td class="custom">{{ $header->no_lhp }}</td>
                        @if (!$isManyNoSampel)
                            <td class="custom">{{ $header->no_sampel }}</td>                            
                        @endif
                        <td class="custom">Lingkungan Kerja</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                {{-- Informasi Pelanggan --}}
                <table style="padding: 20px 0px 0px 0px;" width="100%">
                    <tr>
                        <td><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span>
                        </td>
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
                        <td class="custom5">{!! html_entity_decode($header->alamat_sampling) !!}</td>
                    </tr>
                </table>

                {{-- Informasi Sampling --}}
                <table style="padding: 10px 0px 0px 0px;" width="100%">                    
                    <tr>
                        <td class="custom5" width="120">
                            <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                        </td>
                    </tr>
                    @if ($isManyNoSampel)
                        @php
                            $prameter = $header->parameter_uji ? json_decode($header->parameter_uji, true) : [];
                        @endphp
                        <tr>
                            <td class="custom5" width="120">Parameter Pengujian</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">
                                <table width="100%" style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                                    @foreach($prameter as $index => $item)
                                        <tr>
                                            @if (count($prameter) > 1)
                                                <td class="custom5" width="20">{{ $index + 1 }}.</td>
                                                <td class="custom5">{{ $item ?? '-' }}</td>
                                            @else
                                                <td class="custom5" colspan="2">{{ $item ?? '-' }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                        @php
                            $methode_sampling = $header->methode_sampling ? json_decode($header->methode_sampling, true) : [];
                        @endphp
                        <tr>
                            <td class="custom5" width="120">Metode Sampling</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">
                                <table width="100%" style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                                    @foreach($methode_sampling as $index => $item)
                                        <tr>
                                            @if (count($methode_sampling) > 1)
                                                <td class="custom5" width="20">{{ $index + 1 }}.</td>
                                                <td class="custom5">{{ $item ?? '-' }}</td>
                                            @else
                                                <td class="custom5" colspan="2">{{ $item ?? '-' }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>        
                    @endif
                    <tr>
                        <td class="custom5" width="120">Tanggal Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">
                            @php
                                echo $tanggal_sampling;
                            @endphp
                        </td>
                    </tr>
                    <tr>
                        <td class="custom5">Periode Analisa</td>
                        <td class="custom5">:</td>
                        @php
                            $periode1 = $header->tanggal_analisa_awal ?? '';
                            $periode2 = $header->tanggal_analisa_akhir ?? '';
                        @endphp
                        <td class="custom5">
                            @if ($periode2)
                                {{ \App\Helpers\Helper::tanggal_indonesia($periode1) }} -
                                {{ \App\Helpers\Helper::tanggal_indonesia($periode2) }}
                            @elseif ($periode1)
                                {{ \App\Helpers\Helper::tanggal_indonesia($periode1) }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @if(!$$manyNoSampel)
                        <tr>
                            <td class="custom5">Keterangan</td>
                            <td class="custom5">:</td>
                            <td class="custom5">{{ $header->deskripsi_titik }}</td>
                        </tr>
                    @endif
                    {{-- <tr>
                        <td class="custom5">Titik Koordinat</td>
                        <td class="custom5">:</td>
                        <td class="custom5">
                            @php
                                // if ($detailLapangan) {
                                //     echo $detailLapangan->titik_koordinat;
                                // }
                                echo $header->titik_koordinat;
                            @endphp
                        </td>
                    </tr> --}}
                </table>

                {{-- Kondisi Lingkungan --}}
                @if (!$isManyNoSampel)
                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                        <tr>
                            <td class="custom5" width="120">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">Kondisi Lingkungan</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table>
                                    <tr>
                                        <td class="custom5" width="120">Suhu Lingkungan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">{{ $header->suhu }} °C</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Kelembapan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">{{ $header->kelembapan }} %</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tekanan Udara</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">{{ $header->tekanan_udara }} mmHg</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                @endif

                {{-- Regulasi --}}
                @if (!empty($header->regulasi))

                    @foreach (json_decode($header->regulasi) as $y)
                        <table style="padding-top: 10px;" width="100%">
                            <tr>
                                @php
                                @endphp
                                <td class="custom5" colspan="3"><strong>{{ explode('-', $y)[1] }}</strong></td>
                            </tr>
                        </table>

                        @php
                            $regulasiId = explode('-', $y)[0];
                            $regulasiName = explode('-', $y)[1] ?? '';
                            $regulasi = MasterRegulasi::find($regulasiId);
                            $tableObj = TabelRegulasi::whereJsonContains('id_regulasi', $regulasiId)->first();
                            $table = $tableObj ? $tableObj->konten : '';
                        @endphp
                        @if($table)
                        <table style="padding-top: 5px;" width="100%">
                                <tr>
                                    <td class="custom5" colspan="3">Lampiran di halaman terakhir</td>
                                </tr>
                        </table>
                        @endif
                    @endforeach

                @endif
                @php
                    $temptArrayPush = [];
                    if (!empty($detail)) {
                        foreach ($detail as $v) {
                            if (!empty($v['akr']) && !in_array($v['akr'], $temptArrayPush)) {
                                $temptArrayPush[] = $v['akr'];
                            }
                            if (!empty($v['attr']) && !in_array($v['attr'], $temptArrayPush)) {
                                $temptArrayPush[] = $v['attr'];
                            }
                        }
                    }
                @endphp
                @if (!empty($header->keterangan))
                    <table style="padding: 5px 0px 0px 10px;" width="100%">
                        @foreach (json_decode($header->keterangan) as $vx)
                            @foreach ($temptArrayPush as $symbol)
                                @if (\Illuminate\Support\Str::startsWith($vx, $symbol))
                                    <tr>
                                        <td class="custom5" colspan="3">{{ $vx }}</td>
                                    </tr>
                                    @break
                                @endif
                            @endforeach
                        @endforeach
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>
