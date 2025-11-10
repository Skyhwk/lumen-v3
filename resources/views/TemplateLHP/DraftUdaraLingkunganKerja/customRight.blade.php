@php
use App\Models\TabelRegulasi;
use App\Models\MasterRegulasi;
use App\Models\DetailLingkunganKerja;
use \Carbon\Carbon;

$detailLapangan = DetailLingkunganKerja::where('no_sampel', $header->no_sampel)->first();
$tanggal_sampling = '';
if($header->status_sampling == 'S24'){
$detailLapangan = DetailLingkunganKerja::where('no_sampel', $header->no_sampel)->where('shift_pengambilan', 'L2')->first();

$tanggalAwal = DetailLingkunganKerja::where('no_sampel', $header->no_sampel)->min('created_at');

$tanggalAkhir = DetailLingkunganKerja::where('no_sampel', $header->no_sampel)->max('created_at');

$tanggalAwal = Carbon::parse($tanggalAwal)->format('Y-m-d');
$tanggalAkhir = Carbon::parse($tanggalAkhir)->format('Y-m-d');

if ($tanggalAwal || $tanggalAkhir) {
if ($tanggalAwal == $tanggalAkhir) {
$tanggal_sampling = \App\Helpers\Helper::tanggal_indonesia($tanggalAwal);
} else {
$tanggal_sampling = \App\Helpers\Helper::tanggal_indonesia($tanggalAwal) . ' - ' . \App\Helpers\Helper::tanggal_indonesia($tanggalAkhir);
}
} else {
$tanggal_sampling = '-';
}
} else {
if ($header->tanggal_sampling || $header->tanggal_terima) {
if ($header->tanggal_sampling == $header->tanggal_terima) {
$tanggal_sampling = \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling);
} else {
$tanggal_sampling = \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling) . ' - ' . \App\Helpers\Helper::tanggal_indonesia($header->tanggal_terima);
}
} else {
$tanggal_sampling = '-';
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
                        <td class="custom5">Periode Analisa</td>
                        <td class="custom5">:</td>
                        @php
                        $periode_analisa = optional($header)->periode_analisa ?? $header['periode_analisa'];
                        $periode = explode(' - ', $periode_analisa);
                        $periode1 = $periode[0] ?? '';
                        $periode2 = $periode[1] ?? '';
                        @endphp
                        <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($periode1) }} - {{ \App\Helpers\Helper::tanggal_indonesia($periode2) }}</td>
                    </tr>
                    <tr>
                        <td class="custom5">Keterangan</td>
                        <td class="custom5">:</td>
                        <td class="custom5">{{ $header->deskripsi_titik }}</td>
                    </tr>
                    {{-- <tr>
                        <td class="custom5">Titik Koordinat</td>
                        <td class="custom5">:</td>
                        <td class="custom5">
                            @php
                                echo $header->titik_koordinat;
                            @endphp
                        </td>
                    </tr> --}}
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
                                    <td class="custom5">Suhu Lingkungan</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $header->suhu }} °C</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Kelembapan</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $header->kelembapan }} %</td>
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