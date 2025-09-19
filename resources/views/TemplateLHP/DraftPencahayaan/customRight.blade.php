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

          
                   @if (!empty($header->regulasi))
                
                        @foreach (json_decode($header->regulasi) as $y)
                            <table style="padding-top: 10px;" width="100%">
                                <tr>
                                    <td class="custom5" colspan="3"><strong>{{ explode('-',$y)[1] }}</strong></td>
                                </tr>
                            </table>
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
                        {!! preg_replace(
                                '/<table(\s|>)/i',
                                '<table border="1" cellspacing="0" cellpadding="2" style="border: 1px solid #000;"$1',
                                $table
                            ) !!}

                        @endforeach
                    
                @endif
            </td>
        </tr>
    </table>
</div>
