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

                    <tr>
                     <td class="custom5" width="120">Spesifikasi Metode</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">
                            @if (count($header->metode_sampling) > 1)
                                <ol>
                                    @foreach($header->metode_sampling as $index => $item)
                                        <li>{{ $item ?? '-' }}</li>
                                    @endforeach
                                </ol>
                            @else
                                {{ $header->metode_sampling[0] ?? '-' }}
                            @endif
                        </td>
                    </tr>
                </table>

           
                  @if (!empty($header->regulasi))
                    @foreach (json_decode($header->regulasi) as $y)
                            <table style="padding-top: 10px;" width="100%">
                                <tr>
                                    <td class="custom5" colspan="3"><strong>{{ explode('-',$y)[1] }}</strong></td>
                                </tr>
                            </table>
                    @endforeach
                    
                @endif
            </td>
        </tr>
    </table>
</div>
