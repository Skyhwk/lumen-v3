@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;
    use App\Models\LhpsMicrobiologiDetail;
    $custom = LhpsMicrobiologiDetail::where('id_header', $header->id)->where('page', $page)->get();
    $detailData = is_object($custom) && method_exists($custom, 'toArray') ? $custom->toArray() : (array) $custom;

    $detailData = collect($detailData)->map(fn($r) => (array) $r);

    $sampelUnik = $detailData->pluck('no_sampel')->filter()->unique()->values();
    $paramUnik = $detailData->pluck('parameter')->filter()->unique()->values();

    $totalSampel = $sampelUnik->count();
    $totalParam = $paramUnik->count();

    $isSingleSampel = $totalSampel === 1;
    $isMultiSampelOneParam = $totalSampel > 1 && $totalParam === 1;
    $isMultiSampelMultiParam = $totalSampel > 1 && $totalParam > 1;

    $isMultiSampelMultiParam = $totalSampel > 1 && $totalParam > 1;

    $isMultipleParameter = $totalParam > 1;
    $id_reg = [];
    if(!$isMultipleParameter){
        foreach (json_decode($header->regulasi, true) as $reg) {
            $id_reg[] = explode('-', $reg)[0];
        }
        $isTable = TabelRegulasi::whereJsonContains('id_regulasi', $id_reg)
            ->where('is_active', 1)
            ->get();
        $isUsingTable = !$isTable->isEmpty();
        $isNotUsingTable = !$isUsingTable;
    }

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
                        <td class="custom">JENIS SAMPEL</td>
                    </tr>
                    <tr>
                        <td class="custom">{{ $header->no_lhp }}</td>
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

                    @if ($isMultipleParameter)

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
                                        foreach ($custom as $row) {
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
                    @elseif ($isUsingTable)
                        {{-- parameter pengujian --}}
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
                                        foreach ($custom as $row) {
                                            if ($row['parameter'] === $p) {
                                                $methode = $row['methode'];
                                                $methode_suhu = $row['methode_suhu'];
                                                $methode_kelembapan = $row['methode_kelembapan'];
                                                break;
                                            }
                                        }
                                    @endphp

                                    {{ $p }} : {{ $methode }}
                                    <br>
                                    Suhu : {{ $methode_suhu }}
                                    <br>
                                    Kelembapan : {{ $methode_kelembapan }}
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
                        {{-- KONDISI 3: banyak no sampel, banyak parameter --}}
                    @elseif ($isNotUsingTable)
                        {{-- metode sampling (array) --}}
                        {{-- <tr>
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
                        </tr> --}}

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
                                        foreach ($custom as $row) {
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

                        {{-- <tr>
                            <td class="custom5" width="120">Area Swab</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">
                                {{ $header->deskripsi_titik ?? '-' }}
                            </td>
                        </tr> --}}
                    @endif
                </table>

                {{-- =========================================
                     REGULASI  (SAMA UNTUK SEMUA KONDISI)
                ========================================== --}}
                @if (!empty($header->regulasi))
                
                    @foreach (json_decode($header->regulasi) as $i => $y)
                        @if($i === ($page - 1))
                            <table style="padding-top: 10px;" width="100%">
                                <tr>
                                    <td class="custom5" colspan="3"><strong>{{ explode('-',$y)[1] }}</strong></td>
                                </tr>
                            </table>
                        @endif
                    @endforeach
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
                @endif

                @php
                    $temptArrayPush = [];
                    if (!empty($custom)) {
                        foreach ($custom as $v) {
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