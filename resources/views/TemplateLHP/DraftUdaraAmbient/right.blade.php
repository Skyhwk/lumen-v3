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
                        @if ($header->status_sampling == 'SD')
                            <tr>
                                <td class="custom5" colspan="3">(******) Adalah sampling tidak dilakukan Laboratorium</td>
                            </tr>
                        @endif
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>
