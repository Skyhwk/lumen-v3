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
                        <td class="custom5" width="120" colspan="3">
                            <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                        </td>
                    </tr> 

                    @php
                        $methode_sampling = $header->metode_sampling ? json_decode($header->metode_sampling) : '-';
                    @endphp

                    {{-- Metode Sampling --}}
                    <tr>
                        <td class="custom5" width="120">Metode Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">
                            <table width="100%" style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                                @foreach($methode_sampling as $index => $item)
                                    <tr>
                                        @if (count($methode_sampling) > 1)
                                            <td class="custom5" width="20">{{ $index + 1 }}.</td>
                                            <td class="custom5">{{ $item ?? '-' }}</td>
                                        @else
                                            <td class="custom5" colspan="2">{{ $item ?? '-' }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </table>
                        </td>

                    </tr>

                {{-- Tanggal Sampling / Terima --}}
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
                        $tanggal_ = $header->status_sampling == 'SD'
                            ? $header->tanggal_terima
                            : $header->tanggal_sampling;
                    @endphp
                    <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($tanggal_) }}</td>
                </tr>
            </table>

                {{-- Regulasi --}}
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
