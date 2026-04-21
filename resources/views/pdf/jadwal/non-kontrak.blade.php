@php
    use App\Models\Parameter;
@endphp
@if ($part === 'header')
            <table class="tabel" width="100%">
                <tr class="tr_top">
                    <td class="text-left text-wrap" style="width: 33.33%;"><img class="img_0" src="{{ public_path() }}/img/isl_logo.png" alt="ISL"></td>
                    <td style="width: 33.33%; text-align: center;">
                        <h5 style="text-align:center; font-size:14px;"><b><u>SAMPLING PLAN</u></b></h5>
                        <p style="font-size: 10px;text-align:center;margin-top: -10px;">{{ $periode_ }}</p>
                    </td>
                    <td style="text-align: right;">
                        <p style="font-size: 9px; text-align:right;">{{ $tanggalCetak }} - {{ $jamCetak }}</p> <br>
                        <span style="font-size:11px; font-weight: bold; border: 1px solid gray;">{{ $status_kontrak }}</span> <span style="font-size:11px; font-weight: bold; border: 1px solid gray;" id="status_sampling">{{ $sampling }}</span>
                    </td>
                </tr>
            </table>
            <table class="table table-bordered" width="100%">
                <tr>
                    <td colspan="2" style="font-size: 12px; padding: 5px;"><h6 style="font-size:12px; font-weight: bold;" id="nama_customer">{!! $perusahaan !!}</h6></td>
                    <td style="font-size: 12px; padding: 5px;"><span style="font-size:12px; font-weight: bold;" id="no_document">{{ $sampling_plan->no_quotation }}</span></td>
                </tr>
                <tr>
                    <td colspan="2" style="font-size: 12px; padding: 5px;"><span style="font-size:12px;" id="alamat_customer">{{ $data->alamat_sampling }}</span></td>
                    <td style="font-size: 12px; padding: 5px;"><span style="font-size:12px; font-weight: bold;" id="no_document_sp">{{ $sampling_plan->no_document }}</span></td>
                </tr>
            </table>
@elseif ($part === 'body')
                <table class="table table-bordered" style="font-size: 8px;">
                    <thead class="text-center">
                        <tr>
                            <th width="2%" style="padding: 5px !important;">NO</th>
                            <th width="85%">KETERANGAN PENGUJIAN</th>
                            <th width="13%">TITIK</th>
                        </tr>
                    </thead>
                    <tbody>
@if ($isQtc)
@php $i = 1; @endphp
@foreach (json_decode($data->data_pendukung_sampling) as $key => $y)
@continue(! in_array($sampling_plan->periode_kontrak, $y->periode))
@php
    $kategori = explode('-', $y->kategori_1);
    $kategori2 = explode('-', $y->kategori_2);
    $regulasi = ($y->regulasi[0] != '') ? explode('-', $y->regulasi[0])[1] : '';
@endphp
                        <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">{{ $i }}</td>
                        <td style="font-size: 12px; padding: 5px;"><b style="font-size: 12px;">{{ $kategori2[1] }} - {{ $regulasi }} - {{ $y->total_parameter }} Parameter</b>
@foreach ($y->parameter as $keys => $valuess)
@php
    $dParam = explode(';', $valuess);
    $d = Parameter::where('id', $dParam[0])->where('is_active', 1)->first();
@endphp
@if ($keys == 0)
<br><hr><span style="font-size: 13px; float:left; display: inline; text-align:left;">{{ $d->nama_lab }}</span>
@else
 &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">{{ $d->nama_lab }}</span>
@endif
@endforeach
                        </td>
                        <td style="font-size: 13px; padding: 5px;text-align:center;">{{ $y->jumlah_titik }}</td></tr>
@php $i++; @endphp
@endforeach
                </tbody></table>
@else
@foreach (json_decode($data->data_pendukung_sampling) as $key => $a)
@php
    $kategori = explode('-', $a->kategori_1);
    $kategori2 = explode('-', $a->kategori_2);
    $regulasi = '';
    if (is_array($a->regulasi) && count($a->regulasi) > 0) {
        $cleanedRegulasi = array_map(function ($peraturan) {
            $parts = explode('-', $peraturan, 2);
            return $parts[1] ?? $peraturan;
        }, $a->regulasi);
        $regulasi = implode(', ', $cleanedRegulasi);
    }
@endphp
                        <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">{{ $loop->iteration }}</td>
                            <td style="font-size: 12px; padding: 5px;"><b style="font-size: 12px;">{{ $kategori2[1] }} - {{ $regulasi }} - {{ $a->total_parameter }} Parameter</b>
@foreach ($a->parameter as $keys => $valuess)
@php
    $dParam = explode(';', $valuess);
    $d = Parameter::where('id', $dParam[0])->where('is_active', 1)->first();
@endphp
@continue(! $d)
@if ($keys == 0)
<br><hr><span style="font-size: 13px; float:left; display: inline; text-align:left;">{{ $d->nama_lab }}</span>
@else
 &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">{{ $d->nama_lab }}</span>
@endif
@endforeach
                            </td>
                            <td style="font-size: 13px; padding: 5px;text-align:center;">{{ $a->jumlah_titik }}</td></tr>
@endforeach
                </tbody></table>
@endif

@if ($jadwalSection['show'])
                <table class="table table-bordered" style="font-size: 8px; margin-top:{{ $jadwalTableMarginTop }};" width="100%">
                    <thead class="text-center">
                        <tr>
                            <th colspan="4" style="text-align:center;">JADWAL SAMPLING</th>
                        </tr>
                        <tr>
                            <th class="text-center" width="25%">Tanggal</th>
                            <th class="text-center" width="15%">Jam Mulai</th>
                            <th class="text-center" width="15%">Jam Selesai</th>
                            <th class="text-center" width="45%">Sampler</th>
                        </tr>
                    </thead>
                    <tbody>
@foreach ($jadwalSection['rows'] as $row)
                    <tr>
                        <td style="text-align:center; vertical-align: middle;">{{ $row['tanggal'] }}</td>
                        <td style="text-align:center; vertical-align: middle;">{{ $row['jam_mulai'] }}</td>
                        <td style="text-align:center; vertical-align: middle;">{{ $row['jam_selesai'] }}</td>
                        <td style="text-align:center; vertical-align: middle;">{{ $row['samplers'] }}</td>
                    </tr>
@endforeach
                </tbody></table>

                <p style="font-size: 9px; font-style: italic; margin-top: 5px; text-align: left;">
                    <b>Catatan:</b> Sampler dapat berubah sewaktu-waktu sesuai dengan kondisi lapangan.
                </p>
@endif
@endif
