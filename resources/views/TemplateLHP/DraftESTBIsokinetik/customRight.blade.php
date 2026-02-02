@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;
    use App\Models\DataLapanganEmisiCerobong;
    use App\Models\WsValueEmisiCerobong;
    use App\Models\EmisiCerobongHeader;
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    $wsvalue = WsValueEmisiCerobong::where('no_sampel', $header->no_sampel)->get();
    $dataLapangan = DataLapanganEmisiCerobong::where('no_sampel', $header->no_sampel)->first();
    $emisiCerobongHeader = EmisiCerobongHeader::with('ws_value')->where('no_sampel', $header->no_sampel)->where('parameter', 'Velocity')->first();
    

    $keterangan_koreksi = [];
    foreach ($wsvalue as $k => $v) {
        if ($v->keterangan_koreksi != null && $v->keterangan_koreksi != '') {
            foreach (json_decode($v->keterangan_koreksi) as $kk => $vv) {
                if (in_array($vv, $keterangan_koreksi) == false) {
                    $keterangan_koreksi[] = $vv;
                }
            }
        }
    }
    
    $laju_velocity = '-';
    if ($emisiCerobongHeader) {
        $laju_velocity = round($emisiCerobongHeader->ws_value->C9, 2);
    }
    

@endphp

<div class="right" style="margin-top: {{ $mode == 'downloadLHPFinal' ? '0px' : '14px' }};">
    <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
        <tr>
            <td>
                <table style="border-collapse: collapse; text-align: center;" width="100%">
                    <tr>
                        <td class="custom" width="120">No. LHP {!! $showKan ? '<sup><u>a</u></sup>' : '' !!}</td>
                        <td class="custom" width="120">No. SAMPEL</td>
                        <td class="custom" width="200">JENIS SAMPEL</td>
                    </tr>
                    <tr>
                        <td class="custom">{{ $header->no_lhp }}</td>
                        <td class="custom">{{ $header->no_sampel }}</td>
                        <td class="custom">EMISI SUMBER TIDAK BERGERAK</td>
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
                        <td class="custom5">{{ $header->alamat_sampling ?? '-' }}</td>
                    </tr>
                </table>

                {{-- Informasi Sampling --}}
                @php
                    $methode_sampling = $header->metode_sampling != null ? json_decode($header->metode_sampling) : [];
                    $period = explode(' - ', $header->periode_analisa);
                    $period = array_filter($period);
                    $period1 = '';
                    $period2 = '';
                    if (!empty($period)) {
                        $period1 = \App\Helpers\Helper::tanggal_indonesia($period[0]);
                        $period2 = \App\Helpers\Helper::tanggal_indonesia($period[1]);
                    }

                    $parame = str_replace(['[', ']', '"'], '', $header->parameter_uji);
                @endphp
                <table style="padding: 10px 0px 0px 0px;" width="100%">
                    <tr>
                        <td class="custom5" width="120"><span
                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span></td>
                    </tr>
                    {{-- <tr>
                        <td class="custom5">Kategori</td>
                        <td class="custom5">:</td>
                        <td class="custom5">{{ $header->sub_kategori }}</td>
                    </tr> --}}
                    {{-- <tr>
                        <td class="custom5">Parameter</td>
                        <td class="custom5">:</td>
                        <td class="custom5">{{ $parame }}</td>
                    </tr> --}}
                    {{-- @if (count($methode_sampling) > 0)
                        @php $i = 1; @endphp
                        @foreach ($methode_sampling as $key => $value)
                            @php
                                $akre = explode(';', $value)[0] == 'AKREDITASI' ? ' <sup style="border-bottom: 1px solid;">a</sup>' : '';
                                $metode = implode(' - ', array_slice(explode(';', $value), 1, 2));
                            @endphp
                            <tr>
                                <td class="custom5">{{ $key == 0 ? 'Metode Sampling' : '' }}</td>
                                <td class="custom5">{{ $key == 0 ? ':' : '' }}</td>
                                <td class="custom5">{{ $i . '. ' . $metode . $akre }}</td>
                            </tr>
                            @php $i++; @endphp
                        @endforeach
                    @else
                        <tr>
                            <td class="custom5">Metode Sampling</td>
                            <td class="custom5">:</td>
                            <td class="custom5">-</td>
                        </tr>
                    @endif --}}
                    <tr>
                        <td class="custom5" width="120">Tanggal Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($header->tanggal_tugas) }}</td>
                    </tr>
                    <tr>
                        <td class="custom5">Periode Analisa</td>
                        <td class="custom5">:</td>
                        <td class="custom5">{{ $period1 }} - {{ $period2 }}</td>
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
                    @if ($laju_velocity != '-')
                        <tr>
                            <td class="custom5">Laju Velocity</td>
                            <td class="custom5">:</td>
                            <td class="custom5">{{ $laju_velocity }} m/s</td>
                        </tr>
                    @endif
                </table>

                {{-- Regulasi --}}
                @php
                    $bintang = '';
                @endphp
                {{-- @if (!empty($header->regulasi_custom))
                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                        @foreach (json_decode($header->regulasi_custom) as $t => $y)
                            <tr>
                                <td class="custom5" colspan="3">{{ $bintang }}{{ $y }}</td>
                            </tr>
                            @php
                                $bintang .= '*';
                            @endphp
                        @endforeach
                    </table>
                @endif --}}

                @if ($header->regulasi_custom != null)
                @php
                    $customRegulasi = json_decode($header->regulasi_custom, true);
                    $pages = collect($customRegulasi)->pluck('page')->sort()->values();

                    $secondLast = $pages[$pages->count() - 2];
                    $last       = $pages[$pages->count() - 1];

                    if ($page > $secondLast && $page < $last) {
                        // skip render
                        return;
                    }
                @endphp
                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                        @foreach (json_decode($header->regulasi_custom) as $key => $y)
                            @if ($y->page == $page && !in_array($page, [$last, $secondLast]))
                                <tr>
                                    <td class="custom5" colspan="3"><strong>{{ $y->regulasi }}</strong></td>
                                </tr>
                            @endif
                        @endforeach
                    </table>
                @endif
                @if (!empty($keterangan_koreksi))
                    @php
                        // Bersihkan nilai kosong & spasi berlebih
                        $items = array_map('trim', array_filter($keterangan_koreksi));

                        // Inisialisasi variabel hasil
                        $bagian_standar = '';
                        $bagian_o2 = '';
                        $bagian_kering = '';
                        $bagian_semua = '';
                        $bagian_angka = '';
                        $bagian_khusus = '';

                        // Deteksi bagian berdasarkan isi teks
                        foreach ($items as $v) {
                            if (Str::contains(strtolower($v), 'standar')) {
                                $bagian_standar =
                                    'Volume Gas diukur dalam keadaan standar (25°C dan 1 tekanan atmosfer)';
                            } elseif (Str::contains(strtolower($v), 'o2')) {
                                $bagian_o2 = 'dengan O₂ terkoreksi';
                            } elseif (Str::contains(strtolower($v), 'kering')) {
                                $bagian_kering = 'dalam keadaan kering';
                            } elseif (Str::contains(strtolower($v), 'parameter')) {
                                $bagian_semua = 'untuk semua parameter';
                            } elseif (Str::contains(strtolower($v), 'angka')) {
                                // Cari angka persen (misalnya 6%, 15%, dst)
                                if (preg_match('/(\d+(?:[\.,]\d+)?)\s*%/', $v, $matches)) {
                                    $bagian_angka = 'sebesar ' . $matches[1] . '%';
                                } else {
                                    // fallback jika tidak ada angka
                                    $bagian_angka = 'sebesar 15%';
                                }
                            } elseif (Str::contains(strtolower($v), 'partikulat')) {
                                // Tambahan: khusus untuk partikulat
                                $bagian_khusus = 'Khusus untuk konsentrasi partikulat';
                            }
                        }

                        // Gabungkan secara berurutan
                        $gabungKeterangan = trim(
                            implode(' ', array_filter([
                                $bagian_standar,
                                $bagian_o2,
                                $bagian_angka,
                                $bagian_kering,
                                $bagian_semua,
                                $bagian_khusus, // diletakkan paling akhir
                            ]))
                        );

                        // Tambahkan titik di akhir jika belum ada
                        if ($gabungKeterangan && !preg_match('/[.!?]$/', $gabungKeterangan)) {
                            $gabungKeterangan .= '.';
                        }
                    @endphp

                    @if ($gabungKeterangan)
                        <table style="padding: 10px 0px 0px 0px;" width="100%">
                            <tr>
                                <td class="custom5" colspan="3">- {{ $gabungKeterangan }}</td>
                            </tr>
                        </table>
                    @endif
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
            </td>
        </tr>
    </table>
</div>
