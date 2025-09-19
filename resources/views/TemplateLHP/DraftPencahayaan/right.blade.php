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
                    if ($header->metode_sampling != null) {
                        $methode_sampling = '';
                        $dataArray =
                            $header->metode_sampling && count(json_decode($header->metode_sampling)) > 0
                                ? json_decode($header->metode_sampling)
                                : [];

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
                            return $accreditation ? "{$sni} <sup style=\"border-bottom: 1px solid;\">a</sup>" : $sni;
                        }, $dataArray);

                        foreach ($result as $index => $item) {
                            $methode_sampling .= '<span><span>' . ($index + 1) . '. ' . $item . '</span></span><br>';
                        }

                        if ($header->status_sampling == 'SD') {
                            $methode_sampling = $dataArray[0] ?? '-';
                        }
                    } else {
                        $methode_sampling = '-';
                    }

                    $period = explode(' - ', $header->periode_analisa);
                    $period = array_filter($period);
                    $period1 = '';
                    $period2 = '';
                    if (!empty($period)) {
                        $period1 = \App\Helpers\Helper::tanggal_indonesia($period[0]);
                        $period2 = \App\Helpers\Helper::tanggal_indonesia($period[1]);
                    }
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
                        <td class="custom5">Metode Sampling</td>
                        <td class="custom5">:</td>
                        @if ($header->status_sampling == 'SD')
                            <td class="custom5">****** {!! str_replace('-', '', $methode_sampling) !!}</td>
                        @else
                            <td class="custom5">{!! $methode_sampling !!}</td>
                        @endif
                    </tr>
                    <!-- <tr>
                        <td class="custom5">Periode Analisa</td>
                        <td class="custom5">:</td>
                        <td class="custom5">{{ $period1 }} - {{ $period2 }}</td>
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
