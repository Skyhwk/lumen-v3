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
                        <td class="custom">{{ $header->sub_kategori }}</td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Informasi Pelanggan --}}
        <tr>
            <td>
                <table style="padding-top: 20px;" width="100%">
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
                <table style="padding-top: 10px;" width="100%">
                    <tr>
                        <td class="custom5" width="120">Alamat / Lokasi Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ $header->alamat_sampling }}</td>
                    </tr>
                </table>

                {{-- Informasi Sampling --}}
                <table style="padding-top: 10px;" width="100%">
                    <tr>
                        <td class="custom5" width="120">
                            <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                        </td>
                    </tr>
                       @php
                        if ($header->methode_sampling != null) {
                            
                            $methode_sampling = "";
                            $dataArray = json_decode($header->methode_sampling ?? []);
                            
                            $result = array_map(function ($item) {
                                $parts = explode(';', $item);
                                $accreditation = strpos($parts[0], 'AKREDITASI') !== false;
                                $sni = $parts[1] ?? '-';
                                return $accreditation ? "{$sni} <sup style=\"border-bottom: 1px solid;\">a</sup>" : $sni;
                            }, $dataArray);

                            foreach ($result as $index => $item) {
                                $methode_sampling .= "<span><span>" . ($index + 1) . ". " . $item . "</span></span><br>";
                            }

                            if($header->status_sampling == 'SD') {
                                $methode_sampling = $dataArray[0] ?? '-';
                            }
                        } else {
                            $methode_sampling = "-";
                        }
                    @endphp

                    <tr>
                        <td class="custom5">Metode Sampling</td>
                        <td class="custom5">:</td>
                        @if ($header->status_sampling == 'SD')
                            <td class="custom5">****** {!! str_replace('-', '', $methode_sampling) !!}</td>
                        @else
                            <td class="custom5">{!! $methode_sampling !!}</td>
                        @endif
                    </tr>
                     <tr>
                        <td class="custom5" width="120">@if ($header->status_sampling == 'SD') Tanggal Terima @else Tanggal Sampling @endif</td>
                        <td class="custom5" width="12">:</td>
                        @php
                            if($header->status_sampling == 'SD'){ 
                                $tanggal_ = $header->tanggal_terima ;
                            } else { 
                                $tanggal_ = $header->tanggal_sampling;
                            }
                        @endphp
                        <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($tanggal_) }}</td>
                    </tr>
                    @php
                        $periode = explode(' - ', $header['periode_analisa']);
                        $periode1 = $periode[0] ?? '';
                        $periode2 = $periode[1] ?? '';
                    @endphp
                  
                </table>

                {{-- Regulasi --}}
                @if (!empty($header->regulasi))
                    <table style="padding-top: 10px;" width="100%">
                        @foreach (json_decode($header->regulasi) as $y)
                            <tr>
                                <td class="custom5" colspan="3"><strong>**{{ $y }}</strong></td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                {{-- Tabel Kebisingan --}}
                <table border="1" cellspacing="0" cellpadding="2" width="100%" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>Durasi Pajanan Kebisingan per Hari</th>
                            <th>Level Kebisingan (dBA)</th>
                            <th>Durasi Pajanan Kebisingan per Hari</th>
                            <th>Level Kebisingan (dBA)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $dataKebisingan = [
                                ['24 Jam', '80', '28,12 Detik', '115'],
                                ['16', '82', '14,06', '118'],
                                ['8', '85', '7,03', '121'],
                                ['4', '88', '3,52', '124'],
                                ['2', '91', '1,76', '127'],
                                ['1', '94', '0,88', '130'],
                                ['30 Menit', '97', '0,44', '133'],
                                ['15', '100', '0,22', '136'],
                                ['7,5', '103', '0,11', '139'],
                                ['3,75', '106', '', ''],
                                ['1,88', '109', '', ''],
                                ['0,94', '112', '', ''],
                            ];
                        @endphp

                        @foreach ($dataKebisingan as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td style="text-align: center; vertical-align: middle;">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach

                        <tr>
                            <td colspan="4" style="text-align: center; vertical-align: middle;">
                                <em>Catatan: Pajanan bising tidak boleh melebihi level 140 dBA walaupun hanya sesaat</em>
                            </td>
                        </tr>
                    </tbody>
                </table>

            </td>
        </tr>
    </table>
</div>
