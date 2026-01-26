@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;

    $detailData = is_object($detail) && method_exists($detail, 'toArray') ? $detail->toArray() : (array) $detail;

    $detailData = collect($detailData)->map(fn($r) => (array) $r);

    if (!empty($header->metode_sampling)) {
        $metodeSampling = is_array($header->metode_sampling)
            ? $header->metode_sampling
            : json_decode($header->metode_sampling, true) ?? [];
    } else {
        $metodeSampling = [];
    }
    if ($header->tanggal_sampling_awal || $header->tanggal_sampling_akhir) {
        if ($header->tanggal_sampling_awal == $header->tanggal_sampling_akhir) {
            $tanggalSampling = \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling_awal);
        } elseif ($header->tanggal_sampling_akhir == null) {
            $tanggalSampling = \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling_awal);
        } else {
            $tanggalSampling =
                \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling_awal) .
                ' - ' .
                \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling_akhir);
        }
    } elseif ($header->tanggal_sampling || $header->tanggal_terima) {
        if ($header->tanggal_sampling == $header->tanggal_terima) {
            $tanggalSampling = \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling);
        } elseif ($header->tanggal_terima != null) {
            $tanggalSampling =
                \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling) .
                ' - ' .
                \App\Helpers\Helper::tanggal_indonesia($header->tanggal_terima);
        } else {
            $tanggalSampling = \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling);
        }
    } else {
        $tanggalSampling = '-';
    }

    $periode1 = $header->tanggal_analisa_awal ?? '';
    $periode2 = $header->tanggal_analisa_akhir ?? '';

@endphp

<div class="right" style="margin-top: {{ $mode == 'downloadLHPFinal' ? '0px' : '14px' }};">
    <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
        {{-- =========================================
             BARIS ATAS: NO LHP, JENIS SAMPEL, PARAMETER UJI
        ========================================== --}}
        <tr>
            <td>
                <table style="border-collapse: collapse; text-align: center;" width="100%">
                    <tr>
                        <td class="custom">No. LHP</td>
                        <td class="custom" width="33%">No. SAMPEL</td>
                        <td class="custom">JENIS SAMPEL</td>
                    </tr>
                    <tr>
                        <td class="custom">{{ $header->no_lhp }}</td>
                        <td class="custom" width="33%">{{ $header->no_sampel }}</td>
                        <td class="custom">Lingkungan Kerja</td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- =========================================
             INFORMASI PELANGGAN (SAMA UNTUK SEMUA KONDISI)
        ========================================== --}}
        <tr>
            <td>
                <table style="padding-top: 20px;" width="100%">
                    <tr>
                        <td>
                            <span style="font-weight: bold; border-bottom: 1px solid #000">
                                Informasi Pelanggan
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="custom5" width="120">Nama Pelanggan</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ $header->nama_pelanggan }}</td>
                    </tr>
                </table>

                {{-- Alamat Sampling --}}
                <table style="padding-top: 10px;" width="100%">
                    <tr>
                        <td class="custom5" width="120">Alamat / Lokasi Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ $header->alamat_sampling }}</td>
                    </tr>
                </table>

                {{-- =========================================
                     INFORMASI SAMPLING – PER KONDISI
                ========================================== --}}
                <table style="padding-top: 10px;" width="100%">
                    <tr>
                        <td class="custom5" width="120" colspan="3">
                            <span style="font-weight: bold; border-bottom: 1px solid #000">
                                Informasi Sampling
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td class="custom5" width="120">Tanggal Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ $tanggalSampling ?? '-' }}</td>
                    </tr>

                    <tr>
                        <td class="custom5" width="120">Periode Analisa</td>
                        <td class="custom5" width="12">:</td>
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
                        <td class="custom5" width="120">Keterangan</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">
                            {{ $header->deskripsi_titik ?? '-' }}
                        </td>
                    </tr>
                </table>

                {{-- =========================================
                     REGULASI  (SAMA UNTUK SEMUA KONDISI)
                ========================================== --}}
                @if (!empty($header->regulasi))
                    @foreach (json_decode($header->regulasi) as $i => $y)
                        @if ($i === 0)
                            @php
                                // PERBAIKAN: Gunakan explode dengan limit 2
                                $parts = explode('-', $y, 2);
                                $regulasiId = $parts[0] ?? '';
                                $regulasiName = $parts[1] ?? '';
                            @endphp

                            <table style="padding-top: 10px;" width="100%">
                                <tr>
                                    <td class="custom5" colspan="3"><strong>{{ $regulasiName }}</strong></td>
                                </tr>
                            </table>
                        @endif
                    @endforeach

                    @php
                        // PERBAIKAN: Gunakan explode dengan limit 2
                        $parts = explode('-', $y, 2);
                        $regulasiId = $parts[0] ?? '';
                        $regulasiName = $parts[1] ?? '';

                        $regulasi = MasterRegulasi::find($regulasiId);
                        $tableObj = TabelRegulasi::whereJsonContains('id_regulasi', $regulasiId)->first();
                        $table = $tableObj ? $tableObj->konten : '';
                    @endphp

                    @if ($table)
                        <table style="padding-top: 5px;" width="100%">
                            <tr>
                                <td class="custom5" colspan="3">Lampiran di halaman terakhir</td>
                            </tr>
                        </table>
                    @endif
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
                    <table style="padding: 5px 0px 0px 3px;" width="100%">
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
