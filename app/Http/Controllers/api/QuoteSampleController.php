<?php

namespace App\Http\Controllers\api;

use App\Models\Jadwal;
use App\Models\Parameter;
use Illuminate\Http\Request;
use App\Models\SamplingPlan;
use Yajra\Datatables\Datatables;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use Illuminate\Support\Facades\DB;
use App\Models\QuotationNonKontrak;
use App\Models\DocumentCodingSample;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use Mpdf;

class QuoteSampleController extends Controller
{
    public function index(Request $request)
    {
        try {

            $periode_awal = date('Y-m-01', strtotime($request->periode_awal));
            $periode_akhir = date('Y-m-t', strtotime($request->periode_akhir));

            $model = $request->type == 'non_kontrak' ? QuotationNonKontrak::class : QuotationKontrakH::class;
            $flagColumn = $request->type == 'non_kontrak' ? 'request_quotation' : 'request_quotation_kontrak_H';

            $data = $model::with([
                'sampling.jadwal' => function ($q) use ($periode_awal, $periode_akhir) {
                    $q->where('is_active', true)
                        ->whereBetween('tanggal', [$periode_awal, $periode_akhir])
                        ->orderBy('tanggal', 'asc');
                },
                'sales',
                'order',
                'documentCodingSampling' => function ($q) use ($request) {
                    $q->where('menu', $request->act);
                },
            ]);

            if ($request->type != 'non_kontrak') {
                $data->with('detail');
            }

            $data->where([
                "$flagColumn.flag_status" => 'ordered',
                "$flagColumn.is_active" => true
            ])
                ->whereHas('sampling', function ($q) use ($periode_awal, $periode_akhir) {
                    $q->whereHas('jadwal', function ($sub) use ($periode_awal, $periode_akhir) {
                        $sub->where('is_active', true)
                            ->whereBetween('tanggal', [$periode_awal, $periode_akhir]);
                    });
                });

            $filtered = $data->get();
            return DataTables::of($filtered)
                ->filter(function ($item) use ($request) {
                    $search = strtolower($request->search['value'] ?? '');

                    if (!$search) {
                        return true;
                    }
                    if (strpos(strtolower($item->tanggal_penawaran), $search) !== false) {
                        return true;
                    }

                    // filter tanggal_sampling (ambil dari jadwal)
                    $foundSampling = false;
                    foreach ($item->sampling as $sampling) {
                        foreach ($sampling->jadwal as $jadwal) {
                            if (strpos(strtolower($jadwal->tanggal), $search) !== false) {
                                $foundSampling = true;
                                break 2;
                            }
                        }
                    }
                    if ($foundSampling) {
                        return true;
                    }
                    if (strpos(strtolower($item->no_document), $search) !== false) {
                        return true;
                    }
                    if ($item->order && strpos(strtolower($item->order->no_order), $search) !== false) {
                        return true;
                    }
                    if (strpos(strtolower($item->nama_perusahaan), $search) !== false) {
                        return true;
                    }
                    $status = strtolower($item->status_sampling);
                    if (
                        (strpos($search, '24') !== false && $status === 's24') ||
                        (strpos($search, 'antar') !== false && $status === 'sd') ||
                        (strpos($search, 'sampling') !== false && $status === 's') ||
                        (strpos($search, 're') !== false && $status === 'rs')
                    ) {
                        return true;
                    }
                    if (strpos(strtolower($item->flag_status), $search) !== false) {
                        return true;
                    }
                    if (strpos(strtolower($item->konsultan), $search) !== false) {
                        return true;
                    }
                    if (strpos(strtolower($item->created_by), $search) !== false) {
                        return true;
                    }
                    if (strpos(strtolower(date('Y-m-d', strtotime($item->created_at))), $search) !== false) {
                        return true;
                    }

                    return false;
                })
                ->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'error',
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function getQuoteSampleDocument(Request $request)
    {
        if ($request->type_document == 'QuoteSampleContract') {

            return $this->renderQuoteSampleContract($request->id, $request->periode_kontrak);
        } else {
            return $this->renderQuoteSample($request->id);
        }
    }

    public function renderQuoteSampleContract($id, $periode)
    {
        try {
            //grab data 
            $periode_kontrak = explode(',', \str_replace(' ', '', $periode));
            $data = QuotationKontrakH::where('id', (int) $id)->first();
            $data_order = OrderHeader::with('orderDetail')->where('no_document', $data->no_document)->where('is_active', true)->first();

            $detailOrderSD = collect(); // inisialisasi default
            if ($data_order && $data_order->orderDetail) {
                $detailOrderSD = $data_order->orderDetail
                    ->where('kategori_1', 'SD')
                    ->where('is_active', true);
            }

            $getIdSampling = QuotationKontrakH::where('no_document', $data_order->no_document)->where('is_active', true)->first();
            $getSamplingPlanAll = SamplingPlan::with(['jadwal' => function ($q) {
                $q->select('id_sampling', 'tanggal', 'jam')  // jangan lupa kolom foreign key (id_sampling)
                    ->where('is_active', true);
            }])
                ->select('id', 'keterangan_lain', 'tambahan', 'periode_kontrak')
                ->where('no_quotation', $getIdSampling->no_document)
                ->whereIn('periode_kontrak', $periode_kontrak)
                ->where('status', true)
                ->where('is_active', true)
                ->get();



            $detail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $getIdSampling->id)->whereIn('periode_kontrak', $periode_kontrak)->orderBy('periode_kontrak')->get();

            //lose grab data 

            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 10,
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            );

            $pdf = new Mpdf($mpdfConfig);
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
            $pdf->showWatermarkImage = true;

            $footer = array(
                'odd' => array(
                    'C' => array(
                        'content' => 'Hal {PAGENO} dari {nbpg}',
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#606060'
                    ),
                    'R' => array(
                        'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                        'font-size' => 5,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'L' => array(
                        'font-size' => 4,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'line' => -1,
                )
            );

            $pdf->setFooter($footer);
            $no = 1;
            foreach ($detail as $key => $value) {
                $tgl = [];
                $jam = [];
                $arrayKeterangan = [];
                $getSamplingPlan = $getSamplingPlanAll->firstWhere('periode_kontrak', $value->periode_kontrak);
                if ($getSamplingPlan) {
                    // Keterangan Lain
                    if ($getSamplingPlan->keterangan_lain && $getSamplingPlan->keterangan_lain !== 'null') {
                        foreach (json_decode($getSamplingPlan->keterangan_lain) as $keterangan) {
                            if (!empty($keterangan)) {
                                $arrayKeterangan[] = $keterangan;
                            }
                        }
                    }

                    // Tambahan
                    if ($getSamplingPlan->tambahan && $getSamplingPlan->tambahan !== 'null') {
                        foreach (json_decode($getSamplingPlan->tambahan) as $tambahan) {
                            if ($tambahan === 'MASKER') {
                                $arrayKeterangan[] = 'Memakai Masker';
                            } elseif ($tambahan === 'Pick Up Sampel') {
                                $arrayKeterangan[] = 'Sample Telah Disiapkan Oleh Pihak Pelanggan.';
                            }
                        }
                    }

                    // Ambil Jadwal
                    $getJadwal = $getSamplingPlan->jadwal ?? collect();

                    if ($getJadwal->isNotEmpty()) {
                        foreach ($getJadwal as $jadwal) {
                            $tgl[] = $this->tanggal_indonesia($jadwal->tanggal);
                            $jam[] = $jadwal->jam;
                        }
                    } else {
                        $tgl = '';
                        $jam = '';
                    }
                } else {
                    // Kalau tidak ada SamplingPlan
                    if ($value->status_sampling !== 'SD') {
                        $tgl = '';
                        $jam = '';
                    } else {
                        foreach ($detailOrderSD as $valueSD) {
                            if ($valueSD->tanggal_terima !== null) {
                                $tgl[] = $this->tanggal_indonesia($valueSD->tanggal_terima);
                            }
                        }
                    }
                }

                $keterangan_lain = implode(', ', $arrayKeterangan);

                $add = '-';
                $no_telp = '';
                if ($data->sales != null) {
                    $add = $data->sales->nama_lengkap;
                    $no_telp = ' (' . $data->sales->no_telpon . ')';
                }
                $kontrak = $this->tanggal_indonesia($value->periode_kontrak, 'period');
                // dump($value->periode_kontrak);
                // dump($kontrak);
                $filteredTgl = array_filter($tgl, function ($item) use ($kontrak) {
                    return strpos($item, $kontrak) !== false;
                });
                // dump($filteredTgl);



                if ($value->status_sampling != 'SD') {
                    $pdf->SetHTMLHeader('
                        <table class="table table-bordered" width="100%" style="margin-bottom: 10px;">
                            <tr class="">
                                <td width="25%" style="text-align: center; font-size: 13px;"><b>' . $data->no_document . '</b></td>
                                <td style="text-align: center; font-size: 13px;"><b>QUOTE SAMPLE (ORDER) - ' . $data_order->no_order . '</b><br><b>Periode: ' . $kontrak . '</b></td>
                                <td width="25%" style="text-align: center; font-size: 13px;"></td>
                            </tr>
                        </table>

                        <table class="table table-bordered" width="100%" style="margin-bottom: 10px;text-align:center;">
                                <tr>
                                    <td width="50%" style="font-weight: bold;">' . $data->nama_perusahaan . '</td>
                                    <td width="50%" style="font-weight: bold;text-align: center;">' . $data->alamat_sampling . '</td>
                                </tr>
                        </table>
                        <table class="table table-bordered" width="100%" style="margin-bottom: 10px; text-align:center;">
                            <tr>
                                <td colspan="2">PERMINTAAN CUSTOMER</td>
                                <td width="25%">TANGGAL SAMPLING</td>
                                <td width="25%">' . ($tgl != '' ? implode(', ', array_unique($tgl)) : '') . '</td>
                            </tr>
                            <tr>
                                <td rowspan="3" colspan="2" style="text-align: left; padding:5px;">' . $keterangan_lain . '</td>
                                <td>JAM TIBA DILOKASI</td>
                                <td>' . ($jam != '' ? implode(', ', array_unique($jam)) : '') . '</td>
                            </tr>
                            <tr>
                                <td>PIC/JABATAN</td>
                                <td style="text-align: center;">' . $data->nama_pic_sampling . '</td>
                            </tr>
                            <tr>
                                <td>NO HP</td>
                                <td style="text-align: center;">' . $data->no_tlp_pic_sampling . '</td>
                            </tr>
                        </table>
                    ');
                } else {
                    $pdf->SetHTMLHeader('
                        <table class="table table-bordered" width="100%" style="margin-bottom: 10px;">
                            <tr class="">
                                <td width="25%" style="text-align: center; font-size: 13px;"><b>' . $data->no_document . '</b></td>
                                <td style="text-align: center; font-size: 13px;"><b>QUOTE SAMPLE (ORDER) - ' . $data_order->no_order . '</b><br><b>Periode: ' . $kontrak . '</b></td>
                                <td width="25%" style="text-align: center; font-size: 13px;"></td>
                            </tr>
                        </table>

                        <table class="table table-bordered" width="100%" style="margin-bottom: 10px;text-align:center;">
                                <tr>
                                    <td width="50%" style="font-weight: bold;">' . $data->nama_perusahaan . '</td>
                                    <td width="50%" style="font-weight: bold;text-align: center;">' . $data->alamat_sampling . '</td>
                                </tr>
                        </table>
                        <table class="table table-bordered" width="100%" style="margin-bottom: 10px; text-align:center;">
                            <tr>
                                <td colspan="2">PERMINTAAN CUSTOMER</td>
                                <td width="25%">TANGGAL TERIMA</td>
                                <td width="25%">' . (!empty($filteredTgl) ? implode(', ', array_unique($filteredTgl)) : '') . '</td>
                            </tr>
                            <tr>
                                <td rowspan="3" colspan="2" style="text-align: left; padding:5px;">' . $keterangan_lain . '</td>
                                <td>JAM TERIMA SAMPLING</td>
                                <td>' . ($jam != '' ? implode(', ', array_unique($jam)) : '') . '</td>
                            </tr>
                            <tr>
                                <td>PIC/JABATAN</td>
                                <td style="text-align: center;">' . $data->nama_pic_sampling . '</td>
                            </tr>
                            <tr>
                                <td>NO HP</td>
                                <td style="text-align: center;">' . $data->no_tlp_pic_sampling . '</td>
                            </tr>
                        </table>
                    ');
                }

                if ($no > 1) {
                    $pdf->AddPage();
                }

                $pdf->WriteHTML('
                    <table class="table table-bordered" style="font-size: 8px; margin-bottom: 10px;">
                        <thead class="text-center">
                            <tr>
                                <th width="2%" style="padding: 5px !important;">NO</th>
                                <th width="85%">KETERANGAN PENGUJIAN</th>
                                <th>TITIK</th>
                            </tr>
                        </thead>
                        <tbody>');

                $i = 1;

                foreach (json_decode($value->data_pendukung_sampling) as $key => $pendukungSampling) {
                    $i = 1;
                    foreach ($pendukungSampling->data_sampling as $dataSampling) {
                        $regulasi = [];
                        if ($dataSampling->regulasi && $dataSampling->regulasi != "" && count(array_filter($dataSampling->regulasi)) > 0) {
                            foreach (array_filter($dataSampling->regulasi) as $k => $v) {
                                array_push($regulasi, explode('-', $v)[1]);
                            }
                        }

                        $kategori = explode("-", $dataSampling->kategori_1);
                        $kategori2 = explode("-", $dataSampling->kategori_2);

                        $pdf->WriteHTML(
                            '<tr>
                                <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                                <td style="font-size: 12px; padding: 5px;"><b style="font-size: 12px;">' . $kategori2[1] . ' - ' . implode('-', $regulasi) . ' - ' . $dataSampling->total_parameter . ' Parameter  ' . ($dataSampling->kategori_1 == '1-Air' ? '(' . number_format(($dataSampling->volume / 1000), 1) . ' L)' : '') . '</b>'
                        );

                        foreach ($dataSampling->parameter as $keys => $valuess) {
                            $dParam = explode(';', $valuess);
                            $p = Parameter::where('id', $dParam[0])->where('is_active', true)->first();
                            $regula = '';
                            if (!empty($p)) {
                                //$regula = $p->nama_regulasi;
                                $regula = $p->nama_lab;
                            }
                            if ($keys == 0) {
                                $pdf->WriteHTML('<br><hr><span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $regula . '</span> ');
                            } else {
                                $pdf->WriteHTML(' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $regula . '</span> ');
                            }
                        }

                        $pdf->WriteHTML(
                            '<td style="font-size: 13px; padding: 5px;text-align:center;">' . $dataSampling->jumlah_titik . '</td></tr>'
                        );
                    }
                }

                if ($value->transportasi > 0) {
                    $i = $i;
                    $pdf->WriteHTML('
                        <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $data->wilayah)[1] . '</td>
                            <td style="font-size: 13px; text-align:center;">' . $value->transportasi . '</td>
                        </tr>');
                }

                if ($value->perdiem_jumlah_orang > 0) {
                    $i = $i + 1;
                    $pdf->WriteHTML('
                        <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Perdiem : ' . $value->perdiem_jumlah_orang . '</td>
                            <td style="font-size: 13px; text-align:center;">' . $value->perdiem_jumlah_hari . '</td>
                        </tr>');
                }

                $pdf->WriteHTML('</tbody></table>');

                $pdf->WriteHTML('
                    <table class="table table-bordered" width="100%" style="text-align:center;margin-bottom: 10px;">
                        <tr>
                            <td rowspan="2" width="15%">DATA WAJIB LAPANGAN</td>
                            <td width="25%">TITIK KOORDINAT</td>
                            <td width="20%">CUACA</td>
                            <td width="20%">SUHU</td>
                            <td>WAKTU</td>
                        </tr>
                        <tr>
                            <td>KECEPATAN ANGIN</td>
                            <td>ARAH ANGIN</td>
                            <td>KELEMBAPAN</td>
                            <td>DESKRIPSI</td>
                        </tr>
                    </table>
                ');

                $apd = '';
                $genset = '';
                if (!is_null($getSamplingPlan)) {
                    if ($getSamplingPlan->tambahan != "null") {
                        if (in_array('APD LENGKAP', json_decode($getSamplingPlan->tambahan)))
                            $apd = 'LENGKAP';
                        if (in_array('GENSET', json_decode($getSamplingPlan->tambahan)))
                            $genset = 'BAWA';
                    } else {
                        $apd = '&nbsp;';
                    }
                } else {
                    $apd = '&nbsp;';
                }

                $pdf->WriteHTML('
                    <table class="table table-bordered" width="100%" style="text-align:center;margin-bottom: 10px;">
                        <tr>
                            <td rowspan="2" width="15%">HAL KHUSUS</td>
                            <td width="25%">GENSET</td>
                            <td width="20%">APD</td>
                            <td width="20%">HP</td>
                            <td>PERCIKAN API</td>
                        </tr>
                        <tr>
                            <td>' . $genset . '</td>
                            <td>' . $apd . '</td>
                            <td></td>
                            <td></td>
                        </tr>
                    </table>
                ');

                $pdf->WriteHTML('
                    <table class="table table-bordered" width="100%" style="text-align:center;">
                        <tr>
                            <td width="15%" height="5%">SALES</td>
                            <td width="45%">' . $add . $no_telp . '</td>
                            <td width="20%">ACC</td>
                            <td></td>
                        </tr>
                    </table>
                ');

                $no++;
            }

            $fileName = preg_replace('/\\//', '-', $data['no_document']) . '-QSC.pdf';

            $pdf->Output(public_path() . '/qs/' . $fileName, 'F');

            return $fileName;
        } catch (\Exception $e) {

            dd($e);
        }
    }
    // public function renderQuoteSampleContract($id)
    // {
    //     $data = QuotationKontrakH::where('id', $id)->first();

    //     try {
    //         $data_order = OrderHeader::where('no_document', $data->no_document)->where('is_active', true)->first();

    //         $mpdfConfig = array(
    //             'mode' => 'utf-8',
    //             'format' => 'A4',
    //             'margin_header' => 10,
    //             'margin_footer' => 3,
    //             'setAutoTopMargin' => 'stretch',
    //             'setAutoBottomMargin' => 'stretch',
    //             'orientation' => 'P'
    //         );

    //         $pdf = new Mpdf($mpdfConfig);
    //         $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
    //         $pdf->showWatermarkImage = true;

    //         $footer = array(
    //             'odd' => array(
    //                 'C' => array(
    //                     'content' => 'Hal {PAGENO} dari {nbpg}',
    //                     'font-size' => 6,
    //                     'font-style' => 'I',
    //                     'font-family' => 'serif',
    //                     'color' => '#606060'
    //                 ),
    //                 'R' => array(
    //                     'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
    //                     'font-size' => 5,
    //                     'font-style' => 'I',
    //                     'font-family' => 'serif',
    //                     'color' => '#000000'
    //                 ),
    //                 'L' => array(
    //                     'font-size' => 4,
    //                     'font-style' => 'I',
    //                     'font-family' => 'serif',
    //                     'color' => '#000000'
    //                 ),
    //                 'line' => -1,
    //             )
    //         );

    //         $pdf->setFooter($footer);

    //         $getIdSampling = QuotationKontrakH::where('no_document', $data_order->no_document)->where('is_active', true)->first();

    //         $detail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $getIdSampling->id)->orderBy('periode_kontrak')->get(['periode_kontrak','data_pendukung_sampling','transportasi','perdiem_jumlah_orang','perdiem_jumlah_hari','jumlah_orang_24jam','jumlah_hari_24jam'])->toArray();

    //         $detailOrder = OrderDetail::selectRaw('
    //             parameter,
    //             regulasi,
    //             kategori_2,
    //             kategori_3,
    //             MIN(periode) as periode,
    //             COUNT(*) as jumlah_titik
    //         ')
    //         ->where('id_order_header', $data_order->id)
    //         ->where('is_active', true)
    //         ->groupBy('parameter', 'regulasi', 'kategori_2', 'kategori_3','periode')
    //         ->orderBy('periode')
    //         ->get();
    //         $dataPendukungsampling=[];
    //         foreach ($detail as $key => $value) {

    //             $decodeJson =html_entity_decode($value['data_pendukung_sampling']);
    //             $decodeJson=json_decode($decodeJson,true);
    //             foreach ($decodeJson as $row) {
    //                 $dataPendukungsampling[] = [
    //                     'periode_kontrak' => $row['periode_kontrak'],
    //                     'data_sampling' => $row['data_sampling'],
    //                     'transportasi' =>$value['transportasi'], // sudah berupa array
    //                     'perdiem_jumlah_hari' =>$value['perdiem_jumlah_hari'], // sudah berupa array
    //                     'perdiem_jumlah_orang' =>$value['perdiem_jumlah_orang'], // sudah berupa array
    //                     'jumlah_orang_24jam' =>$value['jumlah_orang_24jam'], // sudah berupa array
    //                     'jumlah_hari_24jam' =>$value['jumlah_hari_24jam'], // sudah berupa array
    //                 ];
    //             }
    //         }

    //         // dd($dataPendukungsampling);
    //         foreach ($detailOrder as $itemOrder) {
    //             $itemOrder->volume = 0; // inisialisasi volume

    //             foreach ($dataPendukungsampling as $periodeSampling) {
    //                 if ($periodeSampling['periode_kontrak'] != $itemOrder->periode) continue;

    //                 foreach ($periodeSampling['data_sampling'] as $dataSampling) {
    //                     if (
    //                         isset($dataSampling['kategori_1'], $dataSampling['kategori_2']) &&
    //                         $dataSampling['kategori_1'] == $itemOrder->kategori_2 &&
    //                         $dataSampling['kategori_2'] == $itemOrder->kategori_3
    //                     ) {
    //                         $regulasiList = json_decode($itemOrder->regulasi, true);
    //                         if (!is_array($regulasiList)) {
    //                             $regulasiList = [];
    //                         }

    //                         $parameterList = json_decode($itemOrder->parameter, true);
    //                         if (!is_array($parameterList)) {
    //                             $parameterList = [];
    //                         }

    //                         foreach ($dataSampling['regulasi'] as $regulasi) {
    //                             foreach ($dataSampling['parameter'] as $param) {
    //                                 $splitParam = explode(';', $param);
    //                                 $namaParam = isset($splitParam[1]) ? $splitParam[1] : $splitParam[0];

    //                                 $matchParameter = false;
    //                                 foreach ($parameterList as $paramStr) {
    //                                     $split = explode(';', $paramStr);
    //                                     $namaParamOrder = isset($split[1]) ? $split[1] : $split[0];
    //                                     if ($namaParamOrder == $namaParam) {
    //                                         $matchParameter = true;
    //                                         break;
    //                                     }
    //                                 }

    //                                 if (
    //                                     $matchParameter &&
    //                                     in_array($regulasi, $regulasiList)
    //                                 ) {
    //                                     $itemOrder->volume += $dataSampling['volume'];
    //                                     $itemOrder->transportasi += $periodeSampling['transportasi'];
    //                                     $itemOrder->perdiem_jumlah_hari += $periodeSampling['perdiem_jumlah_hari'];
    //                                     $itemOrder->perdiem_jumlah_orang += $periodeSampling['perdiem_jumlah_orang'];
    //                                     $itemOrder->jumlah_orang_24jam += $periodeSampling['jumlah_orang_24jam'];
    //                                     $itemOrder->jumlah_hari_24jam += $periodeSampling['jumlah_hari_24jam'];

    //                                     // Jika satu parameter-regulasi sudah cocok, break agar tidak double count
    //                                     break 2;
    //                                 }
    //                             }
    //                         }
    //                     }
    //                 }
    //             }
    //         }

    //         // dd($detailOrder);
    //         $no = 1;
    //         foreach ($detailOrder as $key => $value) {

    //             $getSamplingPlan = SamplingPlan::select('id', 'keterangan_lain', 'tambahan')->where('no_quotation', $getIdSampling->no_document)->where('is_active', true)->where('periode_kontrak', $value->periode_kontrak)->first();

    //             $arrayKeterangan = [];
    //             if (!is_null($getSamplingPlan)) {

    //                 foreach (json_decode($getSamplingPlan->keterangan_lain) as $key) {
    //                     if ($key != '') {
    //                         array_push($arrayKeterangan, $key);
    //                     }
    //                 }

    //                 if ($getSamplingPlan->tambahan != "null") {
    //                     foreach (json_decode($getSamplingPlan->tambahan) as $key) {
    //                         if ($key == 'MASKER') {
    //                             array_push($arrayKeterangan, 'Memakai Masker');
    //                         } else if ($key == 'Pick Up Sampel') {
    //                             array_push($arrayKeterangan, 'Sample Telah Disiapkan Oleh Pihak Pelanggan.');
    //                         }
    //                     }
    //                 }

    //                 $getJadwal = Jadwal::select('tanggal', 'jam')->where('is_active', true)->where('id_sampling', $getSamplingPlan->id)->get();
    //                 $tgl = [];
    //                 $jam = [];
    //                 if ($getJadwal->isNotEmpty()) {
    //                     foreach ($getJadwal as $k => $v) {
    //                         array_push($tgl, $this->tanggal_indonesia($v->tanggal));
    //                     }

    //                     foreach ($getJadwal as $k => $v) {
    //                         array_push($jam, $v->jam);
    //                     }
    //                 } else {
    //                     $tgl = '';
    //                     $jam = '';
    //                 };
    //             } else {
    //                 $tgl = '';
    //                 $jam = '';
    //             }

    //             $keterangan_lain = implode(', ', $arrayKeterangan);

    //             $add = '-';
    //             $no_telp = '';
    //             if ($data->addby != null) {
    //                 $add = $data->addby->nama_lengkap;
    //                 $no_telp = ' (' . $data->addby->no_telpon . ')';
    //             }

    //             $kontrak = $this->tanggal_indonesia($value->periode, 'period');

    //             $pdf->SetHTMLHeader('
    //                 <table class="table table-bordered" width="100%" style="margin-bottom: 10px;">
    //                     <tr class="">
    //                         <td width="25%" style="text-align: center; font-size: 13px;"><b>' . $data->no_document . '</b></td>
    //                         <td style="text-align: center; font-size: 13px;"><b>QUOTE SAMPLE (ORDER) - ' . $data_order->no_order . '</b><br><b>Periode: ' . $kontrak . '</b></td>
    //                         <td width="25%" style="text-align: center; font-size: 13px;"></td>
    //                     </tr>
    //                 </table>

    //                 <table class="table table-bordered" width="100%" style="margin-bottom: 10px;text-align:center;">
    //                         <tr>
    //                             <td width="50%" style="font-weight: bold;">' . $data->nama_perusahaan . '</td>
    //                             <td width="50%" style="font-weight: bold;text-align: center;">' . $data->alamat_sampling . '</td>
    //                         </tr>
    //                 </table>

    //                 <table class="table table-bordered" width="100%" style="margin-bottom: 10px; text-align:center;">
    //                     <tr>
    //                         <td colspan="2">PERMINTAAN CUSTOMER</td>
    //                         <td width="25%">TANGGAL SAMPLING</td>
    //                         <td width="25%">' . ($tgl != '' ? implode(', ', array_unique($tgl)) : '') . '</td>
    //                     </tr>
    //                     <tr>
    //                         <td rowspan="3" colspan="2" style="text-align: left; padding:5px;">' . $keterangan_lain . '</td>
    //                         <td>JAM TIBA DILOKASI</td>
    //                         <td>' . ($jam != '' ? implode(', ', array_unique($jam)) : '') . '</td>
    //                     </tr>
    //                     <tr>
    //                         <td>PIC/JABATAN</td>
    //                         <td style="text-align: center;">' . $data->nama_pic_sampling . '</td>
    //                     </tr>
    //                     <tr>
    //                         <td>NO HP</td>
    //                         <td style="text-align: center;">' . $data->no_tlp_pic_sampling . '</td>
    //                     </tr>
    //                 </table>
    //             ');

    //             if ($no > 1) {
    //                 $pdf->AddPage();
    //             }

    //             $pdf->WriteHTML('
    //                 <table class="table table-bordered" style="font-size: 8px; margin-bottom: 10px;">
    //                     <thead class="text-center">
    //                         <tr>
    //                             <th width="2%" style="padding: 5px !important;">NO</th>
    //                             <th width="85%">KETERANGAN PENGUJIAN</th>
    //                             <th>TITIK</th>
    //                         </tr>
    //                     </thead>
    //                     <tbody>');

    //             $i = 1;
    //             $kategori = explode("-", $value->kategori_2);
    //                     $kategori2 = explode("-", $value->kategori_3);
    //             $regulasi = [];
    //             $textRegulasi=json_decode($value->regulasi,true);
    //             if (is_array($textRegulasi) && count(array_filter($textRegulasi)) > 0) {
    //                 foreach (array_filter($textRegulasi) as $item) {
    //                     $parts = explode('-', $item);
    //                     if (isset($parts[1])) {
    //                         $regulasi[] = trim($parts[1]);
    //                     }
    //                 }
    //             }
    //             $parameter=json_decode($value->parameter,true);
    //             $pdf->WriteHTML(
    //                 '<tr>
    //                     <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
    //                     <td style="font-size: 12px; padding: 5px;"><b style="font-size: 12px;">' . $kategori2[1] . ' - ' . implode('-', $regulasi) . ' - ' . count($parameter) . ' Parameter  ' . ($value->kategori_2 == '1-Air' ? '(' . number_format(( $value->volume / 1000), 1) . ' L)' : '') . '</b>'
    //             );

    //             foreach ($parameter as $keys => $valuess) {
    //                 $dParam = explode(';', $valuess);
    //                 $p = Parameter::where('id', $dParam[0])->where('is_active', true)->first();
    //                 $regula = '';
    //                 if (!empty($p)) {
    //                     $regula = $p->nama_regulasi;
    //                 }
    //                 if ($keys == 0) {
    //                     $pdf->WriteHTML('<br><hr><span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $regula . '</span> ');
    //                 } else {
    //                     $pdf->WriteHTML(' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $regula . '</span> ');
    //                 }
    //             }

    //             $pdf->WriteHTML(
    //                 '<td style="font-size: 13px; padding: 5px;text-align:center;">'.$value->jumlah_titik.'</td></tr>'
    //             );

    //             if ($value->transportasi > 0) {
    //                 $i = $i;
    //                 $pdf->WriteHTML('
    //                     <tr>
    //                         <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
    //                         <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $data->wilayah)[1] . '</td>
    //                         <td style="font-size: 13px; text-align:center;">' . $value->transportasi . '</td>
    //                     </tr>');
    //             }

    //             if ($value->perdiem_jumlah_orang > 0) {
    //                 $i = $i + 1;
    //                 $pdf->WriteHTML('
    //                     <tr>
    //                         <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
    //                         <td style="font-size: 13px;padding: 5px;">Perdiem : ' . $value->perdiem_jumlah_orang . '</td>
    //                         <td style="font-size: 13px; text-align:center;">' . $value->perdiem_jumlah_hari . '</td>
    //                     </tr>');
    //             }

    //             $pdf->WriteHTML('</tbody></table>');

    //             $pdf->WriteHTML('
    //                 <table class="table table-bordered" width="100%" style="text-align:center;margin-bottom: 10px;">
    //                     <tr>
    //                         <td rowspan="2" width="15%">DATA WAJIB LAPANGAN</td>
    //                         <td width="25%">TITIK KOORDINAT</td>
    //                         <td width="20%">CUACA</td>
    //                         <td width="20%">SUHU</td>
    //                         <td>WAKTU</td>
    //                     </tr>
    //                     <tr>
    //                         <td>KECEPATAN ANGIN</td>
    //                         <td>ARAH ANGIN</td>
    //                         <td>KELEMBAPAN</td>
    //                         <td>DESKRIPSI</td>
    //                     </tr>
    //                 </table>
    //             ');

    //             $apd = '';
    //             $genset = '';
    //             if (!is_null($getSamplingPlan)) {
    //                 if ($getSamplingPlan->tambahan != "null") {
    //                     if (in_array('APD LENGKAP', json_decode($getSamplingPlan->tambahan)))
    //                         $apd = 'LENGKAP';
    //                     if (in_array('GENSET', json_decode($getSamplingPlan->tambahan)))
    //                         $genset = 'BAWA';
    //                 } else {
    //                     $apd = '&nbsp;';
    //                 }
    //             } else {
    //                 $apd = '&nbsp;';
    //             }

    //             $pdf->WriteHTML('
    //                 <table class="table table-bordered" width="100%" style="text-align:center;margin-bottom: 10px;">
    //                     <tr>
    //                         <td rowspan="2" width="15%">HAL KHUSUS</td>
    //                         <td width="25%">GENSET</td>
    //                         <td width="20%">APD</td>
    //                         <td width="20%">HP</td>
    //                         <td>PERCIKAN API</td>
    //                     </tr>
    //                     <tr>
    //                         <td>' . $genset . '</td>
    //                         <td>' . $apd . '</td>
    //                         <td></td>
    //                         <td></td>
    //                     </tr>
    //                 </table>
    //             ');

    //             $pdf->WriteHTML('
    //                 <table class="table table-bordered" width="100%" style="text-align:center;">
    //                     <tr>
    //                         <td width="15%" height="5%">SALES</td>
    //                         <td width="45%">' . $add . $no_telp . '</td>
    //                         <td width="20%">ACC</td>
    //                         <td></td>
    //                     </tr>
    //                 </table>
    //             ');

    //             $no++;
    //         }


    //         $fileName = preg_replace('/\\//', '-', $data['no_document']) . '-QSC.pdf';

    //         $pdf->Output(public_path() . '/qs/' . $fileName, 'F');

    //         return $fileName;
    //     } catch (\Exception $e) {
    //         dd($e);
    //     }
    // }

    public function renderQuoteSample($id)
    {
        $data = QuotationNonKontrak::where('id', $id)->first();

        try {
            // dd($data);
            $data_order = OrderHeader::where('no_document', $data->no_document)->where('is_active', true)->first();
            //  dd($data->no_document);
            $sp = SamplingPlan::select('id', 'keterangan_lain', 'tambahan', 'no_quotation')->where('no_quotation', $data->no_document)->where('is_active', true)->where('status', 1)->first();

            $keter = [];
            if (!is_null($sp)) {
                foreach (json_decode($sp->keterangan_lain) as $key) {
                    if ($key != '') {
                        array_push($keter, $key);
                    }
                }

                if (!is_null($sp->tambahan) && $sp->tambahan != 'null') {
                    foreach (json_decode($sp->tambahan) as $key) {
                        if ($key == 'MASKER') {
                            array_push($keter, 'Memakai Masker');
                        } else if ($key == 'Pick Up Sampel') {
                            array_push($keter, 'Sample Telah Disiapkan Oleh Pihak Pelanggan.');
                        }
                    }
                }

                $jadwal_sekarang = Jadwal::select('tanggal', 'jam')->where('is_active', true)->where('no_quotation', $sp->no_quotation)->where('id_sampling', $sp->id)->orderBy('tanggal')->get();
                // $jadwal_tahun_depan = Jadwal::select('tanggal', 'jam')->where('is_active', true)->where('no_quotation', $sp->no_quotation)->orderBy('tanggal')->get();
                // $jadwal = $jadwal_sekarang->merge($jadwal_tahun_depan);
                $tgl = [];
                $jam = [];
                if ($jadwal_sekarang->isNotEmpty()) {
                    foreach ($jadwal_sekarang as $k => $v) {
                        array_push($tgl, $this->tanggal_indonesia($v->tanggal));
                    }

                    foreach ($jadwal_sekarang as $k => $v) {
                        array_push($jam, $v->jam);
                    }
                } else {
                    $tgl = '';
                    $jam = '';
                };
            } else {
                $tgl = '';
                $jam = '';
            }

            $keterangan_lain = implode(', ', $keter);

            $add = '-';
            $no_telp = '';
            if ($data->sales != null) {
                $add = $data->sales->nama_lengkap;
                $no_telp = ' (' . $data->sales->no_telpon . ')';
            }

            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 10,
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            );

            $pdf = new Mpdf($mpdfConfig);
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
            $pdf->showWatermarkImage = true;

            $footer = array(
                'odd' => array(
                    'C' => array(
                        'content' => 'Hal {PAGENO} dari {nbpg}',
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#606060'
                    ),
                    'R' => array(
                        'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                        'font-size' => 5,
                        'font-style' => 'I',
                        // 'font-style' => 'B',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'L' => array(
                        // 'content' => '' . $qr_img . '',
                        'font-size' => 4,
                        'font-style' => 'I',
                        // 'font-style' => 'B',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'line' => -1,
                )
            );
            if ($tgl != '') {
                $tgl = array_unique($tgl);
            }
            $pdf->setFooter($footer);

            // $fileName = preg_replace('/\\//', '-', $data->no_document) . '-QS.pdf';
            // $fileName = preg_replace('/\\//', '-', $data['no_document']) . '-QS-' . $data['nama_perusahaan'] . '.pdf';
            $fileName = preg_replace('/\\//', '-', $data['no_document']) . '-QS.pdf';

            $pdf->SetHTMLHeader('
                <table class="table table-bordered" width="100%" style="margin-bottom: 10px;">
                    <tr class="">
                        <td width="25%" style="text-align: center; font-size: 13px;"><b>' . $data->no_document . '</b></td>
                        <td style="text-align: center; font-size: 13px;"><b>QUOTE SAMPLE (ORDER) - ' . $data_order->no_order . '</b></td>
                        <td width="25%" style="text-align: center; font-size: 13px;"></td>
                    </tr>
                </table>

                <table class="table table-bordered" width="100%" style="margin-bottom: 10px;text-align:center;">
                        <tr>
                            <td width="50%" style="font-weight: bold;">' . $data->nama_perusahaan . '</td>
                            <td width="50%" style="font-weight: bold;text-align: center;">' . $data->alamat_sampling . '</td>
                        </tr>
                </table>

                <table class="table table-bordered" width="100%" style="margin-bottom: 10px; text-align:center;">
                    <tr>
                        <td colspan="2">PERMINTAAN CUSTOMER</td>
                        <td width="25%">TANGGAL SAMPLING</td>
                        <td width="25%">' . ($tgl != '' ? implode(', ', $tgl) : '') . '</td>
                    </tr>
                    <tr>
                        <td rowspan="3" colspan="2" style="text-align: left; padding:5px;">' . $keterangan_lain . '</td>
                        <td>JAM TIBA DILOKASI</td>
                        <td>' . ($jam != '' ? implode(', ', array_unique($jam)) : '') . '</td>
                    </tr>
                    <tr>
                        <td>PIC/JABATAN</td>
                        <td style="text-align: center;">' . $data->nama_pic_sampling . '</td>
                    </tr>
                    <tr>
                        <td>NO HP</td>
                        <td style="text-align: center;">' . $data->no_tlp_pic_sampling . '</td>
                    </tr>
                </table>
            ');

            $pdf->WriteHTML('
                <table class="table table-bordered" style="font-size: 8px; margin-bottom: 10px;">
                    <thead class="text-center">
                        <tr>
                            <th width="2%" style="padding: 5px !important;">NO</th>
                            <th width="85%">KETERANGAN PENGUJIAN</th>
                            <th>TITIK</th>
                        </tr>
                    </thead>
                    <tbody>');

            $i = 1;

            foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {
                $kategori = explode("-", $a->kategori_1);
                $kategori2 = explode("-", $a->kategori_2);
                $regulasi = [];
                if ($a->regulasi != '') {
                    if (count(array_filter($a->regulasi)) > 0) {
                        foreach (array_filter($a->regulasi) as $k => $v) {

                            array_push($regulasi, explode('-', $v)[1]);
                        }
                    }
                }
                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                        <td style="font-size: 12px; padding: 5px;"><b style="font-size: 12px;">' . $kategori2[1] . ' - ' . implode('-', $regulasi) . ' - ' . $a->total_parameter . ' Parameter  ' . ($a->kategori_1 == '1-Air' ? '(' . number_format(($a->volume / 1000), 1) . ' L)' : '') . '</b>'
                );

                foreach ($a->parameter as $keys => $valuess) {
                    $dParam = explode(';', $valuess);
                    $d = Parameter::where('id', $dParam[0])->where('is_active', true)->first();
                    $regula = '';
                    if (!empty($d)) {
                        $regula = $d->nama_lab;
                    }
                    if ($keys == 0) {
                        $pdf->WriteHTML('<br><hr><span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $regula . '</span> ');
                    } else {
                        $pdf->WriteHTML(' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $regula . '</span> ');
                    }
                    // if ($keys == 0) {
                    //     $pdf->WriteHTML('<br><hr><span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_lab . '</span> ');
                    // } else {
                    //     $pdf->WriteHTML(' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_lab . '</span> ');
                    // }
                }

                $pdf->WriteHTML(
                    '<td style="font-size: 13px; padding: 5px;text-align:center;">' . $a->jumlah_titik . '</td></tr>'
                );
            }

            if ($data->transportasi > 0) {
                $i = $i;
                $pdf->WriteHTML('
                    <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $data->wilayah)[1] . '</td>
                        <td style="font-size: 13px; text-align:center;">' . $data->transportasi / $data->transportasi . '</td>
                    </tr>');
            }

            if ($data->perdiem_jumlah_orang > 0) {
                $i = $i + 1;
                $pdf->WriteHTML('
                    <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Perdiem : ' . $data->perdiem_jumlah_orang / $data->perdiem_jumlah_hari . '</td>
                        <td style="font-size: 13px; text-align:center;">' . $data->perdiem_jumlah_hari / $data->perdiem_jumlah_hari . '</td>
                    </tr>');
            }

            $pdf->WriteHTML('</tbody></table>');

            $pdf->WriteHTML('
                <table class="table table-bordered" width="100%" style="text-align:center;margin-bottom: 10px;">
                    <tr>
                        <td rowspan="2" width="15%">DATA WAJIB LAPANGAN</td>
                        <td width="25%">TITIK KOORDINAT</td>
                        <td width="20%">CUACA</td>
                        <td width="20%">SUHU</td>
                        <td>WAKTU</td>
                    </tr>
                    <tr>
                        <td>KECEPATAN ANGIN</td>
                        <td>ARAH ANGIN</td>
                        <td>KELEMBAPAN</td>
                        <td>DESKRIPSI</td>
                    </tr>
                </table>
            ');

            $apd = '';
            $genset = '';
            if (!is_null($sp)) {
                if (!is_null($sp->tambahan) && $sp->tambahan != 'null') {
                    if (in_array('APD LENGKAP', json_decode($sp->tambahan)))
                        $apd = 'LENGKAP';
                    if (in_array('GENSET', json_decode($sp->tambahan)))
                        $genset = 'BAWA';
                } else {
                    $apd = '&nbsp;';
                }
            } else {
                $apd = '&nbsp;';
            }

            $pdf->WriteHTML('
                <table class="table table-bordered" width="100%" style="text-align:center;margin-bottom: 10px;">
                    <tr>
                        <td rowspan="2" width="15%">HAL KHUSUS</td>
                        <td width="25%">GENSET</td>
                        <td width="20%">APD</td>
                        <td width="20%">HP</td>
                        <td>PERCIKAN API</td>
                    </tr>
                    <tr>
                        <td>' . $genset . '</td>
                        <td>' . $apd . '</td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
            ');

            $pdf->WriteHTML('
                <table class="table table-bordered" width="100%" style="text-align:center;">
                    <tr>
                        <td width="15%" height="5%">SALES</td>
                        <td width="45%">' . $add . $no_telp . '</td>
                        <td width="20%">ACC</td>
                        <td></td>
                    </tr>
                </table>
            ');

            $fileName = preg_replace('/\\//', '-', $data['no_document']) . '-QS.pdf';

            $pdf->Output(public_path() . '/qs/' . $fileName, 'F');

            return $fileName;
        } catch (\Exception $e) {
            dd($e);
        }
    }

    private function tanggal_indonesia($tanggal, $mode = '')
    {
        $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $hari = ['Sun' => 'Minggu', 'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => 'Jum\'at', 'Sat' => 'Sabtu'];

        $date = strtotime($tanggal);
        $var = explode('-', $tanggal);

        $day = $hari[date('D', $date)];
        $month = $bulan[(int) $var[1] - 1];

        if ($mode == 'period')
            return $month . ' ' . $var[0];
        if ($mode == 'hari')
            return $day . ' / ' . $var[2] . ' ' . $month . ' ' . $var[0];

        return $var[2] . ' ' . $month . ' ' . $var[0];
    }
}
