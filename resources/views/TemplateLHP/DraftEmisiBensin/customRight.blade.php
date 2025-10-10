<div class="right" style="margin-top: {{ $mode == 'downloadLHPFinal' ? '0px' : '14px' }};">
    <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
        <tr>
            <td>
                <table style="border-collapse: collapse; text-align: center;" width="100%">
                    <tr>
                        <td class="custom" width="120">No. LHP</td>
                        <td class="custom" width="200">JENIS SAMPEL</td>
                    </tr>
                    <tr>
                        <td class="custom">{{ $header->no_lhp }}</td>
                        <td class="custom">EMISI SUMBER BERGERAK</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                {{-- Informasi Pelanggan --}}
                <table style="padding: 20px 0px 0px 0px;" width="100%">
                    <tr>
                        <td><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span></td>
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
                  

                    $parame = str_replace(['[', ']', '"'], '', $header->parameter_uji);
                @endphp
                <table style="padding: 10px 0px 0px 0px;" width="100%">
                    <tr>
                        <td class="custom5" width="120"><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span></td>
                    </tr>
                    <tr>
                        <td class="custom5" width="120">Kategori</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ $header->sub_kategori }}</td>
                    </tr>
                    <!-- <tr>
                        <td class="custom5">Parameter</td>
                        <td class="custom5">:</td>
                        <td class="custom5">{{ $parame }}</td>
                    </tr> -->
                    @if (count($methode_sampling) > 0)
                        @php $i = 1; @endphp
                        @foreach ($methode_sampling as $key => $value)
                            @php
                                $akre = explode(';', $value)[0] == 'AKREDITASI' ? ' <sup style="border-bottom: 1px solid;">a</sup>' : '';
                                $metode = implode(' - ', array_slice(explode(';', $value), 1, 2));
                            @endphp
                            <tr>
                                <td class="custom5" width="120">{{ $key == 0 ? 'Metode Sampling' : '' }}</td>
                                <td class="custom5" width="12">{{ $key == 0 ? ':' : '' }}</td>
                                <td class="custom5">{{ $i . '. ' . $metode . $akre }}</td>
                            </tr>
                            @php $i++; @endphp
                        @endforeach
                    @else
                        <tr>
                            <td class="custom5" width="120">Metode Sampling</td>
                            <td class="custom5" width="12">:</td>
                            <td class="custom5">-</td>
                        </tr>
                    @endif
                    <!-- <tr>
                        <td class="custom5" width="120">Tanggal Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling) }}</td>
                    </tr> -->
                  
                </table>

                {{-- Regulasi --}}
                @php
                    $bintang = '**';
                @endphp
                @if ($header->regulasi_custom != null)
                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                        @foreach (json_decode($header->regulasi_custom) as $key => $y)
                            @if ($y->page == $page)
                                <tr>
                                    <td class="custom5" colspan="3">{{ $bintang }}{{ $y->regulasi }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>
