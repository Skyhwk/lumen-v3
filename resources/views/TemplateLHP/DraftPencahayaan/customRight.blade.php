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
                        <td class="custom" width="120">No. LHP</td>
                        <td class="custom" width="200">JENIS SAMPEL</td>
                        <td class="custom" width="200">PARAMETER UJI</td>
                    </tr>
                    <tr>
                        <td class="custom">{{ $header->no_lhp }}</td>
                        <td class="custom">Lingkungan Kerja</td>
                        <td class="custom">Intensitas Pencahayaan <sup style="font-size: 8px;"><u>a</u></sup></td>
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
                        @php
                            $methode_sampling = $header->metode_sampling != null ? json_decode($header->metode_sampling) : [];
                        @endphp
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
                </table>

          
                @if (!empty($header->regulasi_custom))
                    @foreach (json_decode($header->regulasi_custom) as $y)

                        @php
                            $parts = explode('-', $y);
                            $regulasiId = $parts[0] ?? null;
                            $regulasiName = $parts[1] ?? '';
                            $regulasi = MasterRegulasi::find($regulasiId);
                            $tableObj = TabelRegulasi::whereJsonContains('id_regulasi', $regulasiId)->first();
                            $table = $tableObj ? $tableObj->konten : '';
                        @endphp

                        <table width="100%" style="padding-top: 10px;">
                            <tr>
                                <td class="custom5" colspan="3">
                                    <strong>{{ $regulasiName }}</strong>
                                </td>
                            </tr>
                        </table>

                        @if($table)
                            <table width="100%" style="padding-top: 5px;">
                                <tr>
                                    <td class="custom5" colspan="3">
                                        Lampiran di halaman terakhir
                                    </td>
                                </tr>
                            </table>
                        @endif

                    @endforeach
                @endif

                <!-- @if (!empty($header->regulasi))
                
                        @foreach (json_decode($header->regulasi) as $y)
                            <table style="padding-top: 10px;" width="100%">
                                <tr>
                                    <td class="custom5" colspan="3"><strong>{{ explode('-',$y)[1] }}</strong></td>
                                </tr>
                            </table>
                        @endforeach
                           @php
                            // pastikan $header ada nilainya
                            $regulasi = MasterRegulasi::where('id',  explode('-',$y)[0])->first();
                            $table = TabelRegulasi::whereJsonContains('id_regulasi',explode('-',$y)[0])->first();
                                if (!empty($table)) {
                                $table = $table->konten;
                            } else {
                                $table = '';
                            }
                        @endphp
                        @if($table)
                        <table style="padding-top: 5px;" width="100%">
                                <tr>
                                    <td class="custom5" colspan="3">Lampiran di halaman terakhir</td>
                                </tr>
                        </table>
                        @endif
                @endif -->
            </td>
        </tr>
    </table>
</div>
