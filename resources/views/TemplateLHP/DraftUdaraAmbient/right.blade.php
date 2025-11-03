@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;
    use App\Models\DetailLingkunganHidup;

    $detailLapangan = DetailLingkunganHidup::where('no_sampel', $header->no_sampel)->first();
@endphp
<div class="right" style="margin-top: {{ $mode == 'downloadLHPFinal' ? '0px' : '14px' }};">
    <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
        <tr>
            <td>
                <table style="border-collapse: collapse; text-align: center;" width="100%">
                    <tr>
                        <td class="custom" width="33%">No. LHP <sup><u>a</u></sup></td>
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
                                if ($header->tanggal_sampling || $header->tanggal_terima) {
                                    if ($header->tanggal_sampling == $header->tanggal_terima) {
                                        echo \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling);
                                    } else {
                                        echo \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling) . ' - ' . \App\Helpers\Helper::tanggal_indonesia($header->tanggal_terima);
                                    }
                                } else {
                                    echo '-';
                                }
                            @endphp
                        </td>
                    </tr>
                    <tr>
                        <td class="custom5">Periode Analisa</td>
                        <td class="custom5">:</td>
                        <td class="custom5">
                            @php
                                if ($header->tanggal_terima) {
                                    echo \App\Helpers\Helper::tanggal_indonesia($header->tanggal_terima) . ' - ' . \App\Helpers\Helper::tanggal_indonesia(date('Y-m-d'));
                                } else {
                                    echo '-';
                                }
                            @endphp
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
                                if ($detailLapangan) {
                                    echo $detailLapangan->titik_koordinat;
                                }
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
                                    <td class="custom5">{{ $detailLapangan->waktu_pengukuran }} WIB</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Cuaca</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $detailLapangan->cuaca }}</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Suhu Lingkungan</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $detailLapangan->suhu }} °C</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Kelembapan</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $detailLapangan->kelembapan }} %</td>
                                </tr>
                            </table>
                        </td>
                        <td width="50%">
                            <table>
                                <tr>
                                    <td class="custom5">Kecepatan Angin</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $detailLapangan->kecepatan_angin }} Km/Jam</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Arah Angin Dominan</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $detailLapangan->arah_angin }}</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Tekanan Udara</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">{{ $detailLapangan->tekanan_udara }} mmHg</td>
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
                    @php
                        // pastikan $header ada nilainya
                        $regulasi = MasterRegulasi::where('id', explode('-', $y)[0])->first();
                        $table = TabelRegulasi::whereJsonContains('id_regulasi', explode('-', $y)[0])->first();
                        if (!empty($table)) {
                            $table = $table->konten;
                        } else {
                            $table = '';
                        }
                    @endphp
                    @if ($table)
                        <table style="padding-top: 5px;" width="100%">
                            <tr>
                                <td class="custom5" colspan="3">Lampiran di halaman terakhir</td>
                            </tr>
                        </table>
                    @endif

                @endif
            </td>
        </tr>
    </table>
</div>
