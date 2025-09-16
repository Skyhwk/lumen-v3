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
                        <td class="custom5" width="120" colspan="3">
                            <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="custom5" width="120">Metode Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">
                            {{ $header->metode_sampling[0] ?? '-' }}
                        </td>
                    </tr>
                </table>

                {{-- Regulasi --}}
                @if (!empty($header->regulasi))
                    <table style="padding-top: 10px;" width="100%">
                        @foreach (json_decode($header->regulasi) as $y)
                            <tr>
                                <td class="custom5" colspan="3"><strong>{{ $y }}</strong></td>
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
