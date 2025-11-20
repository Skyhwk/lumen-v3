@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;

    $detailData = is_object($detail) && method_exists($detail, 'toArray') ? $detail->toArray() : (array) $detail;

    $detailData = collect($detailData)->map(fn($r) => (array) $r);

    $sampelUnik = $detailData->pluck('no_sampel')->filter()->unique()->values();
    $paramUnik = $detailData->pluck('parameter')->filter()->unique()->values();

    $totalSampel = $sampelUnik->count();
    $totalParam = $paramUnik->count();

    $isSingleSampel = $totalSampel === 1;
    $isMultiSampelOneParam = $totalSampel > 1 && $totalParam === 1;
    $isMultiSampelMultiParam = $totalSampel > 1 && $totalParam > 1;

    $periodeAnalisa = $header->periode_analisa ?? null;

    // Area swab: aku asumsikan dar keterangan (bisa dimodif kalau ada field khusus)
    $areaSwabUnik = $detailData->pluck('keterangan')->filter()->unique()->values();

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
                        @if ($isSingleSampel)
                            <td class="custom" width="33%">No. SAMPEL</td>
                        @endif
                        <td class="custom">JENIS SAMPEL</td>
                    </tr>
                    <tr>
                        <td class="custom">{{ $header->no_lhp }}</td>
                        @if ($isSingleSampel)
                            <td class="custom" width="33%">{{ $header->no_sampel }}</td>
                        @endif
                        <td class="custom">Swab Lingkungan Kerja</td>
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

                    @if ($isSingleSampel)
                        <tr>
                            <td class="custom5" width="120">Tanggal Sampling</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">{{ $tanggalSampling ?? '-' }}</td>
                        </tr>

                        {{-- keterangan (bisa gabung semua area/keterangan) --}}
                        <tr>
                            <td class="custom5" width="120">Keterangan</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">{{ $header->deskripsi_titik ?? '-' }}</td>
                        </tr>

                        {{-- area swab (kalau mau dipisah) --}}
                        <tr>
                            <td class="custom5" width="120">Area Swab</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">
                                {{ $header->deskripsi_titik ?? '-' }}
                            </td>
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

                        {{-- KONDISI 2: banyak no sampel, 1 parameter --}}
                    @elseif ($isMultiSampelOneParam)
                        {{-- parameter pengujian --}}
                        <tr>
                            <td class="custom5" width="120">Parameter Pengujian</td>
                            <td class="custom5" width="12">:</td>
                            @foreach ($paramUnik as $idx => $p)
                                @if ($idx > 0)
                                    <br>
                                @endif

                                @php
                                    $methode = '-';
                                    foreach ($detail as $row) {
                                        if ($row['parameter'] === $p) {
                                            $akr = $row['akr'];
                                            break;
                                        }
                                    }
                                @endphp

                                <td class="custom5"><sup>{{ $akr }}</sup>&nbsp;{{ $p }}</td>
                            @endforeach
                        </tr>

                        {{-- spesifikasi metode (hardcode / dari header kalau ada) --}}
                        <tr>
                            <td class="custom5" width="120">Spesifikasi Metode</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">
                                @foreach ($paramUnik as $idx => $p)
                                    @if ($idx > 0)
                                        <br>
                                    @endif

                                    @php
                                        $methode = '-';
                                        foreach ($detail as $row) {
                                            if ($row['parameter'] === $p) {
                                                $methode = $row['methode'];
                                                break;
                                            }
                                        }
                                    @endphp

                                    {{ $methode }}
                                @endforeach
                            </td>
                        </tr>
                        
                        {{-- periode analisa --}}
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

                        {{-- area swab --}}
                        <tr>
                            <td class="custom5" width="120">Area Swab</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">
                                {{ $header->deskripsi_titik ?? '-' }}
                            </td>
                        </tr>

                        {{-- KONDISI 3: banyak no sampel, banyak parameter --}}
                    @elseif ($isMultiSampelMultiParam)
                        {{-- metode sampling (array) --}}
                        <tr>
                            <td class="custom5" width="120">Metode Sampling</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">
                                <table width="100%"
                                    style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                                    @forelse ($metodeSampling as $index => $item)
                                        <tr>
                                            @if (count($metodeSampling) > 1)
                                                <td class="custom5" width="20">{{ $index + 1 }}.</td>
                                                <td class="custom5">{{ $item ?? '-' }}</td>
                                            @else
                                                <td class="custom5" colspan="2">{{ $item ?? '-' }}</td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="custom5" colspan="2">-</td>
                                        </tr>
                                    @endforelse
                                </table>
                            </td>
                        </tr>

                        {{-- spesifikasi metode per parameter --}}
                        <tr>
                            <td class="custom5" width="120">Spesifikasi Metode</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">
                                @foreach ($paramUnik as $idx => $p)
                                    @if ($idx > 0)
                                        <br>
                                    @endif

                                    @php
                                        $methode = '-';
                                        foreach ($detail as $row) {
                                            if ($row['parameter'] === $p) {
                                                $methode = $row['methode'];
                                                break;
                                            }
                                        }
                                    @endphp

                                    {{ $p }} : {{ $methode }}
                                @endforeach
                            </td>
                        </tr>

                        {{-- periode analisa --}}
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
                            <td class="custom5" width="120">Area Swab</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">
                                {{ $header->deskripsi_titik ?? '-' }}
                            </td>
                        </tr>
                    @endif
                </table>

                {{-- =========================================
                     REGULASI  (SAMA UNTUK SEMUA KONDISI)
                ========================================== --}}
                @if (!empty($header->regulasi))
                    @php
                        $regulasiList = json_decode($header->regulasi, true) ?? [];
                    @endphp

                    @foreach ($regulasiList as $regItem)
                        @php
                            $parts = explode('-', $regItem, 2);
                            $regulasiId = $parts[0] ?? null;
                            $regulasiName = $parts[1] ?? '';
                        @endphp

                        <table style="padding: 10px 0px 0px 0px;" width="100%">
                            <tr>
                                <td class="custom5" colspan="3">{{ $regulasiName }}</td>
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
