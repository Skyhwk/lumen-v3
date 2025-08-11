<?php

namespace App\Http\Controllers\api;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;

use Carbon\Carbon;

use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\Parameter;
use Mpdf\Mpdf;

class StpsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $periode_awal = Carbon::parse($request->periode_awal); // format dari frontend YYYY-MM
            $periode_akhir = Carbon::parse($request->periode_akhir)->endOfMonth(); // mengambil tanggal terakhir dari bulan terpilih
            $interval = $periode_awal->diff($periode_akhir);

            if ($interval->days > 91) return response()->json(['message' => 'Periode tidak boleh lebih dari 1 bulan'], 403);

            $data = OrderDetail::with([
                'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi',
                'orderHeader.samplingPlan',
                'orderHeader.samplingPlan.jadwal' => function ($q) {
                    $q->select(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'periode', DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')])
                        ->where('is_active', true)
                        ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'periode']);
                }
            ])
                ->select(['id_order_header', 'no_order', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
                ->where('is_active', true)
                ->whereBetween('tanggal_sampling', [
                    $periode_awal->format('Y-m-01'),
                    $periode_akhir->format('Y-m-t')
                ])
                // ->where('no_quotation', 'ISL/QTC/25-I/000228')
                ->groupBy(['id_order_header', 'no_order', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling']);

            $data = $data->get()->toArray();

            $formattedData = array_reduce($data, function ($carry, $item) {
                if (empty($item['order_header']) || empty($item['order_header']['sampling'])) return $carry;

                $samplingPlan = $item['order_header']['sampling'];
                $periode = $item['periode'] ?? '';

                $targetPlan = $periode ?
                    current(array_filter(
                        $samplingPlan,
                        fn($plan) =>
                        isset($plan['periode_kontrak']) && $plan['periode_kontrak'] == $periode
                    )) :
                    current($samplingPlan);
                if (!$targetPlan) return $carry;

                $jadwal = $targetPlan['jadwal'] ?? [];
                $results = [];

                foreach ($jadwal as $schedule) {
                    if ($schedule['tanggal'] == $item['tanggal_sampling']) {
                        // dump(json_decode($schedule['sampler'], true));
                        $results[] = [
                            'nomor_quotation' => $item['order_header']['no_document'] ?? '',
                            'nama_perusahaan' => $item['order_header']['nama_perusahaan'] ?? '',
                            'status_sampling' => $item['kategori_1'] ?? '',
                            'periode' => $periode,
                            'jadwal' => $schedule['tanggal'],
                            'jadwal_jam_mulai' => $schedule['jam_mulai'],
                            'jadwal_jam_selesai' => $schedule['jam_selesai'],
                            'kategori' => implode(',', json_decode($schedule['kategori'], true) ?? []),
                            'sampler' => $schedule['sampler'] ?? '',
                            'no_order' => $item['no_order'] ?? '',
                            'alamat_sampling' => $item['order_header']['alamat_sampling'] ?? '',
                            'konsultan' => $item['order_header']['konsultan'] ?? '',
                            'info_pendukung' => json_encode([
                                'nama_pic_order' => $item['order_header']['nama_pic_order'],
                                'nama_pic_sampling' => $item['order_header']['nama_pic_sampling'],
                                'no_tlp_pic_sampling' => $item['order_header']['no_tlp_pic_sampling'],
                                'jabatan_pic_sampling' => $item['order_header']['jabatan_pic_sampling'],
                                'jabatan_pic_order' => $item['order_header']['jabatan_pic_order']
                            ]),
                            'info_sampling' => json_encode([
                                'id_request' => $targetPlan['quotation_id'],
                                'status_quotation' => $targetPlan['status_quotation']
                            ]),
                            'is_revisi' => $item['order_header']['is_revisi']
                        ];
                    }
                }

                return array_merge($carry, $results);
            }, []);

            $groupedData = [];
            foreach ($formattedData as $item) {
                $key = implode('|', [
                    $item['nomor_quotation'],
                    $item['nama_perusahaan'],
                    $item['status_sampling'],
                    $item['periode'],
                    $item['jadwal'],
                    $item['no_order'],
                    $item['alamat_sampling'],
                    $item['konsultan'],
                    $item['kategori'],
                    $item['info_pendukung'],
                    $item['jadwal_jam_mulai'],
                    $item['jadwal_jam_selesai'],
                    $item['info_sampling'],
                ]);

                if (!isset($groupedData[$key])) {
                    $groupedData[$key] = [
                        'nomor_quotation' => $item['nomor_quotation'],
                        'nama_perusahaan' => $item['nama_perusahaan'],
                        'status_sampling' => $item['status_sampling'],
                        'periode' => $item['periode'],
                        'jadwal' => $item['jadwal'],
                        'kategori' => $item['kategori'],
                        'sampler' => $item['sampler'],
                        'no_order' => $item['no_order'],
                        'alamat_sampling' => $item['alamat_sampling'],
                        'konsultan' => $item['konsultan'],
                        'info_pendukung' => $item['info_pendukung'],
                        'jadwal_jam_mulai' => $item['jadwal_jam_mulai'],
                        'jadwal_jam_selesai' => $item['jadwal_jam_selesai'],
                        'info_sampling' => $item['info_sampling'],
                        'is_revisi' => $item['is_revisi']
                    ];
                } else {
                    $groupedData[$key]['sampler'] .= ',' . $item['sampler'];
                }

                $uniqueSampler = explode(',', $groupedData[$key]['sampler']);
                $uniqueSampler = array_unique($uniqueSampler);
                $groupedData[$key]['sampler'] = implode(',', $uniqueSampler);
            }

            $finalResult = array_values($groupedData);

            return DataTables::of($finalResult)->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }


    public function cetakDataQs(Request $request)
    {
        try {
            $kategori = str_replace('&quot;', '"', $request->kategori);
            $kategori_ = str_replace('&amp;', '&', $kategori);
            if ($request->has('no_document')) {
                if ($request->no_document != null || $request->no_document != '') {
                    $tipe = explode("/", $request->no_document);

                    $kategori = str_replace('&quot;', '"', $request->kategori);
                    $kategori = str_replace('&amp;', '&', $kategori);

                    $jadwal = Jadwal::where('no_qt', $request->no_document)
                        ->where('tanggal', DB::raw("CAST('" . $request->tgl_sampling . "' AS DATE)"))
                        ->where('kategori', $kategori)
                        ->where('durasi', $request->durasi)
                        ->where('active', 0)
                        ->first();

                    $sp = DB::table('sampling_plan')->where('id', $jadwal->sample_id)->where('active', 1)->first();

                    $data_qt = [];
                    $data_sampling = [];
                    // dd($jadwal->kategori);
                    if ($sp) {
                        $groupedData = [];
                        $insample = [];
                        foreach (json_decode($jadwal->kategori) as $item) {
                            $parts = explode(" - ", $item);
                            $kategori = $parts[0];
                            array_push($insample, $parts[1]);
                            if (array_key_exists($kategori, $groupedData)) {
                                $groupedData[$kategori]++;
                            } else {
                                $groupedData[$kategori] = 1;
                            }
                        }

                        $type_qt = \explode('/', $request->no_document)[1];
                        if ($type_qt == 'QT') {
                            $data_qt = DB::table('request_quotation')->where('id', $sp->qoutation_id)->first();
                            $sales = DB::table('users')->where('id', $data_qt->add_by)->first();
                        } else if ($type_qt == 'QTC') {
                            $data_qt = DB::table('request_quotation_kontrak_H')->where('id', $sp->qoutation_id)->first();
                            $sales = DB::table('users')->where('id', $data_qt->add_by)->first();
                        } else {
                            return response()->json([
                                'message' => 'Data Not Found.!',
                            ], 401);
                        }

                        $data_sampling = [];
                        try {
                            foreach ($groupedData as $k => $y) {
                                $kateg = DB::table('sub_kategori_sample')->where('nama', $k)->where('active', 0)->first();
                                if (is_null($kateg)) {
                                    return response()->json([
                                        'message' => "Kategori $k dalam QT $request->no_document diJadwal Belum diupdate.! ",
                                    ], 401);
                                }
                                $kateg1 = $kateg->id . '-' . $kateg->nama;
                                $act = $request->menu;

                                $order_detail = OrderD::select(DB::raw('DISTINCT order_detail.*, order_header.tgl_order as tgl_order, order_header.no_order as no_order, order_header.konsultan as konsultan, order_header.nama_perusahaan as nama_perusahaan, order_header.nama_pic_order as nama_pic_order, order_header.nama_pic_sampling as nama_pic_sampling, order_header.no_tlp_pic_sampling as no_tlp_pic_sampling, order_header.jabatan_pic_sampling as jabatan_pic_sampling, order_header.jabatan_pic_order as jabatan_pic_order, order_header.alamat_sampling as alamat_sampling, coding_sampling.jumlah_label as jumlah_label'))

                                    ->leftJoin('coding_sampling', function ($join) {
                                        $join->on('order_detail.no_sample', '=', 'coding_sampling.no_sample');
                                        $join->where('coding_sampling.id', '=', DB::raw("(select max(`id`) from coding_sampling)"));
                                    })

                                    ->leftJoin('order_header', 'order_detail.id_order_header', '=', 'order_header.id')
                                    ->where('order_detail.id_order_header', $request->id_order_header)
                                    ->where('order_detail.kategori_3', $kateg1)
                                    ->where('order_detail.tgl_sampling', $request->tgl_sampling)
                                    ->where('order_detail.active', 0)
                                    ->whereIN(DB::raw('RIGHT(order_detail.no_sample, 3)'), $insample)

                                    ->orderBy('order_detail.no_sample', 'ASC')
                                    ->get();
                                foreach ($order_detail as $c => $vv) {

                                    $par = json_decode($vv->param);
                                    $param = [];
                                    foreach ($par as $key => $val) {
                                        array_push($param, \explode(";", $val)[1]);
                                    }

                                    $par = json_decode($vv->regulasi);
                                    $reg = [];
                                    if ($par != '') {
                                        foreach ($par as $key => $val) {
                                            if ($val != '') {
                                                array_push($reg, \explode("-", $val)[1]);
                                            }
                                        }
                                    }
                                    $vol = null;
                                    if ($vv->botol != null) {
                                        $volume = 0;
                                        foreach (json_decode($vv->botol) as $key => $value) {
                                            $volume += $value->volume;
                                        }
                                        $vol = $volume;
                                    }

                                    array_push($data_sampling, (object) [
                                        'no_sample' => $vv->no_sample,
                                        'konsultan' => $vv->konsultan,
                                        'alamat_sampling' => $vv->alamat_sampling,
                                        'nama_perusahaan' => $vv->nama_perusahaan,
                                        'nama_pic_order' => $vv->nama_pic_order,
                                        'nama_pic_sampling' => $vv->nama_pic_sampling,
                                        'no_tlp_pic_sampling' => $vv->no_tlp_pic_sampling,
                                        'no_tlp_pic_sampling' => $vv->no_tlp_pic_sampling,
                                        'jabatan_pic_sampling' => $vv->jabatan_pic_sampling,
                                        'jabatan_pic_order' => $vv->jabatan_pic_order,
                                        'alamat_sampling' => $vv->alamat_sampling,
                                        'kategori_2' => $vv->kategori_2,
                                        'kategori_3' => $vv->kategori_3,
                                        'no_order' => $vv->no_order,
                                        'tambahan' => $sp->tambahan,
                                        'status_sampling' => $vv->kategori_1,
                                        'keterangan_lain' => $sp->keterangan_lain,
                                        'nama_lengkap' => $sales->nama_lengkap,
                                        'no_telpon' => $sales->no_telpon,
                                        'tgl_sampling' => $jadwal->tanggal,
                                        'jam_sampling' => $jadwal->jam,
                                        'keterangan_1' => $vv->keterangan_1,
                                        'jumlah_label' => $vv->jumlah_label,
                                        // 'status_sampling' => $vv->status_sampling,
                                        'id' => $vv->id,
                                        'id_order_header' => $vv->id_order_header,
                                        'id_req_header' => $request->id_req_header,
                                        'periode_kontrak' => $sp->periode_kontrak,
                                        'tgl_order' => $vv->tgl_order,
                                        'botol' => $vv->botol,
                                        'parameter' => $param,
                                        'no_order' => $vv->no_order,
                                        'no_document' => $request->no_document,
                                        'regulasi' => $reg,
                                        'volume' => $vol,
                                    ]);
                                }
                            }
                            $collection2 = collect($data_sampling);
                            $data_sampling = $collection2->sortBy('no_sample');
                        } catch (\Throwable $th) {
                            dd($th);
                        }
                    } else {
                        return response()->json([
                            'message' => 'Data sampling plan tidak ditemukan.!',
                        ], 401);
                    }
                } else {
                    $data_sampling = [];
                }
            }

            if ($request->type_document == 'STPS') {
                if ($request->status_pr == 'downloadDoc') {
                    $cetak = self::cetakPDFSTPS($data_sampling, $data_qt, $request->sampler, "", $request->action, $request->type_document, $request->status_pr, $request->tgl_sample, $request->durasi, $request->kategori);
                } else if ($request->status_pr == 'printDoc') {
                    try {
                        $cetak = self::cetakPDFSTPS($data_sampling, $data_qt, $request->sampler, "", $request->action, $request->type_document, $request->status_pr, $request->tgl_sample, $request->durasi, $request->kategori);

                        $link = 'http://' . $request->ip_print . '/public/printing';
                        $url = request()->headers->all()['origin'][0];

                        $response = Http::asForm()
                            ->post($link, [
                                'printer' => $request->printer,
                                'file' => $url . '/utc/apps/public/stps/' . $cetak,
                            ]);
                    } catch (Exception $e) {
                        return response()->json([
                            'message' => $e->getMessage(),
                        ], 401);
                    }
                }

                try {
                    $cek = DB::table('doc_coding_sample')
                        ->where('no_qt', $request->no_document)
                        ->where('print_by', $this->userid)
                        ->where('tgl_sample', $request->tgl_sampling)
                        ->where('durasi', $request->durasi)
                        ->where('kategori', $kategori_)
                        ->where('menu', $request->menu)
                        ->first();

                    $status_qt = '';

                    if (explode('/', $request->no_document)[1] == 'QTC') {
                        $status_qt = 'kontrak';
                    } else {
                        $status_qt = 'non_kontrak';
                    }

                    if (!is_null($cek)) {
                        $status_pr = json_decode($cek->status_pr, true);
                        $hist = json_decode($cek->history_time, true);
                        array_push($hist, date('Y-m-d H:i:s'));
                        $key = array_search($request->action, array_column($status_pr, 'action'));

                        if ($key !== false) {
                            $status_pr[$key]['total_print'] += 1;
                        } else {
                            $status_pr[] = ['action' => $request->action, 'total_print' => 1];
                        }

                        $cek = DB::table('doc_coding_sample')
                            ->where('no_qt', $request->no_document)
                            ->where('print_by', $this->userid)
                            ->where('tgl_sample', $request->tgl_sampling)
                            ->where('durasi', $request->durasi)
                            ->where('kategori', $kategori_)
                            ->where('menu', $request->menu)
                            ->update([
                                'status_pr' => json_encode($status_pr),
                                'history_time' => json_encode($hist),
                            ]);
                    } else {
                        $insert = DB::table('doc_coding_sample')->insert([
                            'no_qt' => $request->no_document,
                            'status_qt' => $status_qt,
                            'tgl_sample' => $request->tgl_sampling,
                            'durasi' => $request->durasi,
                            'kategori' => $kategori_,
                            'status_pr' => json_encode([['action' => $request->action, 'total_print' => 1]]),
                            'history_time' => json_encode([date('Y-m-d H:i:s')]),
                            'menu' => $request->menu,
                            'print_by' => $this->userid
                        ]);
                    }
                    return response()->json([
                        'message' => 'Berhasil cetak data STPS.!',
                        'link' => $cetak
                    ], 200);
                } catch (\Exception $th) {
                    dd($th);
                }
            }
        } catch (Exception $e) {
            dd($e);
        }
    }


    public function cetakPDFSTPS(Request $request)
    {
        try {
            //  ==============================================COLLECT DATA========================================
            $tipe_penawaran = \explode('/', $request->nomor_quotation)[1];
            if ($tipe_penawaran == 'QTC') {
                if ($request->periode == null || $request->periode == "") {
                    return response()->json([
                        'message' => 'Periode tidak ditemukan.!',
                    ], 401);
                }

                $dataPenawaran = QuotationKontrakH::with(['order', 'sampling', 'detail'])->where('no_document', $request->nomor_quotation)->first();
                $dataOrder = $dataPenawaran->order;
                $dataSampling = $dataPenawaran->sampling;

                $unik_kategori = $dataOrder->orderDetail()->where('periode', $request->periode)
                ->where('is_active',true)->get()->pluck('kategori_3')->unique()->toArray();
                $pra_no_sample = [];
                $kategori_sample = [];
                foreach (\explode(',', $request->kategori) as $kat) {
                    $split = explode(' - ', $kat);
                    $kategori_sample[] = html_entity_decode($split[0]);
                    $pra_no_sample[] = $dataOrder->no_order . '/' . $split[1];
                }
                $pra_no_sample = array_unique($pra_no_sample);
                // dd($unik_kategori,$kategori_sample,$pra_no_sample);

                foreach ($unik_kategori as $kategori) {
                    // if($kategori == null) continue;
                    $split = explode('-', $kategori);
                    $id = $split[0];
                    $nama_kategori = $split[1];

                    if (in_array($nama_kategori, $kategori_sample)) {
                        $kategori_sample = array_map(function ($item) use ($id, $nama_kategori) {
                            if ($item == $nama_kategori) {
                                return $id . '-' . $nama_kategori;
                            }
                            return $item;
                        }, $kategori_sample);
                    }
                }
                // dd($kategori_sample,$pra_no_sample);
                $dataOrderDetailPerPeriode = $dataOrder->orderDetail()
                    ->select('kategori_3', 'periode', 'kategori_2', 'regulasi', 'keterangan_1', 'parameter', 'persiapan','no_sampel')
                    ->where('periode', $request->periode)
                    ->whereIn('kategori_3', $kategori_sample)
                    ->whereIn('no_sampel', $pra_no_sample)
                    ->where('is_active', 1)
                    // ->groupBy('kategori_3', 'periode', 'kategori_2', 'regulasi', 'keterangan_1', 'parameter', 'persiapan')
                    ->orderBy('periode')
                    ->orderBy('no_sampel')
                    ->get()
                    ->groupBy(['kategori_3', 'regulasi', 'parameter'])

                    ->map(function ($kategori3Group) {
                        return $kategori3Group->map(function ($regulasiGroup) {
                            return $regulasiGroup->map(function ($parameterGroup) {
                                $jumlahTitik = $parameterGroup->count();

                                return [
                                    'kategori_3' => \explode('-', $parameterGroup->first()->kategori_3)[1],
                                    'periode' => $parameterGroup->first()->periode,
                                    'kategori_2' => \explode('-', $parameterGroup->first()->kategori_2)[1],
                                    'regulasi' => $parameterGroup->first()->regulasi ? array_map(function ($item) {
                                        return explode('-', $item)[1];
                                    }, json_decode($parameterGroup->first()->regulasi) ?? []) : [],
                                    'keterangan_1' => $parameterGroup->first()->keterangan_1,
                                    'parameter' => array_map(function ($item) {
                                        $paramId = explode(';', $item)[0];
                                        $param = Parameter::find($paramId);
                                        return $param ? $param->nama_lab : null;
                                    }, json_decode($parameterGroup->first()->parameter)),
                                    'persiapan' => ($parameterGroup->first()->kategori_2 == '1-Air' ? '( ' . number_format((array_sum(array_map(function ($item) {
                                        if (!is_object($item)) return 0;
                                        $persiapan = json_decode($item->persiapan, true);
                                        return $persiapan ? array_sum(array_column($persiapan, 'volume')) : 0;
                                    }, $parameterGroup->toArray())) / 1000), 1) . ' L )' : ''),
                                    'total_parameter' => count(json_decode($parameterGroup->first()->parameter) ?: []),
                                    'jumlah_titik' => $jumlahTitik
                                ];
                            })->values();
                        })->collapse();
                    })->collapse()
                    ->values()
                    ->toArray();
                $dataTransport = $dataPenawaran->detail()
                    ->where('periode_kontrak', $request->periode)
                    ->select('transportasi', 'perdiem_jumlah_orang', 'perdiem_jumlah_hari', 'jumlah_orang_24jam')
                    ->first();

                $dataTransport = [
                    'transportasi' => $dataTransport->transportasi,
                    'perdiem_jumlah_orang' => $dataTransport->perdiem_jumlah_orang,
                    'perdiem_jumlah_hari' => $dataTransport->perdiem_jumlah_hari,
                    'jumlah_orang_24jam' => $dataTransport->jumlah_orang_24jam,
                    'wilayah' => \explode('-', $dataPenawaran->wilayah)[1]
                ];

                //  =========================================COLLECT DATA SELESAI========================================
            } else {
                $dataPenawaran = QuotationNonKontrak::with(['order', 'sampling'])->where('no_document', $request->nomor_quotation)->first();
                $dataOrder = $dataPenawaran->order;

                if ($dataOrder->is_revisi == 1) {
                    return response()->json([
                        'message' => 'Quotation sedang dalam revisi.!',
                    ], 401);
                }
                $dataSampling = $dataPenawaran->sampling;

                $unik_kategori = $dataOrder->orderDetail()->get()->pluck('kategori_3')->unique()->toArray();
                $pra_no_sample = [];
                $kategori_sample = [];

                foreach (\explode(',', $request->kategori) as $kat) {
                    $split = explode(' - ', $kat);
                    $kategori_sample[] = html_entity_decode($split[0]);
                    $pra_no_sample[] = $dataOrder->no_order . '/' . $split[1];
                }
                $pra_no_sample = array_unique($pra_no_sample);
                // dd($unik_kategori, $kategori_sample, $pra_no_sample);
                foreach ($unik_kategori as $kategori) {
                    if($kategori == null) continue;
                    $split = explode('-', $kategori);
                    $id = $split[0];
                    $nama_kategori = $split[1];

                    if (in_array($nama_kategori, $kategori_sample)) {
                        $kategori_sample = array_map(function ($item) use ($id, $nama_kategori) {
                            if ($item == $nama_kategori) {
                                return $id . '-' . $nama_kategori;
                            }
                            return $item;
                        }, $kategori_sample);
                    }
                }
                $dataOrderDetailPerPeriode = $dataOrder->orderDetail()
                    ->select('kategori_3', 'kategori_2', 'regulasi', 'keterangan_1', 'parameter', 'persiapan')
                    ->whereIn('kategori_3', $kategori_sample)
                    ->whereIn('no_sampel', $pra_no_sample)
                    ->where('is_active', 1)
                    ->get()
                    ->groupBy(['kategori_3', 'regulasi', 'parameter'])
                    ->map(function ($kategori3Group) {
                        return $kategori3Group->map(function ($regulasiGroup) {
                            return $regulasiGroup->map(function ($parameterGroup) {
                                $first = $parameterGroup->first();
                                return [
                                    'kategori_3' => \explode('-', $first->kategori_3)[1],
                                    'periode' => NULL,
                                    'kategori_2' => \explode('-', $first->kategori_2)[1],
                                    'regulasi' => $first->regulasi ? array_map(function ($item) {
                                        // dd($item);
                                        // return $item !== "" && explode('-', $item)[1];
                                        return ($item !== "" ? explode('-', $item)[1] : "");
                                    }, json_decode($first->regulasi) ?? []) : [],
                                    'keterangan_1' => $first->keterangan_1,
                                    'parameter' => array_map(function ($item) {
                                        $paramId = explode(';', $item)[0];
                                        $param = Parameter::find($paramId);
                                        return $param ? $param->nama_lab : null;
                                    }, json_decode($first->parameter)),
                                    'persiapan' => ($first->kategori_2 == '1-Air' ? '( ' . number_format((array_sum(array_map(function ($item) {
                                        if (!is_object($item)) return 0;
                                        $persiapan = json_decode($item->persiapan, true);
                                        return $persiapan ? array_sum(array_column($persiapan, 'volume')) : 0;
                                    }, $parameterGroup->toArray())) / 1000), 1) . ' L )' : ''),
                                    'total_parameter' => count(json_decode($first->parameter) ?: []),
                                    'jumlah_titik' => $parameterGroup->count()
                                ];
                            })->values();
                        })->collapse();
                    })->collapse()
                    ->values()
                    ->toArray();


                $dataTransport = $dataPenawaran->select('transportasi', 'perdiem_jumlah_orang', 'perdiem_jumlah_hari', 'jumlah_orang_24jam')
                    ->first();
                $dataTransport = [
                    'transportasi' => $dataTransport->transportasi,
                    'perdiem_jumlah_orang' => $dataTransport->perdiem_jumlah_orang,
                    'perdiem_jumlah_hari' => $dataTransport->perdiem_jumlah_hari,
                    'jumlah_orang_24jam' => $dataTransport->jumlah_orang_24jam,
                    'wilayah' => \explode('-', $dataPenawaran->wilayah)[1]
                ];
                //  =========================================COLLECT DATA SELESAI========================================
            }

            if ($dataPenawaran->status_sampling == 'SD') {
                return response()->json([
                    'message' => 'Sample diantar tidak memiliki STPS.!',
                ], 401);
            }
            //  =========================================CETAK PDF========================================
            $mpdfConfig = [
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 10,
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            ];

            $pdf = new Mpdf($mpdfConfig);
            $pdf->SetProtection(['print'], '', 'skyhwk12');
            $pdf->showWatermarkImage = true;

            $footer = [
                'odd' => [
                    'C' => [
                        'content' => 'Hal {PAGENO} dari {nbpg}',
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#606060'
                    ],
                    'R' => [
                        'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                        'font-size' => 5,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ],
                    'L' => [
                        'font-size' => 4,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ],
                    'line' => -1,
                ]
            ];

            $pdf->setFooter($footer);

            $konsultant = $dataPenawaran->konsultan ? strtoupper($dataPenawaran->konsultan) : '';
            $perusahaan = $dataPenawaran->konsultan ? ' (' . $dataPenawaran->nama_perusahaan . ') ' : $dataPenawaran->nama_perusahaan;

            /* info PIC */
            $nama_pic = $dataPenawaran->nama_pic_sampling .
                ($dataPenawaran->jabatan_pic_sampling ? ' (' . $dataPenawaran->jabatan_pic_sampling . ')' : '(-)') .
                ($dataPenawaran->no_tlp_pic_sampling ? ' - ' . $dataPenawaran->no_tlp_pic_sampling : '');

            $no_bulan = ($request->periode != "" && $request->periode != null) ? explode('-', $request->periode)[1] : 1;

            $no_document = 'ISL/STPS/' . date('y') . '-' . self::romawi(date('m')) . '/' . $dataOrder->no_order . '/' . sprintf('%04d', $no_bulan);
            $tanggal = $request->jadwal;

            $html = '';
            if (str_contains($request->sampler, ',')) {
                $datsa = explode(",", $request->sampler);
                foreach ($datsa as $s => $dat) {
                    $html .= ($s + 1) . '. ' . $dat . '<br>';
                }
            } else {
                $html .= '1. ' . $request->sampler;
            }

            $pdf->SetHTMLHeader('
                <table width="100%">
                    <tr>
                        <td width="60%"></td>
                        <td>
                            <table class="table table-bordered" width="100%">
                                <tr>
                                    <td width="50%" style="text-align: center; font-size: 13px;"><b>No Order</b></td>
                                    <td style="text-align: center; font-size: 13px;"><b>' . $dataOrder->no_order . '</b></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table width="100%">
                    <tr>
                        <td class="text-left text-wrap" style="width: 55%;"></td>
                        <td style="text-align:center">
                            <p style="font-size:14px;"><b><u>SURAT TUGAS PENGAMBILAN SAMPEL</u></b></p>
                            <p style="font-size:11px;text-align:center" id="no_document">' . $no_document . '</p>
                        </td>
                    </tr>
                </table>
                <table style="font-size:13px;font-weight:700;width:100%;margin-top:20px;">
                    <tr>
                        <td>' . $konsultant . $perusahaan . '</td>
                    </tr>
                    <tr>
                        <td width="65%">
                            <p style="font-size:10px">
                                <u>Informasi Sampling :</u><br>
                                <span id="tgl_sampling">' . ($tanggal ? self::tanggal_indonesia($tanggal, 'hari') : 'Belum dijadwalkan') . '</span><br>
                                <span id="alamat_sampling" style="white-space:pre-wrap;word-wrap:break-word;width:50%">' . $dataPenawaran->alamat_sampling . '</span><br>
                                <span id="pic_order">PIC : ' . $nama_pic . '</span>
                            </p>
                        </td>
                        <td style="vertical-align:top;font-size:10px">
                            <u>Petugas Sampling :</u>
                            <div id="petugas_sampling">' . $html . '</div>
                        </td>
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
            $pe = 0;

            foreach ($dataOrderDetailPerPeriode as $key => $value) {
                $value = (object)$value;

                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 12px; padding: 5px;">
                            <b style="font-size: 12px;">' . $value->kategori_3 . '</b><br><hr><b style="font-size: 12px;">' . implode(', ', $value->regulasi) . ' - ' . $value->total_parameter . ' Parameter ' . $value->persiapan .
                        '</b>'
                );
                foreach ($value->parameter as $keys => $parameter) {
                    // dd($parameter);
                    $pdf->WriteHTML(($keys == 0 ? '<br><hr>' : ' &bull; ') . '<span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $parameter . '</span> ');
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 13px; padding: 5px;text-align:center;">' . $value->jumlah_titik . '</td></tr>'
                );
                $i++;
                $pe++;
            }

            if ($dataTransport['transportasi'] > 0) {
                $pdf->WriteHTML('
                    <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . $dataTransport['wilayah'] . '</td>
                        <td style="font-size: 13px; text-align:center;">' . $dataTransport['transportasi'] / $dataTransport['transportasi'] . '</td>
                    </tr>');
            }

            if ($dataTransport['perdiem_jumlah_orang'] > 0) {
                $i++;
                $pdf->WriteHTML('
                    <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Perdiem : ' . $dataTransport['perdiem_jumlah_orang'] . '</td>
                        <td style="font-size: 13px; text-align:center;">' . $dataTransport['perdiem_jumlah_hari'] / $dataTransport['perdiem_jumlah_hari'] . '</td>
                    </tr>');
            }

            $pdf->WriteHTML('</tbody></table>');

            $pdf->WriteHTML('<table width="100%" style="margin-top:10px;">
                <tr>
                    <td width="60%" style="font-size: 10px;">QT : ' . $dataPenawaran->no_document . '</td>
                    <td style="font-size: 10px;text-align:center;">
                        <span>Tangerang, ' . self::tanggal_indonesia(date('Y-m-d')) . '</span><br>
                        <span><b>Manajer Teknis</b></span><br><br><br>
                    </td>
                </tr>
                <tr><td></td><td></td></tr>
                <tr><td></td><td></td></tr>
                <tr><td></td><td></td></tr>
                <tr><td></td><td></td></tr>
                <tr><td></td><td></td></tr>
                <tr><td></td><td></td></tr>
                <tr>
                    <td style="font-size: 10px;">Waktu tiba di lokasi : ' . $request->jadwal_jam_mulai . '</td>
                    <td style="font-size: 10px;text-align:center;">&nbsp;&nbsp;&nbsp;(..............................................)</td>
                </tr>
            </table>');

            $fileName = str_replace('/', '-', $no_document) . '.pdf';

            $pdf->Output(public_path() . '/stps/' . $fileName, 'F');

            return $fileName;
            return response($pdf->Output('', 'I'));
        } catch (\Throwable $th) {
            dd($th);
            return response()->json([
                'message' => 'Data STPS tidak bisa dicetak karena data tidak sinkron dengan data jadwal.!',
            ], 500);
        }
    }

    // public function cetakPDFSTPS(Request $request)
    // {
    //     // dd($request->all());
    //     // setup data
    //     $tempTKategori = explode(',', $request->kategori);
    //     /* step buat no sample */
    //     $noKateg=[];
    //     foreach ($tempTKategori as $key => $value) {
    //        array_push($noKateg,explode(' - ', $value)[1]);
    //     }
    //     $noKateg = array_unique($noKateg);
    //     $noSample = [];
    //     foreach ($noKateg as $key => $value) {
    //         array_push($noSample, $request->no_order.'/'.$value);
    //     }


    //     if($request->periode != "" && $request->periode != null) {
    //         try {
    //             //code...
    //             /* step 2 cari kategori berdasarkan no quotation */
    //             /* step 2 cari kategori berdasarkan no quotation */
    //             $Qt = QuotationKontrakH::with(['detail'])->where('no_document', $request->nomor_quotation)
    //                 ->where('is_active', true)
    //                 ->where('flag_status', 'ordered')
    //                 ->first();
    //             $QtOrder = OrderHeader::with(['orderDetail' => function($query) use($noSample) {
    //                 $query->whereIn('no_sampel', $noSample)
    //                     ->where('is_active', true);
    //             },'persiapanSampel'])->where('no_document', $request->nomor_quotation)->first();
    //             $qTDetail =$Qt->detail->where('periode_kontrak',$request->periode)->first();

    //             if($QtOrder->is_revisi == true){
    //                 return response()->json([
    //                     'message' => 'Data STPS tidak bisa dicetak karena data order sedang direvisi.!',
    //                 ], 401);
    //             }



    //             // if(!$QtOrder->persiapanSampel){
    //             //     return response()->json(['message' => 'Data STPS tidak bisa dicetak karena data persiapan belum di siapkan.!'], 401);
    //             // }


    //             // ambil data sampling
    //             $decodeJson =html_entity_decode($qTDetail->data_pendukung_sampling);
    //             $decodeJson=json_decode($decodeJson,true);
    //             $dataSampling =array_values($decodeJson);
    //             $filterValidKategori = array_filter($dataSampling, function ($item) use ($QtOrder) {
    //                 $validDataSampling = [];

    //                 // Iterasi pada setiap `data_sampling`
    //                 foreach ($item['data_sampling'] as $sp) {
    //                     foreach ($QtOrder->orderDetail as $orderDetail) {
    //                         // Cek kecocokan periode kontrak
    //                         if ($orderDetail['periode'] === $item['periode_kontrak']) {
    //                             // Decode JSON pada regulasi dan parameter
    //                             $detailRegulasi = is_string($orderDetail['regulasi']) ? json_decode($orderDetail['regulasi'], true) : $orderDetail['regulasi'];
    //                             $detailParam = is_string($orderDetail['parameter']) ? json_decode($orderDetail['parameter'], true) : $orderDetail['parameter'];

    //                             $itemRegulasi = is_string($sp['regulasi']) ? json_decode($sp['regulasi'], true) : $sp['regulasi'];
    //                             $itemParam = is_string($sp['parameter']) ? json_decode($sp['parameter'], true) : $sp['parameter'];

    //                             // Filter hanya untuk kategori "5-Emisi"
    //                             if (
    //                                 $sp['kategori_1'] === $orderDetail['kategori_2'] && // Pastikan kategori_1 adalah "5-Emisi"
    //                                 $sp['kategori_2'] === $orderDetail['kategori_3'] &&
    //                                 $itemRegulasi == $detailRegulasi &&
    //                                 $itemParam == $detailParam
    //                             ) {

    //                                 $validDataSampling[] = $sp;
    //                                 break;
    //                             }
    //                         }
    //                     }
    //                 }

    //                 // Jika ada data sampling yang valid, update item dan return
    //                 if (!empty($validDataSampling)) {
    //                     $item['data_sampling'] = $validDataSampling;
    //                     return true; // Kembalikan item yang valid
    //                 }

    //                 return false; // Data tidak valid, keluarkan dari filter
    //             });



    //             // dd($filterValidKategori);
    //         } catch (\Exception $e) {
    //             //throw $th;
    //             throw new \Exception("pengolahan data terjadi kesalahan: " . $e->getMessage(). " Line: " . $e->getLine());
    //         }
    //         //

    //     } else {

    //         /* step 2 cari kategori berdasarkan no quotation */
    //         $Qt = QuotationNonKontrak::where('no_document', $request->nomor_quotation)
    //             ->where('is_active', true)
    //             ->where('flag_status', 'ordered')
    //             ->first();
    //         $QtOrder = OrderHeader::with(['orderDetail' => function($query) use($noSample) {
    //             $query->whereIn('no_sampel', $noSample)
    //                 ->where('is_active', true);
    //         },'persiapanSampel'])->where('no_document', $request->nomor_quotation)->first();

    //         if($QtOrder->is_revisi == true){
    //             return response()->json([
    //                 'message' => 'Data STPS tidak bisa dicetak karena data order sedang direvisi.!',
    //             ], 401);
    //         }



    //         // if(!$QtOrder->persiapanSampel){
    //         //     return response()->json([
    //         //         'message' => 'Data STPS tidak bisa dicetak karena data persiapan belum di siapkan.!',
    //         //     ], 401);
    //         // }


    //         $dataSampling = json_decode($Qt->data_pendukung_sampling,true);
    //         $filterValidKategori = array_filter($dataSampling, function($item) use($QtOrder) {
    //             // Loop through each orderDetail to check kategori match
    //             foreach ($QtOrder->orderDetail as $detail) {
    //                 $itemRegulasi = is_string($item['regulasi']) ? json_decode($item['regulasi'], true) : $item['regulasi'];
    //                 $detailRegulasi = is_string($detail->regulasi) ? json_decode($detail->regulasi, true) : $detail->regulasi;
    //                 $param = is_string($item['parameter']) ? json_decode($item['parameter'], true) : $item['parameter'];
    //                 $detailParam = is_string($detail->parameter) ? json_decode($detail->parameter, true) : $detail->parameter;

    //                 if ($item['kategori_1'] == $detail->kategori_2 &&
    //                     $item['kategori_2'] == $detail->kategori_3 &&
    //                     $itemRegulasi == $detailRegulasi &&
    //                     $param == $detailParam) {
    //                     return true;
    //                 }
    //             }
    //             return false;
    //         });

    //         $filterValidKategori = array_values($filterValidKategori);
    //     }


    //     // set Pdf

    //     $mpdfConfig = [
    //         'mode' => 'utf-8',
    //         'format' => 'A4',
    //         'margin_header' => 10,
    //         'margin_footer' => 3,
    //         'setAutoTopMargin' => 'stretch',
    //         'setAutoBottomMargin' => 'stretch',
    //         'orientation' => 'P'
    //     ];

    //     $pdf = new Mpdf($mpdfConfig);
    //     // $pdf->SetProtection(['print'], '', 'skyhwk12');
    //     $pdf->showWatermarkImage = true;

    //     $footer = [
    //         'odd' => [
    //             'C' => [
    //                 'content' => 'Hal {PAGENO} dari {nbpg}',
    //                 'font-size' => 6,
    //                 'font-style' => 'I',
    //                 'font-family' => 'serif',
    //                 'color' => '#606060'
    //             ],
    //             'R' => [
    //                 'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
    //                 'font-size' => 5,
    //                 'font-style' => 'I',
    //                 'font-family' => 'serif',
    //                 'color' => '#000000'
    //             ],
    //             'L' => [
    //                 'font-size' => 4,
    //                 'font-style' => 'I',
    //                 'font-family' => 'serif',
    //                 'color' => '#000000'
    //             ],
    //             'line' => -1,
    //         ]
    //     ];

    //     $pdf->setFooter($footer);
    //     // $fileName = Helpers::escapeStr('STPS_' . $data_qt->no_document . '_' . $data_qt->nama_perusahaan) . '.pdf';

    //     try {

    //         $konsultant = $request->konsultan ? strtoupper($request->konsultan) : '';
    //         $perusahaan = $request->konsultan ? ' (' . $request->nama_perusahaan . ') ' : $request->nama_perusahaan;

    //         /* info PIC */
    //         $infoPic =html_entity_decode($request->info_pendukung);
    //         $infoPic = json_decode($infoPic,true);
    //         $nama_pic = $infoPic['nama_pic_sampling'] .
    //                     ($infoPic['jabatan_pic_sampling'] ? ' (' . $infoPic['jabatan_pic_sampling'] . ')' : '(-)') .
    //                     ($infoPic['no_tlp_pic_sampling'] ? ' - ' . $infoPic['no_tlp_pic_sampling'] : '');


    //         $tipe_kont = ($request->periode != "" && $request->periode != null) ? explode('-', $request->periode)[1] : 1;

    //         if ($Qt->status_sampling != 'SD') {

    //             $html = '';
    //             $no_document = 'ISL/STPS/' . date('y') . '-' . self::romawi(date('m')) . '/' . $request->no_order . '/' . sprintf('%04d', $tipe_kont);
    //             $tanggal = $request->jadwal;

    //             if (str_contains($request->sampler, ',')) {
    //                 $datsa = explode(",", $request->sampler);
    //                 foreach ($datsa as $s => $dat) {
    //                     $html .= ($s + 1) . '. ' . $dat . '<br>';
    //                 }
    //             } else {
    //                 $html .= '1. ' . $request->sampler;
    //             }

    //             $pdf->SetHTMLHeader('
    //                 <table width="100%">
    //                     <tr>
    //                         <td width="60%"></td>
    //                         <td>
    //                             <table class="table table-bordered" width="100%">
    //                                 <tr>
    //                                     <td width="50%" style="text-align: center; font-size: 13px;"><b>No Order</b></td>
    //                                     <td style="text-align: center; font-size: 13px;"><b>' . $request->no_order . '</b></td>
    //                                 </tr>
    //                             </table>
    //                         </td>
    //                     </tr>
    //                 </table>
    //                 <table width="100%">
    //                     <tr>
    //                         <td class="text-left text-wrap" style="width: 55%;"></td>
    //                         <td style="text-align:center">
    //                             <p style="font-size:14px;"><b><u>SURAT TUGAS PENGAMBILAN SAMPEL</u></b></p>
    //                             <p style="font-size:11px;text-align:center" id="no_document">' . $no_document . '</p>
    //                         </td>
    //                     </tr>
    //                 </table>
    //                 <table style="font-size:13px;font-weight:700;width:100%;margin-top:20px;">
    //                     <tr>
    //                         <td>' . $konsultant . $perusahaan . '</td>
    //                     </tr>
    //                     <tr>
    //                         <td width="65%">
    //                             <p style="font-size:10px">
    //                                 <u>Informasi Sampling :</u><br>
    //                                 <span id="tgl_sampling">' . ($tanggal ? self::tanggal_indonesia($tanggal, 'hari') : 'Belum dijadwalkan') . '</span><br>
    //                                 <span id="alamat_sampling" style="white-space:pre-wrap;word-wrap:break-word;width:50%">' . $request->alamat_sampling . '</span><br>
    //                                 <span id="pic_order">PIC : ' . $nama_pic . '</span>
    //                             </p>
    //                         </td>
    //                         <td style="vertical-align:top;font-size:10px">
    //                             <u>Petugas Sampling :</u>
    //                             <div id="petugas_sampling">' . $html . '</div>
    //                         </td>
    //                     </tr>
    //                 </table>
    //             ');

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
    //             $pe = 0;
    //             $tempRegulasi=[];
    //             $tempKategori=[];
    //             // dd($filterValidKategori);
    //             foreach ($filterValidKategori as $key => $value) {
    //                 if(isset($value['periode_kontrak'])){
    //                     // foreach($value['data_sampling'] as $keya => $data_satuan){
    //                     //     array_push($tempKategori,explode('-', $data_satuan['kategori_2'])[1]);
    //                     // }

    //                     $periode = explode('-', $value['periode_kontrak'])[1];
    //                     foreach($value['data_sampling'] as $keya => $valuea){
    //                         foreach ($valuea['regulasi'] as $keyb => $avlueb) {
    //                             array_push($tempRegulasi,explode('-', $avlueb)[1]);
    //                         }
    //                         $pdf->WriteHTML(
    //                             '<tr>
    //                                 <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
    //                                 <td style="font-size: 12px; padding: 5px;">
    //                                     <b style="font-size: 12px;">'. explode('-', $valuea['kategori_2'])[1] .'</b><br><hr><b style="font-size: 12px;">' . implode(', ',$tempRegulasi) . ' - ' . $valuea['total_parameter'] . ' Parameter ' .
    //                                 ($valuea['kategori_1'] == '1-Air' ? '(' . number_format(($valuea['volume'] / 1000), 1) . ' L)' : '') .
    //                                 '</b>'
    //                         );
    //                         foreach ($valuea['parameter'] as $keys => $valuess) {
    //                             $conn_param = new Parameter;
    //                             $dParam = explode(';', $valuess);
    //                             $p = $conn_param->where('id', $dParam[0])->where('is_active', true)->first();
    //                             if ($p) {
    //                                 $pdf->WriteHTML(($keys == 0 ? '<br><hr>' : ' &bull; ') . '<span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $p->nama_regulasi . '</span> ');
    //                             }
    //                         }
    //                         $pdf->WriteHTML(
    //                             '<td style="font-size: 13px; padding: 5px;text-align:center;">' . $valuea['jumlah_titik'] . '</td></tr>'
    //                         );
    //                         $i++;
    //                         $pe++;
    //                     }
    //                 }else{
    //                     // dd($value);
    //                     foreach ($value['regulasi'] as $keyReg => $val) {
    //                         array_push($tempRegulasi,explode('-', $val)[1]);
    //                     }
    //                     array_push($tempKategori,explode('-', $value['kategori_2'])[1]);
    //                     $pdf->WriteHTML(
    //                         '<tr>
    //                             <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
    //                             <td style="font-size: 12px; padding: 5px;">
    //                                 <b style="font-size: 12px;">'.$tempKategori[$key].'</b><br><hr></br><b style="font-size: 12px;">' . implode(', ',$tempRegulasi) . ' - ' . $value['total_parameter'] . ' Parameter ' .
    //                                 ($value['kategori_1'] == '1-Air' ? '(' . number_format(($value['volume'] / 1000), 1) . ' L)' : '') .
    //                                 '</b>'
    //                     );

    //                     foreach ($value['parameter'] as $keys => $valuess) {
    //                         $conn_param = new Parameter;
    //                         $dParam = explode(';', $valuess);
    //                         $p = $conn_param->where('id', $dParam[0])->where('is_active', true)->first();
    //                         if ($p) {
    //                             $pdf->WriteHTML(($keys == 0 ? '<br><hr>' : ' &bull; ') . '<span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $p->nama_regulasi . '</span> ');
    //                         }
    //                     }

    //                     $pdf->WriteHTML(
    //                         '<td style="font-size: 13px; padding: 5px;text-align:center;">' . $value['jumlah_titik'] . '</td></tr>'
    //                     );
    //                 }
    //                 $i++;
    //                 $pe++;
    //             }

    //             if($request->periode != "" && $request->periode != null){

    //                 if ($qTDetail->transportasi > 0) {
    //                     $pdf->WriteHTML('
    //                         <tr>
    //                             <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
    //                             <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $Qt->wilayah)[1] . '</td>
    //                             <td style="font-size: 13px; text-align:center;">' . $qTDetail->transportasi / $qTDetail->transportasi . '</td>
    //                         </tr>');
    //                 }

    //                 if ($qTDetail->perdiem_jumlah_orang > 0) {
    //                     $i++;
    //                     $pdf->WriteHTML('
    //                         <tr>
    //                             <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
    //                             <td style="font-size: 13px;padding: 5px;">Perdiem : ' . $qTDetail->perdiem_jumlah_orang . '</td>
    //                             <td style="font-size: 13px; text-align:center;">' . $qTDetail->perdiem_jumlah_hari / $qTDetail->perdiem_jumlah_hari . '</td>
    //                         </tr>');
    //                 }
    //             }else{
    //                 if ($Qt->transportasi > 0) {
    //                     $pdf->WriteHTML('
    //                         <tr>
    //                             <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
    //                             <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $Qt->wilayah)[1] . '</td>
    //                             <td style="font-size: 13px; text-align:center;">' . $Qt->transportasi / $Qt->transportasi . '</td>
    //                         </tr>');
    //                 }

    //                 if ($Qt->perdiem_jumlah_orang > 0) {
    //                     $i++;
    //                     $pdf->WriteHTML('
    //                         <tr>
    //                             <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
    //                             <td style="font-size: 13px;padding: 5px;">Perdiem : ' . $Qt->perdiem_jumlah_orang . '</td>
    //                             <td style="font-size: 13px; text-align:center;">' . $Qt->perdiem_jumlah_hari / $Qt->perdiem_jumlah_hari . '</td>
    //                         </tr>');
    //                 }
    //             }


    //             $pdf->WriteHTML('</tbody></table>');

    //             $pdf->WriteHTML('<table width="100%" style="margin-top:10px;">
    //                 <tr>
    //                     <td width="60%" style="font-size: 10px;">QT : ' . $Qt->no_document . '</td>
    //                     <td style="font-size: 10px;text-align:center;">
    //                         <span>Tangerang, ' . self::tanggal_indonesia(date('Y-m-d')) . '</span><br>
    //                         <span><b>Manajer Teknis</b></span><br><br><br>
    //                     </td>
    //                 </tr>
    //                 <tr><td></td><td></td></tr>
    //                 <tr><td></td><td></td></tr>
    //                 <tr><td></td><td></td></tr>
    //                 <tr><td></td><td></td></tr>
    //                 <tr><td></td><td></td></tr>
    //                 <tr><td></td><td></td></tr>
    //                 <tr>
    //                     <td style="font-size: 10px;">Waktu tiba di lokasi : ' . $request->jadwal_jam_mulai . '</td>
    //                     <td style="font-size: 10px;text-align:center;">&nbsp;&nbsp;&nbsp;(..............................................)</td>
    //                 </tr>
    //             </table>');
    //         }
    //     } catch (\Exception $e) {
    //         throw new \Exception("Terjadi kesalahan saat mencetak PDF: " . $e->getMessage(). " Line: " . $e->getLine());
    //     }


    //     $fileName = 'ISL-STPS-' . date('y') . '-' . self::romawi(date('m')) . '-' . $request->no_order . '-' . sprintf('%04d', $tipe_kont).'.pdf';

    //     $pdf->Output(public_path() . '/stps/' . $fileName, 'F');

    //     return $fileName;
    //     // return response($pdf->Output('','I'));
    //     // $pdf->Output('stps/' . $fileName);
    //     // return $fileName;
    // }


    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }

    public function tanggal_indonesia($tanggal, $mode = '')
    {
        $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $hari_map = ['Sun' => 'Minggu', 'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => "Jum'at", 'Sat' => 'Sabtu'];

        $hari = $hari_map[date('D', strtotime($tanggal))];
        $var = explode('-', $tanggal);

        if ($mode == 'period') return $bulan[(int) $var[1]] . ' ' . $var[0];
        if ($mode == 'hari') return $hari . ' / ' . $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];

        return $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
    }
}
