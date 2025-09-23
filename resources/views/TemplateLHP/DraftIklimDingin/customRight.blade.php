



<div class="right" style="margin-top: {{ $mode == 'downloadLHPFinal' ? '0px' : '14px' }};">
    <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
        <tr>
            <td>
                <table style="border-collapse: collapse; text-align: center;" width="100%">
                    <tr>
                        <td class="custom" width="200">No. LHP <sup style="font-size: 8px;"><u>a</u></sup></td>
                        <td class="custom" width="240">JENIS SAMPEL</td>
                    </tr>
                    <tr>
                        <td class="custom">{{ $header->no_lhp }}</td>
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
                        <td class="custom5">{{ $header->alamat_sampling }}</td>
                    </tr>
                </table>

                {{-- Informasi Sampling --}}
                <table style="padding: 10px 0px 0px 0px;" width="100%">
                    <tr>
                        <td class="custom5" width="120"><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span></td>
                    </tr> 

                    <tr>
                     <td class="custom5">Metode Sampling</td>
                        <td class="custom5">:</td>
                        <td class="custom5">
                            <table width="100%" style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                                @if(!empty($header->metode_sampling))
                                    @foreach($header->metode_sampling as $index => $item)
                                        <tr>
                                            @if (count($header->metode_sampling) > 1)
                                                <td class="custom5" width="20">{{ $index + 1 }}.</td>
                                                <td class="custom5">{{ $item ?? '-' }}</td>
                                            @else
                                                <td class="custom5" colspan="2">{{ $item ?? '-' }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td class="custom5" colspan="2">-</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                    <!-- <tr>
                        <td class="custom5" width="120">Tanggal Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling) }}</td>
                    </tr>   
                    @php
                            $periode = explode(' - ', $header['periode_analisa']);
                            $periode1 = $periode[0] ?? '';
                            $periode2 = $periode[1] ?? '';
                        @endphp -->
                      <!-- <tr>
                        <td class="custom5" width="120">Periode Analisa</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($periode1) }} - {{ \App\Helpers\Helper::tanggal_indonesia($periode2) }}</td>
                    </tr> -->
                   
                    
                </table>

                {{-- Regulasi --}}
                @if (!empty($header->regulasi_custom))
                    @foreach (json_decode($header->regulasi_custom) as $key => $y)
                        <table style="padding-top: 10px;" width="100%">
                            @if($key + 1 == $page)
                                <tr>
                                    <td class="custom5" colspan="3"><strong>{{ explode('-',$y)[1] }}</strong></td>
                                </tr>
                            @endif
                        </table>
                    @endforeach
                @endif
            </td>
        </tr>
    </table>
</div>
