<div class="right" style="margin-top: {{ $mode == 'downloadLHPFinal' ? '0px' : '14px' }};">
<!-- <div class="right"> -->
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
                        <td class="custom">
                            @php
                                $aliases = [
                                    'Air Bersih' => 'Air untuk Keperluan Higiene Sanitasi',
                                    'Air Limbah Domestik' => 'Air Limbah',
                                    'Air Limbah Industri' => 'Air Limbah',
                                    'Air Permukaan' => 'Air Sungai',
                                    'Air Kolam Renang' => 'Air Kolam Renang',
                                    'Air Higiene Sanitasi' => 'Air untuk Keperluan Higiene Sanitasi',
                                    'Air Khusus' => 'Air Reverse Osmosis',
                                    'Air Limbah Terintegrasi' => 'Air Limbah',
                                ];
                                
                                if (strpos($header->sub_kategori, '-') !== false) {
                                    $categoryName = explode('-', $header->sub_kategori)[1];
                                } else {
                                    $categoryName = $header->sub_kategori;
                                }
                                if (array_key_exists($categoryName, $aliases)) {
                                    $categoryName = $aliases[$categoryName];
                                }

                                echo $categoryName;
                            @endphp
                        </td>
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
                            Parameter Pengujian
                        </td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ data_get($value, 'parameter') }}</td>
                    </tr>
                    <tr>
                        <td class="custom5">Metode Pengujian</td>
                        <td class="custom5">:</td>
                        <td class="custom5">{{ data_get($value, 'metode_sampling') }}</td>
                    </tr>
                    <tr>
                        <td class="custom5">Keterangan</td>
                        <td class="custom5">:</td>
                        <td class="custom5"><strong>{{ data_get($header, 'deskripsi_titik') }}</strong></td>
                    </tr>
                    <tr>
                        <td class="custom5" width="120">Tanggal Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($header->tanggal_terima) }}</td>
                    </tr>
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
                    <tr>
                        <td class="custom5">Titik Koordinat</td>
                        <td class="custom5">:</td>
                        <td class="custom5">{{ $header->titik_koordinat }}</td>
                    </tr>
                </table>
                @if(!$is_custom)
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
                @else
                    @if ($header->regulasi_custom!=null)
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
                @endif
                <table>
                    <tr>
                        <td class="custom5">Kesimpulan:</td>
                    </tr>
                    <tr>
                        <td class="custom5 border">{{data_get($value, 'kesimpulan')}}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
