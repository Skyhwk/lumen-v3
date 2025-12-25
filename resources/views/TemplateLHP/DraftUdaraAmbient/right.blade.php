@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;
    use App\Models\DetailLingkunganHidup;
    use Carbon\Carbon;

    // $detailLapangan = DetailLingkunganHidup::where('no_sampel', $header->no_sampel)->first();
    // $tanggal_sampling = '';
    // if($header->status_sampling == 'S24'){
    //     $detailLapangan = DetailLingkunganHidup::where('no_sampel', $header->no_sampel)->where('shift_pengambilan', 'L2')->first();

    //     $tanggalAwal = $header->tanggal_sampling;

    //     $tanggalAkhir = Carbon::parse($tanggalAwal)->addDay()->format('Y-m-d');

    //     $tanggalAwal = Carbon::parse($tanggalAwal)->format('Y-m-d');

    //     if ($tanggalAwal || $tanggalAkhir) {
    //         if ($tanggalAwal == $tanggalAkhir) {
    //             $tanggal_sampling = \App\Helpers\Helper::tanggal_indonesia($tanggalAwal);
    //         } else {
    //             $tanggal_sampling = \App\Helpers\Helper::tanggal_indonesia($tanggalAwal) . ' - ' . \App\Helpers\Helper::tanggal_indonesia($tanggalAkhir);
    //         }
    //     } else {
    //         $tanggal_sampling = '-';
    //     }
    // } else {
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
                        <td class="custom" width="33%">No. SAMPEL</td>
                        <td class="custom" width="33%">JENIS SAMPEL</td>
                    </tr>
                    <tr>
                        <td class="custom">{{ $header->no_lhp }}</td>
                        <td class="custom">{{ $header->no_sampel }}</td>
                        <td class="custom">Udara Ambient</td>
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
                        <td class="custom5" width="120">Periode Analisa</td>
                        <td class="custom5" width="12">:</td>
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
                    <tr>
                        <td class="custom5">Keterangan</td>
                        <td class="custom5">:</td>
                        <td class="custom5"><strong>{{ $header->deskripsi_titik }}</strong></td>
                    </tr>
                    <tr>
                        <td class="custom5">Titik Koordinat</td>
                        <td class="custom5">:</td>
                        <td class="custom5">
                            @php
                                // if ($detailLapangan) {
                                echo $header->titik_koordinat;
                                // }
                            @endphp
                        </td>
                    </tr>
                </table>

                {{-- Kondisi Lingkungan --}}
                <table style="padding: 10px 0px 0px 0px;" width="100%">
                    <tr>
                        <td class="custom5" width="120">
                            <span style="font-weight: bold; border-bottom: 1px solid #000">Kondisi Lingkungan</span>
                        </td>
                    </tr>
                    <tr>
                        <td width="50%">
                            <table>
                                <tr>
                                    <td class="custom5" width="120">Jam Pengambilan</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">{{ $header->waktu_pengukuran }} WIB</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Cuaca</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $header->cuaca }}</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Suhu Lingkungan</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $header->suhu }} °C</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Kelembapan</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $header->kelembapan }} %</td>
                                </tr>
                            </table>
                        </td>
                        <td width="50%">
                            <table>
                                <tr>
                                    <td class="custom5">Kecepatan Angin</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $header->kec_angin }} Km/Jam</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Arah Angin Dominan</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $header->arah_angin }}</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Tekanan Udara</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $header->tekanan_udara }} mmHg</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                {{-- Regulasi --}}
                <!-- @php
                    $bintang = '**';
                @endphp
                @if (!empty($header->regulasi))
<table style="padding: 10px 0px 0px 0px;" width="100%">
                        @foreach (json_decode($header->regulasi) as $t => $y)
<tr>
                                <td class="custom5" colspan="3">{{ $bintang }}{{ $y }}</td>
                            </tr>
                            @php
                                $bintang .= '*';
                            @endphp
@endforeach
                    </table>
@endif

                {{-- Keterangan --}}
                @if (!empty($header->keterangan))
<table style="padding: 5px 0px 0px 10px;" width="100%">
                        @foreach (json_decode($header->keterangan) as $t => $y)
<tr>
                                <td class="custom5" colspan="3">{{ $y }}</td>
                            </tr>
@endforeach
                    </table>
@endif -->
                @if (!empty($header->regulasi))

                    @foreach (json_decode($header->regulasi) as $y)
                        <table style="padding-top: 10px;" width="100%">
                            <tr>
                                @php
                                @endphp
                                <td class="custom5" colspan="3"><strong>{{ explode('-', $y)[1] }}</strong></td>
                            </tr>
                        </table>
                    @endforeach

                @endif
                {{-- Keterangan --}}
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
                @php
                    $isPager = false;

                    foreach ($detail as $v) {
                        if ($v['hasil_uji'] === '##') {
                            $isPager = true;
                            break;
                        }
                    }
                @endphp

                @if($isPager)
                    <table style="padding: 5px 0px 0px 10px;" width="100%">
                        <tr>
                            <td class="custom5" colspan="3">(##) Hasil analisa dalam proses di Laboratorium</td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>
