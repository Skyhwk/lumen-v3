@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;
    use App\Models\LhpsMicrobiologiDetail;

    $custom = LhpsMicrobiologiDetail::where('id_header', $header->id)->where('page', $page)->get();

    $detailData = is_object($custom) && method_exists($custom, 'toArray') ? $custom->toArray() : (array) $custom;

    $detailData = collect($detailData)->map(fn($r) => (array) $r);

    $paramUnik = $detailData->pluck('parameter')->filter()->unique()->values();

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
                </table>

                {{-- =========================================
                     REGULASI  (SAMA UNTUK SEMUA KONDISI)
                ========================================== --}}
                @if (!empty($header->regulasi))

                    @foreach (json_decode($header->regulasi) as $i => $y)
                        @if ($i === $page - 1)
                            <table style="padding-top: 10px;" width="100%">
                                <tr>
                                    <td class="custom5" colspan="3"><strong>{{ explode('-', $y)[1] }}</strong></td>
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
