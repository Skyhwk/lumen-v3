@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;
    use App\Models\DetailLingkunganHidup;
    use App\Models\LhpsLingCustom;
    use Carbon\Carbon;

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

    $regulasiThisPage = null;
    $isPagi = false;
    $isSiang = false;
    $isSore = false;
    $isMalam = false;

    $waktu_pengukuran = $header->waktu_pengukuran;
    $cuaca = $header->cuaca;
    $suhu = $header->suhu;
    $kelembapan = $header->kelembapan;

    $kecepatan_angin = $header->kec_angin;
    $arah_angin = $header->arah_angin;
    $tekanan_udara = $header->tekanan_udara;

    if ($header->regulasi_custom != null){
        foreach (json_decode($header->regulasi_custom) as $key => $y) {
            if ($y->page == $page) {
                $regulasiThisPage = $y->regulasi;
            }
        }
    }
    
    if (stripos($regulasiThisPage, "pagi") !== false) $isPagi = true;
    if (stripos($regulasiThisPage, "siang") !== false) $isSiang = true;
    if (stripos($regulasiThisPage, "sore") !== false) $isSore = true;
    if (stripos($regulasiThisPage, "malam") !== false) $isMalam = true;

    $cekDetail = LhpsLingCustom::where('id_header', $header->id)->where('page', $page)->pluck('parameter_lab')->toArray();
    
    if(in_array('NO2 (24 Jam)', $cekDetail) || in_array('SO2 (24 Jam)', $cekDetail)) {
        $shift = $isPagi ? 'L1' : ($isSiang ? 'L2' : ($isSore ? 'L3' : ($isMalam ? 'L4' : null)));
        if ($shift) {
            $cekDataLapangan = DetailLingkunganHidup::where('no_sampel', $header->no_sampel)->where('shift_pengambilan', $shift)->whereIn('parameter', ['NO2 (24 Jam)', 'SO2 (24 Jam)'])->first();
            
            $waktu_pengukuran = $cekDataLapangan->waktu_pengukuran;
            $cuaca = $cekDataLapangan->cuaca;
            $suhu = $cekDataLapangan->suhu;
            $kelembapan = $cekDataLapangan->kelembapan;

            $kecepatan_angin = ($cekDataLapangan->kecepatan_angin !== null && $cekDataLapangan->kecepatan_angin !== "") 
                ? str_replace(',', '', number_format($cekDataLapangan->kecepatan_angin * 3.6, 2)) 
                : '-';
            $arah_angin = $cekDataLapangan->arah_angin;
            $tekanan_udara = $cekDataLapangan->tekanan_udara;
        }
    }

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
                        <td colspan="3">
                            <span style="font-weight: bold; border-bottom: 1px solid #000">InformasiPelanggan</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="custom5" width="120">Nama Pelanggan</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5"><strong>{!! html_entity_decode($header->nama_pelanggan) !!}</strong></td>
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
                        <td class="custom5">{{ $header->deskripsi_titik }}</td>
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
                                    <td class="custom5">{{ $waktu_pengukuran }} WIB</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Cuaca</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $cuaca }}</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Suhu Lingkungan</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $suhu }} °C</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Kelembapan</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $kelembapan }} %</td>
                                </tr>
                            </table>
                        </td>
                        <td width="50%">
                            <table>
                                <tr>
                                    <td class="custom5">Kecepatan Angin</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $kecepatan_angin }} Km/Jam</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Arah Angin Dominan</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $arah_angin }}</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Tekanan Udara</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $tekanan_udara }} mmHg</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                @if ($header->regulasi_custom != null)
                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                        @foreach (json_decode($header->regulasi_custom) as $key => $y)
                            @if ($y->page == $page)
                                <tr>
                                    <td class="custom5" colspan="3"><strong>{{ $y->regulasi }}</strong></td>
                                </tr>
                            @endif
                        @endforeach
                    </table>
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
                            <tr>
                                <td class="custom5" colspan="3">(##) Hasil analisa dalam proses di Laboratorium</td>
                            </tr>
                        @endif
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>
