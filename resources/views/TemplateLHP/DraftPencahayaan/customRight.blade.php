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
                        <td colspan="3"><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                Pelanggan</span></td>
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
                        <td class="custom5" width="120" colspan="3"><span
                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span></td>
                    </tr>
                    <!-- @php
                        if ($header->metode_sampling != null) {
                            $metode_sampling = '';
                            $dataArray = json_decode($header->metode_sampling ?? []);

                            $result = array_map(function ($item) {
                                $sni = '-';
                                if (strpos($item, ';') !== false) {
                                    $parts = explode(';', $item);
                                    $accreditation = strpos($parts[0], 'AKREDITASI') !== false;
                                    $sni = $parts[1] ?? '-';
                                } else {
                                    $accreditation = null;
                                    $sni = $item;
                                }
                                return $accreditation
                                    ? "{$sni} <sup style=\"border-bottom: 1px solid;\">a</sup>"
                                    : $sni;
                            }, $dataArray);

                            foreach ($result as $index => $item) {
                                $metode_sampling .= '<span><span>' . ($index + 1) . '. ' . $item . '</span></span><br>';
                            }

                            if ($header->status_sampling == 'SD') {
                                $metode_sampling = $dataArray[0] ?? '-';
                            }
                        } else {
                            $metode_sampling = '-';
                        }
                    @endphp -->
                    <!-- <tr>
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
                    </tr> -->
                    <tr>
                        <td class="custom5">Metode Sampling</td>
                        <td class="custom5">:</td>
                        @if ($header->status_sampling == 'SD')
                            <td class="custom5">****** {!! str_replace('-', '', $metode_sampling) !!}</td>
                        @else
                            <td class="custom5">{!! $metode_sampling !!}</td>
                        @endif
                    </tr>
                </table>

                {{-- Regulasi --}}
                @if ($header->regulasi_custom != null)
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
            </td>
        </tr>
    </table>
</div>
