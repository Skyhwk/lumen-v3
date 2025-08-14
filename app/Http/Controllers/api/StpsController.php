<?php

namespace App\Http\Controllers\api;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Models\PersiapanSampelHeader;
use App\Models\QrDocument;

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

            if ($interval->days > 91)
                return response()->json(['message' => 'Periode tidak boleh lebih dari 1 bulan'], 403);

            $data = OrderDetail::with([
                'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi',
                'orderHeader.samplingPlan',
                'orderHeader.samplingPlan.jadwal' => function ($q) {
                    $q->select(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'periode', 'id_cabang', DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')])
                        ->where('is_active', true)
                        ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'periode', 'id_cabang']);
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
                if (empty($item['order_header']) || empty($item['order_header']['sampling']))
                    return $carry;

                $samplingPlan = $item['order_header']['sampling'];
                $periode = $item['periode'] ?? '';

                $targetPlan = $periode ?
                    current(array_filter(
                        $samplingPlan,
                        fn($plan) =>
                        isset($plan['periode_kontrak']) && $plan['periode_kontrak'] == $periode
                    )) :
                    current($samplingPlan);
                if (!$targetPlan)
                    return $carry;

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
                            'is_revisi' => $item['order_header']['is_revisi'],
                            'nama_cabang' => isset($schedule['id_cabang']) ? (
                                $schedule['id_cabang'] == 4 ? 'RO-KARAWANG' : ($schedule['id_cabang'] == 5 ? 'RO-PEMALANG' : ($schedule['id_cabang'] == 1 ? 'HEAD OFFICE' : 'UNKNOWN'))
                            ) : 'HEAD OFFICE (Default)',
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
                    $item['nama_cabang'] ?? '',
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
                        'is_revisi' => $item['is_revisi'],
                        'nama_cabang' => $item['nama_cabang'] ?? '',
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
                                        'jabatan_pic_sampling' => $vv->jabatan_pic_sampling,
                                        'jabatan_pic_order' => $vv->jabatan_pic_order,
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
                    ->where('is_active', true)->get()->pluck('kategori_3')->unique()->toArray();
                $pra_no_sample = [];
                $kategori_sample = [];
                foreach (\explode(',', $request->kategori) as $kat) {
                    $split = explode(' - ', $kat);
                    $kategori_sample[] = html_entity_decode($split[0]);
                    $pra_no_sample[] = $dataOrder->no_order . '/' . $split[1];
                }
                $pra_no_sample = array_unique($pra_no_sample);
                // dd($pra_no_sample);
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

                $getPeriodeSampling = array_filter($dataSampling->toArray(), function ($item) use ($request) {
                    return $item['periode_kontrak'] == $request->periode;
                });

                $dataSampling = array_values($getPeriodeSampling)[0]['jadwal'];
                foreach ($dataSampling as $key => $value) {
                    unset($dataSampling[$key]['id']);
                    unset($dataSampling[$key]['nama_perusahaan']);
                    unset($dataSampling[$key]['wilayah']);
                    unset($dataSampling[$key]['alamat']);
                    unset($dataSampling[$key]['tanggal']);
                    unset($dataSampling[$key]['periode']);
                    unset($dataSampling[$key]['jam']);
                    unset($dataSampling[$key]['jam_mulai']);
                    unset($dataSampling[$key]['jam_selesai']);
                    unset($dataSampling[$key]['kategori']);
                    unset($dataSampling[$key]['sampler']);
                    unset($dataSampling[$key]['userid']);
                    unset($dataSampling[$key]['driver']);
                    unset($dataSampling[$key]['warna']);
                    unset($dataSampling[$key]['note']);
                    unset($dataSampling[$key]['durasi']);
                    unset($dataSampling[$key]['status']);
                    unset($dataSampling[$key]['flag']);
                    unset($dataSampling[$key]['created_by']);
                    unset($dataSampling[$key]['created_at']);
                    unset($dataSampling[$key]['updated_by']);
                    unset($dataSampling[$key]['updated_at']);
                    unset($dataSampling[$key]['canceled_by']);
                    unset($dataSampling[$key]['canceled_at']);
                    unset($dataSampling[$key]['notif']);
                    unset($dataSampling[$key]['urutan']);
                    unset($dataSampling[$key]['kendaraan']);
                }

                $dataSampling = array_values(array_filter(array_unique($dataSampling, SORT_REGULAR), function ($item) {
                    return isset($item['is_active']) && $item['is_active'] == 1;
                }));
                // dd($dataSampling);
                if (count($dataSampling) > 1) {
                    // jika data jadwalnya parsial
                    $dataOrderDetailPerPeriode = $dataOrder->orderDetail()
                        ->select('kategori_3', 'periode', 'kategori_1', 'kategori_2', 'regulasi', 'keterangan_1', 'parameter', 'persiapan', 'no_sampel')
                        ->where('periode', $request->periode)
                        ->whereIn('kategori_3', $kategori_sample)
                        ->whereIn('no_sampel', $pra_no_sample)
                        ->where('kategori_1', '!=', 'SD')
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
                                    // dd($parameterGroup);
                                    return [
                                        'kategori_3' => \explode('-', $parameterGroup->first()->kategori_3)[1],
                                        'kategori_1' => $parameterGroup->first()->kategori_1,
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
                                            if (!is_object($item))
                                                return 0;
                                            $persiapan = json_decode($item->persiapan, true);
                                            return $persiapan ? array_sum(array_column($persiapan, 'volume')) : 0;
                                        }, $parameterGroup->toArray())) / 1000), 1) . ' L )' : ''),
                                        'total_parameter' => count(json_decode($parameterGroup->first()->parameter) ?: []),
                                        'jumlah_titik' => $jumlahTitik,
                                        'no_sampel' => $parameterGroup->pluck('no_sampel')->toArray(),
                                    ];
                                })->values();
                            })->collapse();
                        })->collapse()
                        ->values()
                        ->toArray();


                    usort($dataOrderDetailPerPeriode, function ($a, $b) {
                        // Ambil angka terakhir dari no_sampel, misalnya dari "ISDI012502/021" ambil 021 → jadi 21
                        preg_match('/\/(\d+)$/', $a['no_sampel'][0], $matchesA);
                        preg_match('/\/(\d+)$/', $b['no_sampel'][0], $matchesB);

                        $numA = isset($matchesA[1]) ? (int) $matchesA[1] : 0;
                        $numB = isset($matchesB[1]) ? (int) $matchesB[1] : 0;

                        return $numA <=> $numB; // Ascending order
                    });
                } else {
                    $data_detail_penawaran = json_decode($dataPenawaran->detail()->where('periode_kontrak', $request->periode)->first()->data_pendukung_sampling, true);
                    $data_detail_penawaran = array_map(function ($item) use ($dataOrder, $pra_no_sample) {
                        $maping = array_map(function ($data_sampling) use ($item, $dataOrder, $pra_no_sample) {
                            $data = [
                                'kategori_3' => \explode('-', $data_sampling['kategori_2'])[1],
                                'periode' => $item['periode_kontrak'],
                                'kategori_2' => \explode('-', $data_sampling['kategori_1'])[1],
                                'regulasi' => (!empty($data_sampling['regulasi']) && is_array($data_sampling['regulasi'])) ? array_map(function ($item) {
                                    return explode('-', $item)[1] ?? ''; // pakai null coalescing untuk mencegah undefined offset
                                }, $data_sampling['regulasi']) : [],
                                'keterangan_1' => $data_sampling['penamaan_titik'],
                                'parameter' => array_map(function ($parameter) {
                                    return \explode(';', $parameter)[1];
                                }, $data_sampling['parameter']),
                                'persiapan' => isset($data_sampling['volume']) && !empty($data_sampling['volume']) ?
                                    '( ' . number_format($data_sampling['volume'] / 1000, 1) . ' L )' : '',
                                'total_parameter' => $data_sampling['total_parameter'],
                                'jumlah_titik' => $data_sampling['jumlah_titik'],
                                'no_sampel' => $dataOrder->orderDetail()
                                    ->where('kategori_3', $data_sampling['kategori_2'])
                                    ->where('kategori_2', $data_sampling['kategori_1'])
                                    ->whereJsonContains('regulasi', $data_sampling['regulasi'])
                                    ->whereJsonContains('parameter', $data_sampling['parameter'])
                                    ->where('periode', $item['periode_kontrak'])
                                    ->whereIn('no_sampel', $pra_no_sample)
                                    ->where('kategori_1', '!=', 'SD')
                                    ->where('is_active', 1)
                                    ->pluck('no_sampel')->toArray(),
                            ];//8298

                            return $data;
                        }, $item['data_sampling']);

                        return $maping;
                    }, $data_detail_penawaran);

                    $data_detail_penawaran = array_values($data_detail_penawaran)[0];

                    $dataOrderDetailPerPeriode = array_values($data_detail_penawaran);
                }

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
                // dd($unik_kategori);
                $pra_no_sample = [];
                $kategori_sample = [];

                foreach (\explode(',', $request->kategori) as $kat) {
                    $split = explode(' - ', $kat);
                    $kategori_sample[] = html_entity_decode($split[0]);
                    $pra_no_sample[] = $dataOrder->no_order . '/' . $split[1];
                }
                $pra_no_sample = array_unique($pra_no_sample);
                // dd($pra_no_sample);
                // dd($unik_kategori, $kategori_sample, $pra_no_sample);
                foreach ($unik_kategori as $kategori) {
                    if ($kategori == null)
                        continue;
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

                $dataSampling = array_values($dataSampling->toArray())[0]['jadwal'];
                // dd($dataSampling);
                foreach ($dataSampling as $key => $value) {
                    unset($dataSampling[$key]['id']);
                    unset($dataSampling[$key]['nama_perusahaan']);
                    unset($dataSampling[$key]['wilayah']);
                    unset($dataSampling[$key]['alamat']);
                    unset($dataSampling[$key]['tanggal']);
                    unset($dataSampling[$key]['periode']);
                    unset($dataSampling[$key]['jam']);
                    unset($dataSampling[$key]['jam_mulai']);
                    unset($dataSampling[$key]['jam_selesai']);
                    unset($dataSampling[$key]['kategori']);
                    unset($dataSampling[$key]['sampler']);
                    unset($dataSampling[$key]['userid']);
                    unset($dataSampling[$key]['driver']);
                    unset($dataSampling[$key]['warna']);
                    unset($dataSampling[$key]['note']);
                    unset($dataSampling[$key]['durasi']);
                    unset($dataSampling[$key]['status']);
                    unset($dataSampling[$key]['flag']);
                    unset($dataSampling[$key]['created_by']);
                    unset($dataSampling[$key]['created_at']);
                    unset($dataSampling[$key]['updated_by']);
                    unset($dataSampling[$key]['updated_at']);
                    unset($dataSampling[$key]['canceled_by']);
                    unset($dataSampling[$key]['canceled_at']);
                    unset($dataSampling[$key]['notif']);
                    unset($dataSampling[$key]['urutan']);
                    unset($dataSampling[$key]['kendaraan']);
                }
                // dd($dataSampling);
                $dataSampling = array_values(array_filter(array_unique($dataSampling, SORT_REGULAR), function ($item) {
                    return isset($item['is_active']) && $item['is_active'] == 1;
                }));
                // dd($dataSampling);
                if (count($dataSampling) > 1) {
                    // jika data jadwalnya parsial
                    // dd($pra_no_sample);
                    $dataOrderDetailPerPeriode = $dataOrder->orderDetail()
                        ->select('no_sampel','kategori_3', 'kategori_2', 'kategori_1', 'regulasi', 'keterangan_1', 'parameter', 'persiapan')
                        ->whereIn('kategori_3', $kategori_sample)
                        ->whereIn('no_sampel', $pra_no_sample)
                        ->where('kategori_1', '!=', 'SD')
                        ->where('is_active', 1)
                        ->orderBy('no_sampel')
                        ->get()
                        ->groupBy(['kategori_3', 'regulasi', 'parameter'])
                        ->map(function ($kategori3Group) {
                            return $kategori3Group->map(function ($regulasiGroup) {
                                return $regulasiGroup->map(function ($parameterGroup) {
                                    $first = $parameterGroup->first();

                                    return [
                                        'kategori_3' => \explode('-', $first->kategori_3)[1],
                                        'kategori_1' => $parameterGroup->first()->kategori_1,
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
                                            if (!is_object($item))
                                                return 0;
                                            $persiapan = json_decode($item->persiapan, true);
                                            return $persiapan ? array_sum(array_column($persiapan, 'volume')) : 0;
                                        }, $parameterGroup->toArray())) / 1000), 1) . ' L )' : ''),
                                        'total_parameter' => count(json_decode($first->parameter) ?: []),
                                        'jumlah_titik' => $parameterGroup->count(),
                                        'no_sampel' => $parameterGroup->pluck('no_sampel')->toArray()
                                    ];
                                })->values();
                            })->collapse();
                        })->collapse()
                        ->values()
                        ->toArray();

                    // dd($dataOrderDetailPerPeriode);

                    usort($dataOrderDetailPerPeriode, function ($a, $b) {
                        // Ambil angka terakhir dari no_sampel, misalnya dari "ISDI012502/021" ambil 021 → jadi 21
                        preg_match('/\/(\d+)$/', $a['no_sampel'][0], $matchesA);
                        preg_match('/\/(\d+)$/', $b['no_sampel'][0], $matchesB);

                        $numA = isset($matchesA[1]) ? (int) $matchesA[1] : 0;
                        $numB = isset($matchesB[1]) ? (int) $matchesB[1] : 0;

                        return $numA <=> $numB; // Ascending order
                    });
                    // dd('stop', $dataOrderDetailPerPeriode, $kategori_sample);
                } else {
                    $data_detail_penawaran = json_decode($dataPenawaran->data_pendukung_sampling, true);
                    // dd($dataPenawaran, 'test');
                    $data_detail_penawaran = array_map(function ($data_sampling) use ($dataOrder, $pra_no_sample) {
                        
                        return [
                            'kategori_3' => \explode('-', $data_sampling['kategori_2'])[1],
                            'periode' => NULL,
                            'kategori_2' => \explode('-', $data_sampling['kategori_1'])[1],
                            'regulasi' => $data_sampling['regulasi'] ? array_map(function ($item) {
                                return explode('-', $item)[1];
                            }, $data_sampling['regulasi']) : [],
                            'keterangan_1' => $data_sampling['penamaan_titik'],
                            'parameter' => array_map(function ($parameter) {
                                return \explode(';', $parameter)[1];
                            }, $data_sampling['parameter']),
                            'persiapan' => isset($data_sampling['volume']) && !empty($data_sampling['volume']) ?
                                '( ' . number_format($data_sampling['volume'] / 1000, 1) . ' L )' : '',
                            'total_parameter' => $data_sampling['total_parameter'],
                            'jumlah_titik' => $data_sampling['jumlah_titik'],
                            'no_sampel' => $dataOrder->orderDetail()
                                ->where('kategori_3', $data_sampling['kategori_2'])
                                ->where('kategori_2', $data_sampling['kategori_1'])
                                ->whereJsonContains('regulasi', $data_sampling['regulasi'])
                                ->whereJsonContains('parameter', $data_sampling['parameter'])
                                // ->where('periode', $item['periode_kontrak'])
                                ->whereIn('no_sampel', $pra_no_sample)
                                ->where('kategori_1', '!=', 'SD')
                                ->where('is_active', 1)
                                ->pluck('no_sampel')->toArray(),
                        ];
                    }, $data_detail_penawaran);
                    
                    $data_detail_penawaran = array_values($data_detail_penawaran);

                    $dataOrderDetailPerPeriode = $data_detail_penawaran;
                }
                // dd($dataOrder->orderDetail()->get());
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
            // $psh = PersiapanSampelHeader::where('no_quotation', $request->nomor_quotation)
            //     ->where('no_order', $dataOrder->no_order)
            //     // ->where('periode', $request->periode)
            //     ->where('tanggal_sampling', $request->jadwal)
            //     ->whereJsonContains('no_sampel', $pra_no_sample[0])
            //     ->first();





            // $dataList = PersiapanSampelHeader::where('no_quotation', $request->nomor_quotation)
            //     ->where('no_order', $dataOrder->no_order)
            //     ->where('tanggal_sampling', $request->jadwal)
            //     ->where('is_active', 1)
            //     ->get();

            // $psh = $dataList->first(function ($item) use ($pra_no_sample) {
            //     $noSampel = json_decode($item->no_sampel, true) ?? [];
            //     return count(array_intersect($noSampel, $pra_no_sample)) > 0;
            // });


            // if (!$psh) {
            //     if (file_exists(public_path() . '/stps')) {
            //     }

            //     return response()->json([
            //         'message' => 'Sampel belum disiapkan, Silahkan melakukan update terlebih dahulu.!',
            //     ], 401);
            // }

            // dd($pra_no_sample);

            $psController = new PersiapanSampleController($request);
            $pshModel = PersiapanSampelHeader::class;

            $psHeader = $pshModel::where('no_quotation', $request->nomor_quotation)
                ->where('no_order', $dataOrder->no_order)
                ->where('tanggal_sampling', $request->jadwal);
            // ->whereJsonContains('no_sampel', $pra_no_sample);

            if ($request->periode) $psHeader = $psHeader->where('periode', $request->periode);

            $psHeader = $psHeader->first();

            if (!$psHeader) {
                $request->no_document = $request->nomor_quotation;
                // $request->no_order = $request->no_order;
                $request->no_sampel = $pra_no_sample;
                // $request->periode = $request->periode;

                $response = $psController->preview($request);
                $preview = json_decode($response->getContent(), true);

                $isMustPrepared = false;
                foreach (['air', 'udara', 'emisi', 'padatan'] as $kategori) {
                    foreach ($preview[$kategori] as $sampel) {
                        if (isset($sampel['no_sampel'])) {
                            $isMustPrepared = true;
                            break;
                        };
                    }
                }

                if (!$isMustPrepared) {
                    return response()->json(['message' => 'Sampel belum disiapkan, Silahkan melakukan update terlebih dahulu.!'], 401);
                } else {
                    $requestPsData = new Request([
                        'no_order' => $request->no_order,
                        'no_quotation' => $request->no_document,
                        'tanggal_sampling' => $request->jadwal,
                        'nama_perusahaan' => $request->nama_perusahaan,
                        'periode' => $request->periode,
                        'analis_berangkat' => null,
                        'sampler_berangkat' => null,
                        'analis_pulang' => null,
                        'sampler_pulang' => null,
                        'masker' => [
                            'disiapkan' => 2,
                            'tambahan' => null,
                        ],
                        'sarung_tangan_karet' => [
                            'disiapkan' => 2,
                            'tambahan' => null,
                        ],
                        'sarung_tangan_bintik' => [
                            'disiapkan' => 2,
                            'tambahan' => null,
                        ],
                        'detail' => []
                    ]);

                    $psController->save($requestPsData);

                    $psHeader = $pshModel::where('no_quotation', $request->nomor_quotation)
                        ->where('no_order', $dataOrder->no_order)
                        ->where('tanggal_sampling', $request->jadwal)
                        // ->whereJsonContains('no_sampel', $pra_no_sample)
                        ->first();
                }
            }

            // dd($psHeader);

            $noDocument = explode('/', $psHeader->no_document);
            $noDocument[1] = 'STPS';
            $noDocument = implode('/', $noDocument);

            $qr_img = '';
            $qr = QrDocument::where('id_document', $psHeader->id)
                ->where('type_document', 'surat_tugas_pengambilan_sampel')
                ->whereJsonContains('data->no_document', $noDocument)
                ->first();

            if ($qr) {
                $qr_data = json_decode($qr->data, true);
                if (isset($qr_data['no_document']) && $qr_data['no_document'] == $noDocument) {
                    $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr;
                }
            }

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
                        'content' => '' . $qr_img . '',
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

            // $no_bulan = ($request->periode != "" && $request->periode != null) ? explode('-', $request->periode)[1] : 1;

            // $no_document = 'ISL/STPS/' . date('y') . '-' . self::romawi(date('m')) . '/' . $dataOrder->no_order . '/' . sprintf('%04d', $no_bulan);
            $no_document = $noDocument;
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
                $value = (object) $value;
                // dump($value);
                $regulasiText = '';
                if (!empty($value->regulasi) && is_array($value->regulasi)) {
                    $regulasiText = implode(', ', $value->regulasi);
                }
                // dd($value);
                $pdf->WriteHTML(
                    '<tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 12px; padding: 5px;">
                                <b style="font-size: 12px;">' . $value->kategori_3 . '</b><br><hr>
                                <b style="font-size: 12px;">' .
                        (!empty($regulasiText) ? $regulasiText . ' - ' : '') . // hanya tampil jika ada regulasi
                        $value->total_parameter . ' Parameter ' . $value->persiapan .
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








            // LAMPIRAN STPS
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
                            <p style="font-size:14px;"><b><u>LAMPIRAN STPS</u></b></p>
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

            $pdf->AddPage();

            $pdf->WriteHTML('
                <table class="table table-bordered" style="font-size: 8px; margin-bottom: 10px;">
                    <thead class="text-center">
                        <tr>
                            <th width="2%" style="padding: 5px !important;">NO</th>
                            <th width="85%">KETERANGAN PENGUJIAN</th>
                            <th>NO SAMPEL</th>
                        </tr>
                    </thead>
                    <tbody>');

            $i = 1;
            $pe = 0;
            // dd($dataOrderDetailPerPeriode);
            foreach ($dataOrderDetailPerPeriode as $key => $value) {
                // dd($value);
                $value = (object) $value;
                $regulasiText = '';
                if (!empty($value->regulasi) && is_array($value->regulasi)) {
                    $regulasiText = implode(', ', $value->regulasi);
                }
                // dd($value);
                $pdf->WriteHTML(
                    '<tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 12px; padding: 5px;">
                                <b style="font-size: 12px;">' . $value->kategori_3 . '</b><br><hr>
                                <b style="font-size: 12px;">' .
                        (!empty($regulasiText) ? $regulasiText . ' - ' : '') . // hanya tampil jika ada regulasi
                        $value->total_parameter . ' Parameter ' . $value->persiapan .
                        '</b>'
                );
                foreach ($value->parameter as $keys => $parameter) {
                    // dd($parameter);
                    $pdf->WriteHTML(($keys == 0 ? '<br><hr>' : ' &bull; ') . '<span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $parameter . '</span> ');
                }

                $pdf->WriteHTML(
                    '<td style="font-size: 13px; padding: 5px;text-align:center;">' . implode('<br />', $value->no_sampel) . '</td></tr>'
                );
                $i++;
                $pe++;
            }

            $pdf->WriteHTML('</tbody></table>');

            $fileName = str_replace('/', '-', $no_document) . '.pdf';

            $pdf->Output(public_path() . '/stps/' . $fileName, 'F');

            return $fileName;
            // return response($pdf->Output('', 'I'));
        } catch (\Throwable $th) {
            dd($th);
            return response()->json([
                'message' => 'Data STPS tidak bisa dicetak karena data tidak sinkron dengan data jadwal.!',
            ], 500);
        }
    }

    // disabled by myd 2025-08-06
    // public function cetakPDFSTPS(Request $request)
    // {
    //     try {
    //         //  ==============================================COLLECT DATA========================================
    //         $tipe_penawaran = \explode('/', $request->nomor_quotation)[1];
    //         if ($tipe_penawaran == 'QTC') {
    //             if ($request->periode == null || $request->periode == "") {
    //                 return response()->json([
    //                     'message' => 'Periode tidak ditemukan.!',
    //                 ], 401);
    //             }

    //             $dataPenawaran = QuotationKontrakH::with(['order', 'sampling', 'detail'])->where('no_document', $request->nomor_quotation)->first();
    //             $dataOrder = $dataPenawaran->order;
    //             $dataSampling = $dataPenawaran->sampling;

    //             $unik_kategori = $dataOrder->orderDetail()->where('periode', $request->periode)
    //                 ->where('is_active', true)->get()->pluck('kategori_3')->unique()->toArray();
    //             $pra_no_sample = [];
    //             $kategori_sample = [];
    //             foreach (\explode(',', $request->kategori) as $kat) {
    //                 $split = explode(' - ', $kat);
    //                 $kategori_sample[] = html_entity_decode($split[0]);
    //                 $pra_no_sample[] = $dataOrder->no_order . '/' . $split[1];
    //             }
    //             $pra_no_sample = array_unique($pra_no_sample);
    //             // dd($unik_kategori,$kategori_sample,$pra_no_sample);

    //             foreach ($unik_kategori as $kategori) {
    //                 // if($kategori == null) continue;
    //                 $split = explode('-', $kategori);
    //                 $id = $split[0];
    //                 $nama_kategori = $split[1];

    //                 if (in_array($nama_kategori, $kategori_sample)) {
    //                     $kategori_sample = array_map(function ($item) use ($id, $nama_kategori) {
    //                         if ($item == $nama_kategori) {
    //                             return $id . '-' . $nama_kategori;
    //                         }
    //                         return $item;
    //                     }, $kategori_sample);
    //                 }
    //             }

    //             $getPeriodeSampling = array_filter($dataSampling->toArray(), function ($item) use ($request) {
    //                 return $item['periode_kontrak'] == $request->periode;
    //             });

    //             $dataSampling = array_values($getPeriodeSampling)[0]['jadwal'];
    //             foreach ($dataSampling as $key => $value) {
    //                 unset($dataSampling[$key]['id']);
    //                 unset($dataSampling[$key]['nama_perusahaan']);
    //                 unset($dataSampling[$key]['wilayah']);
    //                 unset($dataSampling[$key]['alamat']);
    //                 unset($dataSampling[$key]['tanggal']);
    //                 unset($dataSampling[$key]['periode']);
    //                 unset($dataSampling[$key]['jam']);
    //                 unset($dataSampling[$key]['jam_mulai']);
    //                 unset($dataSampling[$key]['jam_selesai']);
    //                 unset($dataSampling[$key]['kategori']);
    //                 unset($dataSampling[$key]['sampler']);
    //                 unset($dataSampling[$key]['userid']);
    //                 unset($dataSampling[$key]['driver']);
    //                 unset($dataSampling[$key]['warna']);
    //                 unset($dataSampling[$key]['note']);
    //                 unset($dataSampling[$key]['durasi']);
    //                 unset($dataSampling[$key]['status']);
    //                 unset($dataSampling[$key]['flag']);
    //                 unset($dataSampling[$key]['created_by']);
    //                 unset($dataSampling[$key]['created_at']);
    //                 unset($dataSampling[$key]['updated_by']);
    //                 unset($dataSampling[$key]['updated_at']);
    //                 unset($dataSampling[$key]['canceled_by']);
    //                 unset($dataSampling[$key]['canceled_at']);
    //                 unset($dataSampling[$key]['notif']);
    //                 unset($dataSampling[$key]['urutan']);
    //                 unset($dataSampling[$key]['kendaraan']);
    //             }

    //             $dataSampling = array_values(array_filter(array_unique($dataSampling, SORT_REGULAR), function ($item) {
    //                 return isset($item['is_active']) && $item['is_active'] == 1;
    //             }));
    //             // dd($dataSampling);
    //             if (count($dataSampling) > 1) {
    //                 // jika data jadwalnya parsial
    //                 $dataOrderDetailPerPeriode = $dataOrder->orderDetail()
    //                     ->select('kategori_3', 'periode', 'kategori_1', 'kategori_2', 'regulasi', 'keterangan_1', 'parameter', 'persiapan', 'no_sampel')
    //                     ->where('periode', $request->periode)
    //                     ->whereIn('kategori_3', $kategori_sample)
    //                     ->whereIn('no_sampel', $pra_no_sample)
    //                     ->where('kategori_1', '!=', 'SD')
    //                     ->where('is_active', 1)
    //                     // ->groupBy('kategori_3', 'periode', 'kategori_2', 'regulasi', 'keterangan_1', 'parameter', 'persiapan')
    //                     ->orderBy('periode')
    //                     ->orderBy('no_sampel')
    //                     ->get()
    //                     ->groupBy(['kategori_3', 'regulasi', 'parameter'])

    //                     ->map(function ($kategori3Group) {
    //                         return $kategori3Group->map(function ($regulasiGroup) {
    //                             return $regulasiGroup->map(function ($parameterGroup) {
    //                                 $jumlahTitik = $parameterGroup->count();
    //                                 // dd($parameterGroup);
    //                                 return [
    //                                     'kategori_3' => \explode('-', $parameterGroup->first()->kategori_3)[1],
    //                                     'kategori_1' => $parameterGroup->first()->kategori_1,
    //                                     'periode' => $parameterGroup->first()->periode,
    //                                     'kategori_2' => \explode('-', $parameterGroup->first()->kategori_2)[1],
    //                                     'regulasi' => $parameterGroup->first()->regulasi ? array_map(function ($item) {
    //                                     return explode('-', $item)[1];
    //                                 }, json_decode($parameterGroup->first()->regulasi) ?? []) : [],
    //                                     'keterangan_1' => $parameterGroup->first()->keterangan_1,
    //                                     'parameter' => array_map(function ($item) {
    //                                     $paramId = explode(';', $item)[0];
    //                                     $param = Parameter::find($paramId);
    //                                     return $param ? $param->nama_lab : null;
    //                                 }, json_decode($parameterGroup->first()->parameter)),
    //                                     'persiapan' => ($parameterGroup->first()->kategori_2 == '1-Air' ? '( ' . number_format((array_sum(array_map(function ($item) {
    //                                     if (!is_object($item))
    //                                         return 0;
    //                                     $persiapan = json_decode($item->persiapan, true);
    //                                     return $persiapan ? array_sum(array_column($persiapan, 'volume')) : 0;
    //                                 }, $parameterGroup->toArray())) / 1000), 1) . ' L )' : ''),
    //                                     'total_parameter' => count(json_decode($parameterGroup->first()->parameter) ?: []),
    //                                     'jumlah_titik' => $jumlahTitik,
    //                                     'no_sampel' => $parameterGroup->pluck('no_sampel')->min()
    //                                 ];
    //                             })->values();
    //                         })->collapse();
    //                     })->collapse()
    //                     ->values()
    //                     ->toArray();


    //                 usort($dataOrderDetailPerPeriode, function ($a, $b) {
    //                     // Ambil angka terakhir dari no_sampel, misalnya dari "ISDI012502/021" ambil 021 → jadi 21
    //                     preg_match('/\/(\d+)$/', $a['no_sampel'], $matchesA);
    //                     preg_match('/\/(\d+)$/', $b['no_sampel'], $matchesB);

    //                     $numA = isset($matchesA[1]) ? (int) $matchesA[1] : 0;
    //                     $numB = isset($matchesB[1]) ? (int) $matchesB[1] : 0;

    //                     return $numA <=> $numB; // Ascending order
    //                 });

    //             } else {
    //                 $data_detail_penawaran = json_decode($dataPenawaran->detail()->where('periode_kontrak', $request->periode)->first()->data_pendukung_sampling, true);
    //                 $data_detail_penawaran = array_map(function ($item) {
    //                     $maping = array_map(function ($data_sampling) use ($item) {
    //                         return [
    //                             'kategori_3' => \explode('-', $data_sampling['kategori_2'])[1],
    //                             'periode' => $item['periode_kontrak'],
    //                             'kategori_2' => \explode('-', $data_sampling['kategori_1'])[1],
    //                             'regulasi' => (!empty($data_sampling['regulasi']) && is_array($data_sampling['regulasi'])) ? array_map(function ($item) {
    //                                 return explode('-', $item)[1] ?? ''; // pakai null coalescing untuk mencegah undefined offset
    //                             }, $data_sampling['regulasi']) : [],
    //                             'keterangan_1' => $data_sampling['penamaan_titik'],
    //                             'parameter' => array_map(function ($parameter) {
    //                                 return \explode(';', $parameter)[1];
    //                             }, $data_sampling['parameter']),
    //                             'persiapan' => isset($data_sampling['volume']) && !empty($data_sampling['volume']) ?
    //                                 '( ' . number_format($data_sampling['volume'] / 1000, 1) . ' L )' : '',
    //                             'total_parameter' => $data_sampling['total_parameter'],
    //                             'jumlah_titik' => $data_sampling['jumlah_titik']
    //                         ];
    //                     }, $item['data_sampling']);

    //                     return $maping;
    //                 }, $data_detail_penawaran);

    //                 $data_detail_penawaran = array_values($data_detail_penawaran)[0];

    //                 $dataOrderDetailPerPeriode = array_values($data_detail_penawaran);
    //             }

    //             $dataTransport = $dataPenawaran->detail()
    //                 ->where('periode_kontrak', $request->periode)
    //                 ->select('transportasi', 'perdiem_jumlah_orang', 'perdiem_jumlah_hari', 'jumlah_orang_24jam')
    //                 ->first();

    //             $dataTransport = [
    //                 'transportasi' => $dataTransport->transportasi,
    //                 'perdiem_jumlah_orang' => $dataTransport->perdiem_jumlah_orang,
    //                 'perdiem_jumlah_hari' => $dataTransport->perdiem_jumlah_hari,
    //                 'jumlah_orang_24jam' => $dataTransport->jumlah_orang_24jam,
    //                 'wilayah' => \explode('-', $dataPenawaran->wilayah)[1]
    //             ];

    //             //  =========================================COLLECT DATA SELESAI========================================
    //         } else {

    //             $dataPenawaran = QuotationNonKontrak::with(['order', 'sampling'])->where('no_document', $request->nomor_quotation)->first();


    //             $dataOrder = $dataPenawaran->order;

    //             if ($dataOrder->is_revisi == 1) {
    //                 return response()->json([
    //                     'message' => 'Quotation sedang dalam revisi.!',
    //                 ], 401);
    //             }
    //             $dataSampling = $dataPenawaran->sampling;

    //             $unik_kategori = $dataOrder->orderDetail()->get()->pluck('kategori_3')->unique()->toArray();
    //             // dd($unik_kategori);
    //             $pra_no_sample = [];
    //             $kategori_sample = [];

    //             foreach (\explode(',', $request->kategori) as $kat) {
    //                 $split = explode(' - ', $kat);
    //                 $kategori_sample[] = html_entity_decode($split[0]);
    //                 $pra_no_sample[] = $dataOrder->no_order . '/' . $split[1];
    //             }
    //             $pra_no_sample = array_unique($pra_no_sample);
    //             // dd($pra_no_sample);
    //             // dd($unik_kategori, $kategori_sample, $pra_no_sample);
    //             foreach ($unik_kategori as $kategori) {
    //                 if ($kategori == null)
    //                     continue;
    //                 $split = explode('-', $kategori);
    //                 $id = $split[0];
    //                 $nama_kategori = $split[1];

    //                 if (in_array($nama_kategori, $kategori_sample)) {
    //                     $kategori_sample = array_map(function ($item) use ($id, $nama_kategori) {
    //                         if ($item == $nama_kategori) {
    //                             return $id . '-' . $nama_kategori;
    //                         }
    //                         return $item;
    //                     }, $kategori_sample);
    //                 }
    //             }

    //             $dataSampling = array_values($dataSampling->toArray())[0]['jadwal'];
    //             // dd($dataSampling);
    //             foreach ($dataSampling as $key => $value) {
    //                 unset($dataSampling[$key]['id']);
    //                 unset($dataSampling[$key]['nama_perusahaan']);
    //                 unset($dataSampling[$key]['wilayah']);
    //                 unset($dataSampling[$key]['alamat']);
    //                 unset($dataSampling[$key]['tanggal']);
    //                 unset($dataSampling[$key]['periode']);
    //                 unset($dataSampling[$key]['jam']);
    //                 unset($dataSampling[$key]['jam_mulai']);
    //                 unset($dataSampling[$key]['jam_selesai']);
    //                 unset($dataSampling[$key]['kategori']);
    //                 unset($dataSampling[$key]['sampler']);
    //                 unset($dataSampling[$key]['userid']);
    //                 unset($dataSampling[$key]['driver']);
    //                 unset($dataSampling[$key]['warna']);
    //                 unset($dataSampling[$key]['note']);
    //                 unset($dataSampling[$key]['durasi']);
    //                 unset($dataSampling[$key]['status']);
    //                 unset($dataSampling[$key]['flag']);
    //                 unset($dataSampling[$key]['created_by']);
    //                 unset($dataSampling[$key]['created_at']);
    //                 unset($dataSampling[$key]['updated_by']);
    //                 unset($dataSampling[$key]['updated_at']);
    //                 unset($dataSampling[$key]['canceled_by']);
    //                 unset($dataSampling[$key]['canceled_at']);
    //                 unset($dataSampling[$key]['notif']);
    //                 unset($dataSampling[$key]['urutan']);
    //                 unset($dataSampling[$key]['kendaraan']);
    //             }
    //             // dd($dataSampling);
    //             $dataSampling = array_values(array_filter(array_unique($dataSampling, SORT_REGULAR), function ($item) {
    //                 return isset($item['is_active']) && $item['is_active'] == 1;
    //             }));
    //             // dd($dataSampling);
    //             if (count($dataSampling) > 1) {
    //                 // jika data jadwalnya parsial
    //                 $dataOrderDetailPerPeriode = $dataOrder->orderDetail()
    //                     ->select('kategori_3', 'kategori_2', 'kategori_1', 'regulasi', 'keterangan_1', 'parameter', 'persiapan')
    //                     ->whereIn('kategori_3', $kategori_sample)
    //                     ->whereIn('no_sampel', $pra_no_sample)
    //                     ->where('kategori_1', '!=', 'SD')
    //                     ->where('is_active', 1)
    //                     ->get()
    //                     ->groupBy(['kategori_3', 'regulasi', 'parameter'])
    //                     ->map(function ($kategori3Group) {
    //                         return $kategori3Group->map(function ($regulasiGroup) {
    //                             return $regulasiGroup->map(function ($parameterGroup) {
    //                                 $first = $parameterGroup->first();

    //                                 return [
    //                                     'kategori_3' => \explode('-', $first->kategori_3)[1],
    //                                     'kategori_1' => $parameterGroup->first()->kategori_1,
    //                                     'periode' => NULL,
    //                                     'kategori_2' => \explode('-', $first->kategori_2)[1],
    //                                     'regulasi' => $first->regulasi ? array_map(function ($item) {
    //                                         // dd($item);
    //                                         // return $item !== "" && explode('-', $item)[1];
    //                                         return ($item !== "" ? explode('-', $item)[1] : "");
    //                                     }, json_decode($first->regulasi) ?? []) : [],
    //                                     'keterangan_1' => $first->keterangan_1,
    //                                     'parameter' => array_map(function ($item) {
    //                                         $paramId = explode(';', $item)[0];
    //                                         $param = Parameter::find($paramId);
    //                                         return $param ? $param->nama_lab : null;
    //                                     }, json_decode($first->parameter)),
    //                                     'persiapan' => ($first->kategori_2 == '1-Air' ? '( ' . number_format((array_sum(array_map(function ($item) {
    //                                         if (!is_object($item))
    //                                             return 0;
    //                                         $persiapan = json_decode($item->persiapan, true);
    //                                         return $persiapan ? array_sum(array_column($persiapan, 'volume')) : 0;
    //                                     }, $parameterGroup->toArray())) / 1000), 1) . ' L )' : ''),
    //                                     'total_parameter' => count(json_decode($first->parameter) ?: []),
    //                                     'jumlah_titik' => $parameterGroup->count(),
    //                                     'no_sampel' => $parameterGroup->pluck('no_sampel')->min()
    //                                 ];
    //                             })->values();
    //                         })->collapse();
    //                     })->collapse()
    //                     ->values()
    //                     ->toArray();

    //                 // dd($dataOrderDetailPerPeriode);

    //                 usort($dataOrderDetailPerPeriode, function ($a, $b) {
    //                     // Ambil angka terakhir dari no_sampel, misalnya dari "ISDI012502/021" ambil 021 → jadi 21
    //                     preg_match('/\/(\d+)$/', $a['no_sampel'], $matchesA);
    //                     preg_match('/\/(\d+)$/', $b['no_sampel'], $matchesB);

    //                     $numA = isset($matchesA[1]) ? (int) $matchesA[1] : 0;
    //                     $numB = isset($matchesB[1]) ? (int) $matchesB[1] : 0;

    //                     return $numA <=> $numB; // Ascending order
    //                 });
    //                 // dd('stop', $dataOrderDetailPerPeriode, $kategori_sample);
    //             } else {

    //                 $data_detail_penawaran = json_decode($dataPenawaran->data_pendukung_sampling, true);
    //                 // dd($dataPenawaran, 'test');
    //                 $data_detail_penawaran = array_map(function ($data_sampling) {
    //                     return [
    //                         'kategori_3' => \explode('-', $data_sampling['kategori_2'])[1],
    //                         'periode' => NULL,
    //                         'kategori_2' => \explode('-', $data_sampling['kategori_1'])[1],
    //                         'regulasi' => $data_sampling['regulasi'] ? array_map(function ($item) {
    //                             return explode('-', $item)[1];
    //                         }, $data_sampling['regulasi']) : [],
    //                         'keterangan_1' => $data_sampling['penamaan_titik'],
    //                         'parameter' => array_map(function ($parameter) {
    //                             return \explode(';', $parameter)[1];
    //                         }, $data_sampling['parameter']),
    //                         'persiapan' => isset($data_sampling['volume']) && !empty($data_sampling['volume']) ?
    //                             '( ' . number_format($data_sampling['volume'] / 1000, 1) . ' L )' : '',
    //                         'total_parameter' => $data_sampling['total_parameter'],
    //                         'jumlah_titik' => $data_sampling['jumlah_titik']
    //                     ];
    //                 }, $data_detail_penawaran);
    //                 // dd($data_detail_penawaran);
    //                 $data_detail_penawaran = array_values($data_detail_penawaran);

    //                 $dataOrderDetailPerPeriode = $data_detail_penawaran;
    //             }
    //             // dd($dataOrderDetailPerPeriode);
    //             $dataTransport = $dataPenawaran->select('transportasi', 'perdiem_jumlah_orang', 'perdiem_jumlah_hari', 'jumlah_orang_24jam')
    //                 ->first();
    //             $dataTransport = [
    //                 'transportasi' => $dataTransport->transportasi,
    //                 'perdiem_jumlah_orang' => $dataTransport->perdiem_jumlah_orang,
    //                 'perdiem_jumlah_hari' => $dataTransport->perdiem_jumlah_hari,
    //                 'jumlah_orang_24jam' => $dataTransport->jumlah_orang_24jam,
    //                 'wilayah' => \explode('-', $dataPenawaran->wilayah)[1]
    //             ];
    //             //  =========================================COLLECT DATA SELESAI========================================
    //         }

    //         if ($dataPenawaran->status_sampling == 'SD') {
    //             return response()->json([
    //                 'message' => 'Sample diantar tidak memiliki STPS.!',
    //             ], 401);
    //         }
    //         //  =========================================CETAK PDF========================================
    //         // $psh = PersiapanSampelHeader::where('no_quotation', $request->nomor_quotation)
    //         //     ->where('no_order', $dataOrder->no_order)
    //         //     // ->where('periode', $request->periode)
    //         //     ->where('tanggal_sampling', $request->jadwal)
    //         //     ->whereJsonContains('no_sampel', $pra_no_sample[0])
    //         //     ->first();
    //         // $dataList = PersiapanSampelHeader::where('no_quotation', $request->nomor_quotation)
    //         //     ->where('no_order', $dataOrder->no_order)
    //         //     ->where('tanggal_sampling', $request->jadwal)
    //         //     ->where('is_active', 1)
    //         //     ->get();

    //         // $psh = $dataList->first(function ($item) use ($pra_no_sample) {
    //         //     $noSampel = json_decode($item->no_sampel, true) ?? [];
    //         //     return count(array_intersect($noSampel, $pra_no_sample)) > 0;
    //         // });


    //         // if (!$psh) {
    //         //     if (file_exists(public_path() . '/stps')) {
    //         //     }

    //         //     return response()->json([
    //         //         'message' => 'Sampel belum disiapkan, Silahkan melakukan update terlebih dahulu.!',
    //         //     ], 401);
    //         // }

    //         $psController = new PersiapanSampleController($request);
    //         $pshModel = PersiapanSampelHeader::class;

    //         $psHeader = $pshModel::where('no_quotation', $request->nomor_quotation)
    //             ->where('no_order', $dataOrder->no_order)
    //             ->where('tanggal_sampling', $request->jadwal);
    //             // ->whereJsonContains('no_sampel', $pra_no_sample);

    //         if ($request->periode) $psHeader = $psHeader->where('periode', $request->periode);

    //         $psHeader = $psHeader->first();

    //         if (!$psHeader) {
    //             $request->no_document = $request->nomor_quotation;
    //             // $request->no_order = $request->no_order;
    //             $request->no_sampel = $pra_no_sample;
    //             // $request->periode = $request->periode;

    //             $response = $psController->preview($request);
    //             $preview = json_decode($response->getContent(), true);

    //             $isMustPrepared = false;
    //             foreach (['air', 'udara', 'emisi', 'padatan'] as $kategori) {
    //                 foreach ($preview[$kategori] as $sampel) {
    //                     if (isset($sampel['no_sampel'])) {
    //                         $isMustPrepared = true;
    //                         break;
    //                     };
    //                 }
    //             }

    //             if (!$isMustPrepared) {
    //                 return response()->json(['message' => 'Sampel belum disiapkan, Silahkan melakukan update terlebih dahulu.!'], 401);
    //             } else {
    //                 $requestPsData = new Request([
    //                     'no_order' => $request->no_order,
    //                     'no_quotation' => $request->no_document,
    //                     'tanggal_sampling' => $request->jadwal,
    //                     'nama_perusahaan' => $request->nama_perusahaan,
    //                     'periode' => $request->periode,
    //                     'analis_berangkat' => null,
    //                     'sampler_berangkat' => null,
    //                     'analis_pulang' => null,
    //                     'sampler_pulang' => null,
    //                     'masker' => [
    //                         'disiapkan' => 2,
    //                         'tambahan' => null,
    //                     ],
    //                     'sarung_tangan_karet' => [
    //                         'disiapkan' => 2,
    //                         'tambahan' => null,
    //                     ],
    //                     'sarung_tangan_bintik' => [
    //                         'disiapkan' => 2,
    //                         'tambahan' => null,
    //                     ],
    //                     'detail' => []
    //                 ]);

    //                 $psController->save($requestPsData);

    //                 $psHeader = $pshModel::where('no_quotation', $request->nomor_quotation)
    //                     ->where('no_order', $dataOrder->no_order)
    //                     ->where('tanggal_sampling', $request->jadwal)
    //                     // ->whereJsonContains('no_sampel', $pra_no_sample)
    //                     ->first();
    //             }
    //         }

    //         $noDocument = explode('/', $psHeader->no_document);
    //         $noDocument[1] = 'STPS';
    //         $noDocument = implode('/', $noDocument);

    //         $qr_img = '';
    //         $qr = QrDocument::where('id_document', $psHeader->id)
    //             ->where('type_document', 'surat_tugas_pengambilan_sampel')
    //             ->whereJsonContains('data->no_document', $noDocument)
    //             ->first();

    //         if ($qr) {
    //             $qr_data = json_decode($qr->data, true);
    //             if (isset($qr_data['no_document']) && $qr_data['no_document'] == $noDocument) {
    //                 $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr;
    //             }
    //         }

    //         $mpdfConfig = [
    //             'mode' => 'utf-8',
    //             'format' => 'A4',
    //             'margin_header' => 10,
    //             'margin_footer' => 3,
    //             'setAutoTopMargin' => 'stretch',
    //             'setAutoBottomMargin' => 'stretch',
    //             'orientation' => 'P'
    //         ];

    //         $pdf = new Mpdf($mpdfConfig);
    //         $pdf->SetProtection(['print'], '', 'skyhwk12');
    //         $pdf->showWatermarkImage = true;

    //         $footer = [
    //             'odd' => [
    //                 'C' => [
    //                     'content' => 'Hal {PAGENO} dari {nbpg}',
    //                     'font-size' => 6,
    //                     'font-style' => 'I',
    //                     'font-family' => 'serif',
    //                     'color' => '#606060'
    //                 ],
    //                 'R' => [
    //                     'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
    //                     'font-size' => 5,
    //                     'font-style' => 'I',
    //                     'font-family' => 'serif',
    //                     'color' => '#000000'
    //                 ],
    //                 'L' => [
    //                     'content' => '' . $qr_img . '',
    //                     'font-size' => 4,
    //                     'font-style' => 'I',
    //                     'font-family' => 'serif',
    //                     'color' => '#000000'
    //                 ],
    //                 'line' => -1,
    //             ]
    //         ];

    //         $pdf->setFooter($footer);

    //         $konsultant = $dataPenawaran->konsultan ? strtoupper($dataPenawaran->konsultan) : '';
    //         $perusahaan = $dataPenawaran->konsultan ? ' (' . $dataPenawaran->nama_perusahaan . ') ' : $dataPenawaran->nama_perusahaan;

    //         /* info PIC */
    //         $nama_pic = $dataPenawaran->nama_pic_sampling .
    //             ($dataPenawaran->jabatan_pic_sampling ? ' (' . $dataPenawaran->jabatan_pic_sampling . ')' : '(-)') .
    //             ($dataPenawaran->no_tlp_pic_sampling ? ' - ' . $dataPenawaran->no_tlp_pic_sampling : '');

    //         // $no_bulan = ($request->periode != "" && $request->periode != null) ? explode('-', $request->periode)[1] : 1;

    //         // $no_document = 'ISL/STPS/' . date('y') . '-' . self::romawi(date('m')) . '/' . $dataOrder->no_order . '/' . sprintf('%04d', $no_bulan);
    //         $no_document = $noDocument;
    //         $tanggal = $request->jadwal;

    //         $html = '';
    //         if (str_contains($request->sampler, ',')) {
    //             $datsa = explode(",", $request->sampler);
    //             foreach ($datsa as $s => $dat) {
    //                 $html .= ($s + 1) . '. ' . $dat . '<br>';
    //             }
    //         } else {
    //             $html .= '1. ' . $request->sampler;
    //         }

    //         $pdf->SetHTMLHeader('
    //             <table width="100%">
    //                 <tr>
    //                     <td width="60%"></td>
    //                     <td>
    //                         <table class="table table-bordered" width="100%">
    //                             <tr>
    //                                 <td width="50%" style="text-align: center; font-size: 13px;"><b>No Order</b></td>
    //                                 <td style="text-align: center; font-size: 13px;"><b>' . $dataOrder->no_order . '</b></td>
    //                             </tr>
    //                         </table>
    //                     </td>
    //                 </tr>
    //             </table>
    //             <table width="100%">
    //                 <tr>
    //                     <td class="text-left text-wrap" style="width: 55%;"></td>
    //                     <td style="text-align:center">
    //                         <p style="font-size:14px;"><b><u>SURAT TUGAS PENGAMBILAN SAMPEL</u></b></p>
    //                         <p style="font-size:11px;text-align:center" id="no_document">' . $no_document . '</p>
    //                     </td>
    //                 </tr>
    //             </table>
    //             <table style="font-size:13px;font-weight:700;width:100%;margin-top:20px;">
    //                 <tr>
    //                     <td>' . $konsultant . $perusahaan . '</td>
    //                 </tr>
    //                 <tr>
    //                     <td width="65%">
    //                         <p style="font-size:10px">
    //                             <u>Informasi Sampling :</u><br>
    //                             <span id="tgl_sampling">' . ($tanggal ? self::tanggal_indonesia($tanggal, 'hari') : 'Belum dijadwalkan') . '</span><br>
    //                             <span id="alamat_sampling" style="white-space:pre-wrap;word-wrap:break-word;width:50%">' . $dataPenawaran->alamat_sampling . '</span><br>
    //                             <span id="pic_order">PIC : ' . $nama_pic . '</span>
    //                         </p>
    //                     </td>
    //                     <td style="vertical-align:top;font-size:10px">
    //                         <u>Petugas Sampling :</u>
    //                         <div id="petugas_sampling">' . $html . '</div>
    //                     </td>
    //                 </tr>
    //             </table>
    //         ');

    //         $pdf->WriteHTML('
    //             <table class="table table-bordered" style="font-size: 8px; margin-bottom: 10px;">
    //                 <thead class="text-center">
    //                     <tr>
    //                         <th width="2%" style="padding: 5px !important;">NO</th>
    //                         <th width="85%">KETERANGAN PENGUJIAN</th>
    //                         <th>TITIK</th>
    //                     </tr>
    //                 </thead>
    //                 <tbody>');

    //         $i = 1;
    //         $pe = 0;
    //         foreach ($dataOrderDetailPerPeriode as $key => $value) {
    //             $value = (object) $value;
    //             // dump($value);
    //             $regulasiText = '';
    //             if (!empty($value->regulasi) && is_array($value->regulasi)) {
    //                 $regulasiText = implode(', ', $value->regulasi);
    //             }
    //             // dd($value);
    //             $pdf->WriteHTML(
    //                 '<tr>
    //                         <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
    //                         <td style="font-size: 12px; padding: 5px;">
    //                             <b style="font-size: 12px;">' . $value->kategori_3 . '</b><br><hr>
    //                             <b style="font-size: 12px;">' .
    //                 (!empty($regulasiText) ? $regulasiText . ' - ' : '') . // hanya tampil jika ada regulasi
    //                 $value->total_parameter . ' Parameter ' . $value->persiapan .
    //                 '</b>'
    //             );
    //             foreach ($value->parameter as $keys => $parameter) {
    //                 // dd($parameter);
    //                 $pdf->WriteHTML(($keys == 0 ? '<br><hr>' : ' &bull; ') . '<span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $parameter . '</span> ');
    //             }
    //             $pdf->WriteHTML(
    //                 '<td style="font-size: 13px; padding: 5px;text-align:center;">' . $value->jumlah_titik . '</td></tr>'
    //             );
    //             $i++;
    //             $pe++;
    //         }

    //         if ($dataTransport['transportasi'] > 0) {
    //             $pdf->WriteHTML('
    //                 <tr>
    //                     <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
    //                     <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . $dataTransport['wilayah'] . '</td>
    //                     <td style="font-size: 13px; text-align:center;">' . $dataTransport['transportasi'] / $dataTransport['transportasi'] . '</td>
    //                 </tr>');
    //         }

    //         if ($dataTransport['perdiem_jumlah_orang'] > 0) {
    //             $i++;
    //             $pdf->WriteHTML('
    //                 <tr>
    //                     <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
    //                     <td style="font-size: 13px;padding: 5px;">Perdiem : ' . $dataTransport['perdiem_jumlah_orang'] . '</td>
    //                     <td style="font-size: 13px; text-align:center;">' . $dataTransport['perdiem_jumlah_hari'] / $dataTransport['perdiem_jumlah_hari'] . '</td>
    //                 </tr>');
    //         }

    //         $pdf->WriteHTML('</tbody></table>');

    //         $pdf->WriteHTML('<table width="100%" style="margin-top:10px;">
    //             <tr>
    //                 <td width="60%" style="font-size: 10px;">QT : ' . $dataPenawaran->no_document . '</td>
    //                 <td style="font-size: 10px;text-align:center;">
    //                     <span>Tangerang, ' . self::tanggal_indonesia(date('Y-m-d')) . '</span><br>
    //                     <span><b>Manajer Teknis</b></span><br><br><br>
    //                 </td>
    //             </tr>
    //             <tr><td></td><td></td></tr>
    //             <tr><td></td><td></td></tr>
    //             <tr><td></td><td></td></tr>
    //             <tr><td></td><td></td></tr>
    //             <tr><td></td><td></td></tr>
    //             <tr><td></td><td></td></tr>
    //             <tr>
    //                 <td style="font-size: 10px;">Waktu tiba di lokasi : ' . $request->jadwal_jam_mulai . '</td>
    //                 <td style="font-size: 10px;text-align:center;">&nbsp;&nbsp;&nbsp;(..............................................)</td>
    //             </tr>
    //         </table>');

    //         $fileName = str_replace('/', '-', $no_document) . '.pdf';

    //         $pdf->Output(public_path() . '/stps/' . $fileName, 'F');

    //         return $fileName;
    //         // return response($pdf->Output('', 'I'));
    //     } catch (\Throwable $th) {
    //         dd($th);
    //         return response()->json([
    //             'message' => 'Data STPS tidak bisa dicetak karena data tidak sinkron dengan data jadwal.!',
    //         ], 500);
    //     }
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

        if ($mode == 'period')
            return $bulan[(int) $var[1]] . ' ' . $var[0];
        if ($mode == 'hari')
            return $hari . ' / ' . $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];

        return $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
    }
}
