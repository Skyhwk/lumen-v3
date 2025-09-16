<div class="right" style="margin-top: {{ $mode == 'downloadLHPFinal' ? '0px' : '14px' }};">
    <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
        <tr>
            <td>
                <table style="border-collapse: collapse; text-align: center;" width="100%">
                    <tr>
                        <td class="custom" width="120">No. LHP</td>
                        <td class="custom" width="120">No. SAMPEL</td>
                        <td class="custom" width="200">JENIS SAMPEL</td>
                    </tr>
                    <tr>
                        <td class="custom">{{ $header->no_lhp }}</td>
                        <td class="custom">{{ $header->no_sampel }}</td>
                        <td class="custom">{{ $header->sub_kategori }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                {{-- Informasi Pelanggan --}}
                <table style="padding: 20px 0px 0px 0px;" width="100%">
                    <tr>
                        <td colspan="3"><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span></td>
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
                        <td class="custom5" width="120" colspan="3"><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span></td>
                    </tr>
                    <tr>
                        <td class="custom5" width="120">
                            @if ($header->status_sampling == 'SD')
                                Tanggal Terima
                            @else
                                Tanggal Sampling
                            @endif
                        </td>
                        <td class="custom5" width="12">:</td>
                        @php
                            if ($header->status_sampling == 'SD') {
                                $tanggal_ = $header->tanggal_terima;
                            } else {
                                $tanggal_ = $header->tanggal_sampling;
                            }
                        @endphp
                        <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($tanggal_) }}</td>
                    </tr>
                    @php
                        if ($header->methode_sampling != null) {
                            $methode_sampling = '';
                            $dataArray = json_decode($header->methode_sampling ?? []);

                            $result = array_map(function ($item) {
                                $parts = explode(';', $item);
                                $accreditation = strpos($parts[0], 'AKREDITASI') !== false;
                                $sni = $parts[1] ?? '-';
                                return $accreditation ? "{$sni} <sup style=\"border-bottom: 1px solid;\">a</sup>" : $sni;
                            }, $dataArray);

                            foreach ($result as $index => $item) {
                                if (trim($item) == '-') {
                                    $methode_sampling .= "<span><span>-</span></span><br>";
                                } else {
                                    $methode_sampling .= "<span><span>" . ($index + 1) . ". " . $item . "</span></span><br>";
                                }
                            }

                            if ($header->status_sampling == 'SD') {
                                $methode_sampling = $dataArray[0] ?? '-';
                            }
                        } else {
                            $methode_sampling = '-';
                        }
                    @endphp

                    <tr>
                        <td class="custom5">Metode Sampling</td>
                        <td class="custom5">:</td>
                        <td class="custom5">{!! $methode_sampling !!}</td>
                    </tr>
                    <tr>
                        <td class="custom5">Keterangan</td>
                        <td class="custom5">:</td>
                        <td class="custom5"><strong>{{ $header->deskripsi_titik }}</strong></td>
                    </tr>
                    <tr>
                        <td class="custom5">Titik Koordinat</td>
                        <td class="custom5">:</td>
                        <td class="custom5">{{ $header->titik_koordinat }}</td>
                    </tr>
                </table>

                @if ($header->status_sampling != 'SD')
                    {{-- Kondisi Lingkungan --}}
                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                        <tr>
                            <td colspan="3"><span style="font-weight: bold; border-bottom: 1px solid #000">Kondisi Lingkungan Titik Sampling</span></td>
                        </tr>
                        <tr>
                            <td class="custom5" width="120">Suhu Udara</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">{{ $header->suhu_udara }} &deg;C</td>
                        </tr>
                    </table>
                @endif

                {{-- Periode Analisa --}}
                <table style="padding: 10px 0px 0px 0px;" width="100%">
                    <tr>
                        <td class="custom5" width="120">Periode Analisa</td>
                        <td class="custom5" width="12">:</td>
                        @php
                            $periode_analisa = optional($header)->periode_analisa ?? $header['periode_analisa'];

                            $periode = explode(' - ', $periode_analisa);
                            $periode1 = $periode[0] ?? '';
                            $periode2 = $periode[1] ?? '';
                        @endphp
                        <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($periode1) }} - {{ \App\Helpers\Helper::tanggal_indonesia($periode2) }}</td>
                    </tr>
                </table>

                {{-- Regulasi --}}
                @if (!empty($header->regulasi))
                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                        @foreach (json_decode($header->regulasi) as $y)
                            <tr>
                                <td class="custom5" colspan="3"><strong>{{ $y }}</strong></td>
                            </tr>
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
                        @if ($header->status_sampling == 'SD' && $methode_sampling == '******')
                            <tr>
                                <td class="custom5" colspan="3">(******) Adalah sampling tidak dilakukan Laboratorium</td>
                            </tr>
                        @endif
                    </table>
                @endif
                @php
                    $parameterUji = json_decode($header->parameter_uji, true);
                @endphp
                @if ($header->status_sampling == 'SD')
                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                        @if ((!in_array('pH', $parameterUji ?? []) && $header->suhu_air != null) || (!in_array('Suhu', $parameterUji ?? []) && $header->ph != null))
                            <tr>
                                <td class="custom5" colspan="3">Informasi Hasil Parameter In-Situ Pengukuran di Lapangan</td>
                            </tr>
                        @endif
                        @if (!in_array('pH', $parameterUji ?? []) && $header->ph != null)
                            <tr>
                                <td class="custom5" width="120">Suhu Air</td>
                                <td class="custom5" width="12">:</td>
                                <td class="custom5"><strong>{{ $header->suhu_air }} &deg;C</strong></td>
                            </tr>
                        @endif
                        @if (!in_array('Suhu', $parameterUji ?? []) && $header->suhu_air != null)
                            <tr>
                                <td class="custom5" width="120">pH</td>
                                <td class="custom5" width="12">:</td>
                                <td class="custom5"><strong>{{ $header->ph }}</strong></td>
                            </tr>
                        @endif
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>
