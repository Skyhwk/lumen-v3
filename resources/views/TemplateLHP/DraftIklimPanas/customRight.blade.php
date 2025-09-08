



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
                    @php
                         $methode_sampling = $header->metode_sampling ? $header->metode_sampling : '-';
                    @endphp

                    <tr>
                     <td class="custom5">Spesifikasi Metode</td>
                        <td class="custom5">:</td>
                        <td class="custom5">{!! $methode_sampling !!}</td>
                    </tr>
                    <tr>
                        <td class="custom5" width="120">Tanggal Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling) }}</td>
                    </tr>   
                    @php
                            $periode = explode(' - ', $header['periode_analisa']);
                            $periode1 = $periode[0] ?? '';
                            $periode2 = $periode[1] ?? '';
                        @endphp
                      <!-- <tr>
                        <td class="custom5" width="120">Periode Analisa</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($periode1) }} - {{ \App\Helpers\Helper::tanggal_indonesia($periode2) }}</td>
                    </tr> -->
                   
                    
                </table>

                {{-- Regulasi --}}
                @if (!empty($header->regulasi_custom))
                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                        @foreach (json_decode($header->regulasi_custom) as $key => $y)
                        @if($key + 1 == $page)
                            <tr>
                                <td class="custom5" colspan="3"><strong>**{{ $y }}</strong></td>
                            </tr>
                        @endif
                        @endforeach
                    </table>
                @endif

                  <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px; margin-top: 20px">
                    <tr>
                        <th class="custom" rowspan="3">Pengaturan Siklus Waktu Kerja</th>
                        <th class="custom" colspan="4">ISBB (°C)</th>
                    </tr>
                    <tr>
                        <th class="custom" colspan="4">Beban Kerja</th>
                    </tr>
                    <tr>
                        <th class="custom">Ringan</th>
                        <th class="custom">Sedang</th>
                        <th class="custom">Berat</th>
                        <th class="custom">Sangat Berat</th>
                    </tr>
                    <tr>
                        <td class="custom2">75 - 100 %</td>
                        <td class="custom2">31,0</td>
                        <td class="custom2">28,0</td>
                        <td class="custom2">-</td>
                        <td class="custom2">-</td>
                    </tr>
                    <tr>
                        <td class="custom2">50 - 75 %</td>
                        <td class="custom2">31,0</td>
                        <td class="custom2">29,0</td>
                        <td class="custom2">27,5</td>
                        <td class="custom2">-</td>
                    </tr>
                    <tr>
                        <td class="custom2">25 - 50 %</td>
                        <td class="custom2">32,0</td>
                        <td class="custom2">30,0</td>
                        <td class="custom2">29,0</td>
                        <td class="custom2">28,0</td>
                    </tr>
                    <tr>
                        <td class="custom2">0 - 25 %</td>
                        <td class="custom2">32,5</td>
                        <td class="custom2">31,5</td>
                        <td class="custom2">30,5</td>
                        <td class="custom2">30,0</td>
                        </tr>
                 </table>
            </td>
        </tr>
    </table>
</div>
