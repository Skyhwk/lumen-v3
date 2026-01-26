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
use Carbon\Carbon;
class QuoteSampleSdController extends Controller
{
    public function index(Request $request)
    {
        try {
            // $model = $request->type == 'non_kontrak' ? QuotationNonKontrak::class : QuotationKontrakH::class;
            // $flagColumn = $request->type == 'non_kontrak' ? 'request_quotation' : 'request_quotation_kontrak_H';

            // $data = $model::with([
            //     'sampling.jadwal' => function ($q) use ($request) {
            //         $q->where('is_active', true)
            //         ->whereBetween("tanggal", [
            //             date('Y-m-01', strtotime($request->periode_awal)),
            //             date('Y-m-t', strtotime($request->periode_akhir))
            //         ]);
            //     },
            //     'sales',
            //     'order',
            //     'documentCodingSampling' => function ($q) use ($request) {
            //         $q->where('menu', $request->act);
            //     },
            // ]);

            // if ($request->type != 'non_kontrak')
            //     $data->with('detail');

            // $data->where([
            //     "$flagColumn.flag_status" => 'ordered',
            //     "$flagColumn.is_active" => true
            // ])

            // // dd($data->first());
            // ->whereBetween("$flagColumn.tanggal_penawaran", [
            //             date('Y-m-01', strtotime($request->periode_awal)),
            //             date('Y-m-t', strtotime($request->periode_akhir))
            //         ]);


            $model = $request->type == 'non_kontrak' ? QuotationNonKontrak::class : QuotationKontrakH::class;
            $flagColumn = $request->type == 'non_kontrak' ? 'request_quotation' : 'request_quotation_kontrak_H';

            $data = $model::with([
                'sampling.jadwal' => function ($q) {
                    $q->where('is_active', true);
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

            // kondisi utama tabel
            $data->where([
                "$flagColumn.flag_status" => 'ordered',
                "$flagColumn.is_active" => true,
                "$flagColumn.status_sampling" => 'SD'
            ]);

            // cek hanya sampling yang jadwal pertamanya sesuai
            // $data->whereHas('sampling', function ($q) use ($request) {
            //     $q->whereHas('jadwal', function ($sub) use ($request) {
            //         $sub->where('is_active', true)
            //             ->whereBetween('tanggal', [
            //                 date('Y-m-01', strtotime($request->periode_awal)),
            //                 date('Y-m-t', strtotime($request->periode_akhir))
            //             ])
            //             ->orderBy('tanggal', 'asc');
            //     });
            // });

            return DataTables::of($data)
                ->filterColumn('tanggal_penawaran', function ($query, $keyword) {
                    $query->where('tanggal_penawaran', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('tanggal_sampling', function ($query, $keyword) {
                    $query->whereHas('sampling.jadwal', function ($q) use ($keyword) {
                        $q->where('tanggal', 'like', "%$keyword%");
                    });
                })
                ->filterColumn('no_document', function ($query, $keyword) {
                    $query->where('no_document', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('order.no_order', function ($query, $keyword) {
                    $query->whereHas('order', function ($q) use ($keyword) {
                        $q->where('no_order', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                    $query->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('status_sampling', function ($query, $keyword) {
                    $keyword = strtolower($keyword);
                    if (strpos($keyword, '24')) {
                        $query->where('status_sampling', 'S24');
                    } elseif (strpos($keyword, 'antar')) {
                        $query->where('status_sampling', 'SD');
                    } elseif (strpos($keyword, 'sampling')) {
                        $query->where('status_sampling', 'S');
                    } elseif (strpos($keyword, 're')) {
                        $query->where('status_sampling', 'RS');
                    }
                })
                ->filterColumn('flag_status', function ($query, $keyword) {
                    $query->where('flag_status', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('konsultan', function ($query, $keyword) {
                    $query->where('konsultan', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('created_by', function ($query, $keyword) {
                    $query->where('created_by', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('created_at', function ($query, $keyword) {
                    $query->whereRaw("DATE(created_at) LIKE ?", ["%{$keyword}%"]);
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

    // public function index(Request $request)
    // {
    //     try {
    //         $data = $this->getQuotationData($request);

    //         return DataTables::of($data)
    //             ->filter(function ($query) use ($request) {
    //                 if ($request->has('search') && !empty($request->search['value'])) {
    //                     $keyword = strtolower($request->search['value']);
    //                     $query->where(function ($q) use ($keyword) {
    //                         $q->orWhere(DB::raw('LOWER(no_document)'), 'LIKE', "%{$keyword}%")
    //                             ->orWhere(DB::raw('LOWER(nama_perusahaan)'), 'LIKE', "%{$keyword}%")
    //                             // ->orWhere(DB::raw('LOWER(flag_status)'), 'LIKE', "%{$keyword}%")
    //                             ->orWhere(DB::raw('LOWER(konsultan)'), 'LIKE', "%{$keyword}%")
    //                             // ->orWhere(DB::raw('LOWER(tanggal_penawaran)'), 'LIKE', "%{$keyword}%")
    //                             ->orWhereHas('order', function ($q) use ($keyword) {
    //                                 $q->where(DB::raw('LOWER(no_order)'), 'LIKE', "%{$keyword}%");
    //                             });
    //                     });
    //                 }
    //             })
    //             ->make(true);
    //     } catch (\Exception $ex) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $ex->getMessage(),
    //             'line' => $ex->getLine(),
    //         ], 500);
    //     }
    // }

    // private function getQuotationData(Request $request)
    // {
    //     if ($request->type == 'non_kontrak') {
    //         return QuotationNonKontrak::with([
    //             'sampling',
    //             'sales',
    //             'order',
    //             'documentCodingSampling' => function ($q) use ($request) {
    //                 $q->where('menu', $request->act);
    //             },
    //         ])
    //             ->where([
    //                 'request_quotation.flag_status' => 'ordered',
    //                 'request_quotation.is_active' => true
    //             ])
    //             ->whereBetween('request_quotation.tanggal_penawaran', [
    //                 date('Y-m-01', strtotime($request->periode_awal)),
    //                 date('Y-m-t', strtotime($request->periode_akhir))
    //             ]);
    //     }

    //     return QuotationKontrakH::with([
    //         'sampling',
    //         'sales',
    //         'order',
    //         'documentCodingSampling' => function ($q) use ($request) {
    //             $q->where('menu', $request->act);
    //         },
    //         'detail'
    //     ])
    //         ->where([
    //             'request_quotation_kontrak_H.flag_status' => 'ordered',
    //             'request_quotation_kontrak_H.is_active' => true
    //         ])

    //         ->whereBetween('request_quotation_kontrak_H.tanggal_penawaran', [
    //             date('Y-m-01', strtotime($request->periode_awal)),
    //             date('Y-m-t', strtotime($request->periode_akhir))
    //         ]);
    // }

    public function getQuoteSampleDocument(Request $request)
    {
        if ($request->type_document == 'QuoteSampleContract') {

            return $this->renderQuoteSampleContract($request->id);
        } else {
            return $this->renderQuoteSample($request->id);
        }
    }


    public function renderQuoteSampleContract($id)
    {
        try {
            /* grab data */
            $data = QuotationKontrakH::where('id', (int) $id)->first();
            $data_order = OrderHeader::with('orderDetail')->where('no_document', $data->no_document)->where('is_active', true)->first();

            $detailOrderSD = collect(); // inisialisasi default
            if ($data_order && $data_order->orderDetail) {
                $detailOrderSD = $data_order->orderDetail
                    ->where('kategori_1', 'SD')
                    ->where('is_active', true);
            }

            $getIdSampling = QuotationKontrakH::where('no_document', $data_order->no_document)->where('is_active', true)->first();
            $getSamplingPlanAll = SamplingPlan::with(['jadwal' => function($q) {
                        $q->select('id_sampling', 'tanggal', 'jam')  // jangan lupa kolom foreign key (id_sampling)
                        ->where('is_active', true);
                    }])
                    ->select('id', 'keterangan_lain', 'tambahan', 'periode_kontrak')
                    ->where('no_quotation', $getIdSampling->no_document)
                    ->where('status',true)
                    ->where('is_active', true)
                    ->get();



            $detail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $getIdSampling->id)->orderBy('periode_kontrak')->get();
           
            /* close grab data */
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

                // Inisialisasi kosong
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
                $filteredTgl = array_filter($tgl, function($item) use ($kontrak) {
                    return strpos($item, $kontrak) !== false;
                });
                
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

    public function renderQuoteSample($id)
    {
        $data = QuotationNonKontrak::where('id', $id)->first();
        try {
            // dd($data);
            $data_order = OrderHeader::with('orderDetail')->where('no_document', $data->no_document)->where('is_active', true)->first();
            $sp = SamplingPlan::select('id', 'keterangan_lain', 'tambahan', 'no_quotation')->where('no_quotation', $data->no_document)->where('is_active', true)->where('status', 1)->first();
            $keter = [];
            $tgl = $data_order->orderDetail->pluck('tanggal_terima')->toArray();
            $tglFormatted = array_map(function ($tanggal) {
                if (is_null($tanggal)) {
                    return null;
                }
                return Carbon::parse($tanggal)->locale('id')->isoFormat('D MMMM YYYY');
                // return $this->tanggal_indonesia($tanggal);
            }, $tgl);

            $jam = '';
            // if (!is_null($sp)) {
            //     foreach (json_decode($sp->keterangan_lain) as $key) {
            //         if ($key != '') {
            //             array_push($keter, $key);
            //         }
            //     }

            //     if (!is_null($sp->tambahan) && $sp->tambahan != 'null') {
            //         foreach (json_decode($sp->tambahan) as $key) {
            //             if ($key == 'MASKER') {
            //                 array_push($keter, 'Memakai Masker');
            //             } else if ($key == 'Pick Up Sampel') {
            //                 array_push($keter, 'Sample Telah Disiapkan Oleh Pihak Pelanggan.');
            //             }
            //         }
            //     }

            //     // $jadwal_sekarang = Jadwal::select('tanggal', 'jam')->where('is_active', true)->where('no_quotation', $sp->no_quotation)->where('id_sampling', $sp->id)->orderBy('tanggal')->get();
            //     // $jadwal_tahun_depan = Jadwal::select('tanggal', 'jam')->where('is_active', true)->where('no_quotation', $sp->no_quotation)->orderBy('tanggal')->get();
            //     // $jadwal = $jadwal_sekarang->merge($jadwal_tahun_depan);
            //     // $tgl = [];
            //     // $jam = [];
            //     // if ($jadwal_sekarang->isNotEmpty()) {
            //     //     foreach ($jadwal_sekarang as $k => $v) {
            //     //         array_push($tgl, $this->tanggal_indonesia($v->tanggal));
            //     //     }

            //     //     foreach ($jadwal_sekarang as $k => $v) {
            //     //         array_push($jam, $v->jam);
            //     //     }
            //     // } else {
            //     //     $tgl = '';
            //     //     $jam = '';
            //     // }
            //     // ;
            // } else {
            //     $tgl = '';
            //     $jam = '';
            // }

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
            if ($tglFormatted != '') {
                $tglFormatted = array_unique($tglFormatted);
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
                        <td width="25%">TANGGAL TERIMA</td>
                        <td width="25%">' . ($tglFormatted != '' ? implode(', ', $tglFormatted) : '') . '</td>
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
