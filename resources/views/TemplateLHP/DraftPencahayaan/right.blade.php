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
                        <td class="custom" width="33%">No. LHP</td>
                        <td class="custom" width="33%">JENIS SAMPEL</td>
                        <td class="custom" width="33%">PARAMETER UJI</td>
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
                        <td><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span>
                        </td>
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
                @php
                    $methode_sampling = $header->metode_sampling ? json_decode($header->metode_sampling) : [];
                @endphp
                <table style="padding: 10px 0px 0px 0px;" width="100%">
                    <tr>
                        <td class="custom5" width="120"><span
                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span></td>
                    </tr>
                    <!-- <tr>
                        <td class="custom5" width="120">Tanggal Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ \App\Helpers\Helper::tanggal_indonesia($header->tanggal_sampling) }}</td>
                    </tr> -->
                    <tr>
                        <td class="custom5" width="120">Metode Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5"> 
                            <table width="100%" style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif; padding: 10px 0px 0px 0px;">
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
                    <!-- <tr>
                        <td class="custom5">Periode Analisa</td>
                        <td class="custom5">:</td>
                        <td class="custom5">{{-- $period1 --}} - {{-- $period2 --}}</td>
                    </tr> -->
                </table>

                {{-- Regulasi --}}
                <!-- @php
                    $bintang = '**';
                @endphp
                @if (!empty($header->regulasi))
                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                        @foreach (json_decode($header->regulasi) as $t => $y)
                            <tr>
                                <td class="custom5" colspan="3">{{ $bintang }}{{ $y }}</td>
                            </tr>
                            @php
                                $bintang .= '*';
                            @endphp
                        @endforeach
                    </table>
                @endif

                {{-- Keterangan --}}
                @if (!empty($header->keterangan))
                    <table style="padding: 5px 0px 0px 10px;" width="100%">
                        @foreach (json_decode($header->keterangan) as $t => $y)
                            <tr>
                                <td class="custom5" colspan="3">{{ $y }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif -->
                @if (!empty($header->regulasi))
        
                    @foreach (json_decode($header->regulasi) as $y)
                        <table style="padding-top: 10px;" width="100%">
                            <tr>
                                @php
                                
                                @endphp
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
                    
                @endif
            </td>
        </tr>
    </table>
</div>
