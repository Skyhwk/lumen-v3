@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;
@endphp
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
                     <td class="custom5">Metode Sampling</td>
                        <td class="custom5">:</td>
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
                </table>

                {{-- Regulasi --}}
                @if ($header->custom_regulasi!=null)
                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                        @foreach (json_decode($header->custom_regulasi) as $key => $y)
                        @if($key + 1 == $page)
                            <tr>
                                <td class="custom5" colspan="3"><strong>{{ $y }}</strong></td>
                            </tr>
                        @endif
                        @endforeach
                         @php
                            $regulasiId = explode('-', $y)[0];
                            $regulasiName = explode('-', $y)[1] ?? '';
                            $regulasi = MasterRegulasi::find($regulasiId);
                            $tableObj = TabelRegulasi::whereJsonContains('id_regulasi', $regulasiId)->first();
                            $table = $tableObj ? $tableObj->konten : '';
                        @endphp
                        @if($table)
                        <table style="padding-top: 5px;" width="100%">
                                <tr>
                                    <td class="custom5" colspan="3">Lampiran di halaman terakhir</td>
                                </tr>
                        </table>
                        @endif
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>
