<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

Carbon::setLocale('id');

use Yajra\DataTables\DataTables;
use Exception;
use App\Jobs\RenderPdfPersiapanSample;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

use App\Models\{
    OrderDetail,
    MasterKaryawan,
    QuotationKontrakH,
    QuotationNonKontrak,
    PersiapanSampelHeader,
    PersiapanSampelDetail,
    KonfigurasiPraSampling,
    QrDocument,
    JobTask
};

use App\Http\Controllers\api\RekapSampelController;


class PersiapanSampleController extends Controller
{
    public function indexV(Request $request)
    {
        try {
            $periode_awal = Carbon::parse($request->periode_awal); // format dari frontend YYYY-MM
            $periode_akhir = Carbon::parse($request->periode_akhir)->endOfMonth(); // mengambil tanggal terakhir dari bulan terpilih
            $interval = $periode_awal->diff($periode_akhir);

            if ($interval->days > 31)
                return response()->json(['message' => 'Periode tidak boleh lebih dari 1 bulan'], 403);

            $data = OrderDetail::with([
                'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi',
                'orderHeader.samplingPlan',
                'orderHeader.samplingPlan.jadwal' => function ($q) {
                    $q->select(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang', DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')])
                        ->where('is_active', true)
                        ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang']);
                }
            ])
                ->select(['id_order_header', 'no_order', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
                ->where('is_active', true)
                // ->where('order_detail.no_order', 'IAAI012502')
                ->whereBetween('tanggal_sampling', [
                    $periode_awal->format('Y-m-01'),
                    $periode_akhir->format('Y-m-t')
                ])
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
                            'id_cabang' => $schedule['id_cabang'] ?? null,
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

            return DataTables::of(collect($finalResult))
                // Global search
                ->filter(function ($item) use ($request) {
                    $keyword = $request->input('search.value');

                    if (!$keyword)
                        return true;

                    $fieldsToSearch = [
                        'nomor_quotation',
                        'nama_perusahaan',
                        'periode',
                        'jadwal'
                    ];

                    foreach ($fieldsToSearch as $field) {
                        if (!empty($item->$field) && stripos($item->$field, $keyword) !== false) {
                            return true;
                        }
                    }

                    return false;
                })
                // Column search
                ->filter(function ($item) use ($request) {
                    $columns = $request->input('columns', []);

                    foreach ($columns as $column) {
                        $colName = $column['name'] ?? null;
                        $colValue = trim($column['search']['value'] ?? '');

                        if ($colName && $colValue) {
                            $field = $item->$colName ?? '';

                            if ($colName === 'periode') {
                                try {
                                    $parsed = Carbon::parse($field)->translatedFormat('F Y');
                                    if (stripos($parsed, $colValue) === false) {
                                        return false;
                                    }
                                } catch (Exception $e) {
                                    return false;
                                }
                            } elseif ($colName === 'jadwal') {
                                try {
                                    $parsed = Carbon::parse($field)->format('d/m/Y');
                                    if (stripos($parsed, $colValue) === false) {
                                        return false;
                                    }
                                } catch (Exception $e) {
                                    return false;
                                }
                            } else {
                                if (stripos($field, $colValue) === false) {
                                    return false;
                                }
                            }
                        }
                    }

                    return true;
                }, true)
                ->make(true);
        } catch (Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function preview(Request $request)
    {
        try {
            $tipe = explode("/", $request->no_document);
            $jadwal = [];
            if ($tipe[1] == "QT") {
                $perdiem = QuotationNonKontrak::with(['sampling', 'order'])->where('no_document', $request->no_document)->first();

                if (!$perdiem->order)
                    return response()->json(['message' => "Order dengan No. Quotation $request->no_document tidak ditemukan"], 401);

                if ($perdiem->order->is_revisi)
                    return response()->json(['message' => "Order dengan No. Quotation $request->no_document sedang dalam proses revisi"], 401);

                if ($perdiem->sampling->first()->jadwal) {
                    $getJadwal = $perdiem->sampling->first()->jadwal->pluck('tanggal')->toArray();
                    $jadwal = array_values(array_unique($getJadwal));
                    sort($jadwal);
                } else {
                    return response()->json(['message' => "Jadwal pada Order dengan No. Quotation $request->no_document tidak ditemukan"], 401);
                }
            } else if ($tipe[1] == "QTC") {
                $perdiem = QuotationKontrakH::with(['sampling', 'order'])->where('no_document', $request->no_document)->first();

                if (!$perdiem->order)
                    return response()->json(['message' => "Order dengan No. Quotation $request->no_document tidak ditemukan"], 401);

                if ($perdiem->order->is_revisi)
                    return response()->json(['message' => "Order dengan No. Quotation $request->no_document sedang dalam proses revisi"], 401);

                foreach ($perdiem->sampling as $sampling) {
                    if (!$sampling->jadwal)
                        return response()->json(['message' => "Jadwal tidak ditemukan di periode $sampling->periode_kontrak"], 401);
                }
            }

            $request->periode = $request->periode ?: null;

            // generatePersiapan => no_order

            $orderD = $perdiem->order->orderDetail()
                ->where('periode', $request->periode);

            if (is_array($request->no_sampel)) {
                $orderD->whereIn('no_sampel', $request->no_sampel);
            } else {
                $orderD->where('no_sampel', $request->no_sampel);
            }
            //cek kondisi jika ada isi yang kosong terutama air
            $query_cek = OrderDetail::where('is_active', 1)
                ->where('no_order', $request->no_order)
                ->where(function ($query) {
                    $query->whereJsonDoesntContain('parameter', '309;Pencahayaan')
                        ->whereJsonDoesntContain('parameter', '268;Kebisingan')
                        ->whereJsonDoesntContain('parameter', '318;Psikologi')
                        ->whereJsonDoesntContain('parameter', '230;Ergonomi');
                })
                ->where('persiapan', '[]');
            if (isset($request->periode) && $request->periode != null) {
                $query_cek->where('periode', $request->periode);
            }
            $parsingCollection = $query_cek->get();
            $filtered_empty = $query_cek->get()->pluck('persiapan')->toArray();
            $filtered_empty = in_array("[]", $filtered_empty, true);

            if ($filtered_empty) {
                $newRequest = new Request();
                $newRequest->replace(
                    [
                        'no_order' => $request->no_order,
                        'periode' => $request->periode,
                        'collectionContain' => $parsingCollection
                    ]
                );
                $orderD = RekapSampelController::generatePersiapan($newRequest);
            }

            $orderD = $orderD->whereIn('no_sampel', $request->no_sampel)->get();

            $dataBotol = [];
            foreach ($orderD as $val) {
                if ($val->kategori_2 == '1-Air') {
                    array_push($dataBotol, [
                        "kategori" => $val->kategori_2,
                        "no_sampel" => $val->no_sampel,
                        "persiapan" => json_decode($val->persiapan, true),
                        "parameter" => json_decode($val->parameter, true)
                    ]);
                } else if ($val->kategori_2 == '4-Udara') {
                    array_push($dataBotol, [
                        "kategori" => $val->kategori_2,
                        "no_sampel" => $val->no_sampel,
                        "persiapan" => json_decode($val->persiapan, true),
                        "parameter" => json_decode($val->parameter, true)
                    ]);
                } else if ($val->kategori_2 == '5-Emisi') {
                    array_push($dataBotol, [
                        "kategori" => $val->kategori_2,
                        "no_sampel" => $val->no_sampel,
                        "persiapan" => json_decode($val->persiapan, true),
                        "parameter" => json_decode($val->parameter, true)
                    ]);
                } else if ($val->kategori_2 == '6-Padatan') {
                    array_push($dataBotol, [
                        "kategori" => $val->kategori_2,
                        "no_sampel" => $val->no_sampel,
                        "persiapan" => json_decode($val->persiapan, true),
                        "parameter" => json_decode($val->parameter, true)
                    ]);
                }
            }

            $dataBotolGrouped = [];
            foreach ($dataBotol as $botol) {
                $no_sampel = $botol['no_sampel'];
                $kategori = $botol['kategori'];

                if (!isset($dataBotolGrouped[$no_sampel])) {
                    $dataBotolGrouped[$no_sampel] = [
                        'no_sampel' => $no_sampel,
                        'kategori' => []
                    ];
                }

                if (!isset($dataBotolGrouped[$no_sampel]['kategori'][$kategori])) {
                    $dataBotolGrouped[$no_sampel]['kategori'][$kategori] = [];
                }

                if ($kategori == '1-Air' && !empty($botol['persiapan'])) {
                    foreach ($botol['persiapan'] as $item) {
                        $type = $item['type_botol'];
                        $rumus = self::rumus($kategori, $item, $type);

                        if (!isset($dataBotolGrouped[$no_sampel]['kategori'][$kategori][$type])) {
                            $dataBotolGrouped[$no_sampel]['kategori'][$kategori][$type] = [
                                'disiapkan' => 0,
                                "buffer" => 0
                            ];
                        }

                        $dataBotolGrouped[$no_sampel]['kategori'][$kategori][$type]['disiapkan'] += $rumus['data_par'][0]['jumlah'] ?? 0;
                        $dataBotolGrouped[$no_sampel]['kategori'][$kategori][$type]['buffer'] += $rumus['jmlh_label'] ?? 0;
                    }
                } else if ($kategori == '4-Udara') {
                    $cek = KonfigurasiPraSampling::whereIn('parameter', $botol['parameter'])->where('is_active', 1)->get();
                    foreach ($cek as $konfigurasi_persiapan) {
                        $parameter = explode(';', $konfigurasi_persiapan->parameter)[1];
                        $rumus = self::rumus($kategori, null, $konfigurasi_persiapan->parameter);
                        if (!isset($dataBotolGrouped[$no_sampel]['kategori'][$kategori][$parameter])) {
                            $dataBotolGrouped[$no_sampel]['kategori'][$kategori][$parameter] = [
                                'disiapkan' => 0,
                                "buffer" => 0
                            ];
                        }
                        $dataBotolGrouped[$no_sampel]['kategori'][$kategori][$parameter]['disiapkan'] += $rumus['data_par'][0]['jumlah'] ?? 0;
                        $dataBotolGrouped[$no_sampel]['kategori'][$kategori][$parameter]['buffer'] += $rumus['jmlh_label'] ?? 0;
                    }
                } else if ($kategori == '5-Emisi') {
                    $cek = KonfigurasiPraSampling::whereIn('parameter', $botol['parameter'])->where('is_active', 1)->get();

                    foreach ($cek as $konfigurasi_persiapan) {
                        $parameter = explode(';', $konfigurasi_persiapan->parameter)[1];
                        $rumus = self::rumus($kategori, null, $konfigurasi_persiapan->parameter);
                        if (!isset($dataBotolGrouped[$no_sampel]['kategori'][$kategori][$parameter])) {
                            $dataBotolGrouped[$no_sampel]['kategori'][$kategori][$parameter] = [
                                'disiapkan' => 0,
                                "buffer" => 0
                            ];
                        }
                        $dataBotolGrouped[$no_sampel]['kategori'][$kategori][$parameter]['disiapkan'] += $rumus['data_par'][0]['jumlah'] ?? 0;
                        $dataBotolGrouped[$no_sampel]['kategori'][$kategori][$parameter]['buffer'] += $rumus['jmlh_label'] ?? 0;
                    }
                } else if ($kategori == '6-Padatan') {
                    $cek = KonfigurasiPraSampling::whereIn('parameter', $botol['parameter'])->where('is_active', 1)->get();
                    foreach ($cek as $konfigurasi_persiapan) {
                        $parameter = explode(';', $konfigurasi_persiapan->parameter)[1];
                        $rumus = self::rumus($kategori, null, $konfigurasi_persiapan->parameter);
                        if (!isset($dataBotolGrouped[$no_sampel]['kategori'][$kategori][$parameter])) {
                            $dataBotolGrouped[$no_sampel]['kategori'][$kategori][$parameter] = [
                                'disiapkan' => 0,
                                "buffer" => 0
                            ];
                        }
                        $dataBotolGrouped[$no_sampel]['kategori'][$kategori][$parameter]['disiapkan'] += $rumus['data_par'][0]['jumlah'] ?? 0;
                        $dataBotolGrouped[$no_sampel]['kategori'][$kategori][$parameter]['buffer'] += $rumus['jmlh_label'] ?? 0;
                    }
                }
            }
            $dataBotolGrouped = array_values($dataBotolGrouped);

            $param_air = [];
            $param_udara = [];
            $param_emisi = [];
            $param_padatan = [];
            foreach ($dataBotolGrouped as $item) {
                if (!isset($item['kategori']))
                    continue;

                foreach ($item['kategori'] as $kategori => $parameters) {
                    $dat = explode("-", $kategori);
                    $kategoriNumber = $dat[0];

                    $structuredData = [
                        'no_sampel' => $item['no_sampel'],
                        'parameters' => $parameters
                    ];

                    if ($kategoriNumber == 1) {
                        $param_air[] = $structuredData;
                    } elseif ($kategoriNumber == 4) {
                        $param_udara[] = $structuredData;
                    } elseif ($kategoriNumber == 5) {
                        $param_emisi[] = $structuredData;
                    } elseif ($kategoriNumber == 6) {
                        $param_padatan[] = $structuredData;
                    }
                }
            }

            return response()->json([
                'masker' => $perdiem->perdiem_jumlah_orang,
                'udara' => array_values($param_udara),
                'air' => array_values($param_air),
                'emisi' => array_values($param_emisi),
                'padatan' => array_values($param_padatan),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    private function rumus($kategori, $botol, $parameter)
    {
        try {
            $dat = explode("-", $kategori);
            $jmlh_label = 0;
            $data_par = []; // Pastikan selalu array

            if ($dat[0] == 1) {
                $volume = floatval($botol['volume']);
                $type_botol = $botol['type_botol'];

                if (str_contains($type_botol, 'M100')) {
                    $kon = ceil($volume / 100);
                } elseif (str_contains($type_botol, 'HNO3')) {
                    if ($volume >= 500) {
                        $kon = 1; // force 1 bcz ketentuan baru maksimal 500ml doang (sebotol)
                    } else {
                        $kon = ceil($volume / 500);
                    }
                } else {
                    $kon = ceil($volume / 1000);
                }

                $jmlh_label = $kon * 2;
                $data_par[] = ['param' => $type_botol, 'jumlah' => $kon]; // Selalu array numerik

            } elseif (in_array($dat[0], [6, 5, 4])) {
                $cek = KonfigurasiPraSampling::where('parameter', $parameter)->first();
                if ($cek) {
                    $jumlah = $cek->ketentuan + 1;
                    $jmlh_label = $jumlah;
                    $data_par[] = ['param' => $parameter, 'jumlah' => $cek->ketentuan]; // Selalu array numerik
                }
            } else {
                $jmlh_label = "-";
                $data_par = []; // Ubah menjadi array kosong agar tetap bisa diakses tanpa error
            }

            return [
                'jmlh_label' => $jmlh_label,
                'data_par' => $data_par,
            ];
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    public function pdf(Request $request)
    {
        $dataList = PersiapanSampelHeader::where('no_order', $request->no_order)
            // ->where('no_quotation', $request->nomor_quotation)
            // ->where('tanggal_sampling', $request->tanggal_sampling)
            ->where('is_active', 1)
            ->get();

        $psh = $dataList->first(function ($item) use ($request) {
            $noSampelDb = json_decode($item->no_sampel, true) ?? [];
            return count(array_intersect($noSampelDb, $request->no_sampel)) === count($noSampelDb);
        });

        if (!$psh)
            return response()->json(['message' => 'Sampel belum disiapkan pdf'], 404);

        return response()->json([$psh->filename], 200);
    }

    private function getRomanMonth($month)
    {
        return ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month - 1];
    }

    private function getDocumentNumber()
    {
        $latestPSH = PersiapanSampelHeader::orderBy('id', 'desc')->latest()->first();

        return 'ISL/PS/' . date('y') . '-' . $this->getRomanMonth(date('m')) . '/' . sprintf('%04d', $latestPSH ? $latestPSH->id + 1 : 1);
    }

    private function saveHeader(Request $request)
    {
        $noSampel = !empty($request->all_category) ? $request->all_category : [];
        dd($noSampel, $request->all_category);
        $existingPsd = PersiapanSampelDetail::whereIn('no_sampel', $noSampel)
            ->where('is_active', 1)
            ->pluck('id_persiapan_sampel_header')
            ->unique()
            ->toArray();

        $psh = null;

        if (count($existingPsd) === 1) {
            PersiapanSampelDetail::whereNotIn('no_sampel', $noSampel)
                ->whereIn('id_persiapan_sampel_header', $existingPsd)
                ->update([
                    'is_active' => 0,
                    'deleted_by' => $this->karyawan,
                    'deleted_at' => Carbon::now(),
                ]);

            $psh = PersiapanSampelHeader::find($existingPsd[0]);
        } else if (count($existingPsd) > 1) {
            $jumlah_array = count($existingPsd);
            $id_header = array_values($existingPsd)[$jumlah_array - 1];
            $array_to_remove = array_slice($existingPsd, 0, -1);

            // matikan header lain 
            PersiapanSampelHeader::whereIn('id', $array_to_remove)->update(['is_active' => 0]);
            //matikan deatil lain
            PersiapanSampelDetail::whereIn('id_persiapan_sampel_header', $array_to_remove)->update(['is_active' => 0]);
            $psh = PersiapanSampelHeader::find($id_header);
        }

        if ($psh == null) {
            $psh = new PersiapanSampelHeader();
            $psh->no_document = $this->getDocumentNumber();
            $psh->created_by = $this->karyawan;
            $psh->created_at = Carbon::now();
        }

        $psh->fill($request->only([
            'no_order',
            'no_quotation',
            'tanggal_sampling',
            'nama_perusahaan',
            'analis_berangkat',
            'sampler_berangkat',
            'analis_pulang',
            'sampler_pulang',
            'plastik_benthos',
            'media_petri_dish',
            'media_tabung',
            'masker',
            'sarung_tangan_karet',
            'sarung_tangan_bintik',
            'tambahan'
        ]));

        $psh->no_sampel = json_encode($noSampel, JSON_UNESCAPED_SLASHES);
        $psh->periode = $request->periode ?? $psh->periode;

        $psh->plastik_benthos = json_encode($request->plastik_benthos ?? []);
        $psh->media_petri_dish = json_encode($request->media_petri_dish ?? []);
        $psh->media_tabung = json_encode($request->media_tabung ?? []);
        $psh->masker = json_encode($request->masker ?? []);
        $psh->sarung_tangan_karet = json_encode($request->sarung_tangan_karet ?? []);
        $psh->sarung_tangan_bintik = json_encode($request->sarung_tangan_bintik ?? []);
        $psh->tambahan = json_encode($request->tambahan ?? []);
        $psh->is_active = 1;
        $psh->updated_by = $this->karyawan;
        $psh->updated_at = Carbon::now();
        $psh->save();

        return $psh;
    }

    private function saveDetail(Request $request, $psh)
    {
        if (!$request->detail) return false;

        $noSampels = array_keys($request->detail);
        $orderDetail = OrderDetail::whereIn('no_sampel', $noSampels)->get();
        $allSamples = [];

        PersiapanSampelDetail::whereNotIn('no_sampel', $noSampels)
            ->where('id_persiapan_sampel_header', $psh->id)
            ->update([
                'is_active' => 0,
                'deleted_by' => $this->karyawan,
                'deleted_at' => Carbon::now()
            ]);

        foreach ($request->detail as $sampleNumber => $categories) {

            $existingPsd = PersiapanSampelDetail::where([
                'id_persiapan_sampel_header' => $psh->id,
                'no_sampel' => $sampleNumber
            ])->first();

            foreach ($categories as $category => &$params) {
                $od = $orderDetail->firstWhere('no_sampel', $sampleNumber);
                if (!$od) continue;

                $kategori = strtolower(explode('-', $od->kategori_2)[1] ?? '');
                if ($kategori !== $category) continue;

                foreach ($params as $param => &$info) {
                    $decoded = collect(json_decode($od->persiapan));
                    $target = $kategori === 'air'
                        ? $decoded->firstWhere('type_botol', $param)
                        : $decoded->firstWhere('parameter', $param);

                    $info['file'] = $target->file ?? null;
                }
            }

            $psd = $existingPsd ?? new PersiapanSampelDetail();
            $allSamples[] = $sampleNumber;

            $psd->no_sampel = $sampleNumber;
            $psd->id_persiapan_sampel_header = $psh->id;
            $psd->parameters = json_encode($categories);

            if ($existingPsd) {
                $psd->updated_by = $this->karyawan;
                $psd->updated_at = Carbon::now();
                $psd->is_active = 1;
            } else {
                $psd->created_by = $this->karyawan;
                $psd->created_at = Carbon::now();
            }

            $psd->save();
        }


        $psh->no_sampel = json_encode($allSamples, JSON_UNESCAPED_SLASHES);
        $psh->save();

        return true;
    }

    public function save(Request $request)
    {
        DB::beginTransaction();

        try {
            $psh = $this->saveHeader($request);
            $this->saveDetail($request, $psh);

            $this->saveQrDocument($psh);

            JobTask::insert([
                'job' => 'RenderPdfPersiapanSample',
                'status' => 'processing',
                'no_document' => $psh->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            $this->dispatch(new RenderPdfPersiapanSample($psh->id));

            DB::commit();

            return response()->json(['message' => 'Saved successfully'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to save data',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    private function generateQr($noDocument)
    {
        $filename = str_replace("/", "_", $noDocument);
        $dir = public_path("qr_documents");

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . "/$filename.svg";
        $link = 'https://www.intilab.com/validation/';
        $unique = 'isldc' . (int) floor(microtime(true) * 1000);

        QrCode::size(200)->generate($link . $unique, $path);

        return $unique;
    }


    private function saveQrDocument($psh)
    {
        foreach (['persiapan_sampel', 'coding_sample', 'surat_tugas_pengambilan_sampel', 'berita_acara_sampling'] as $item) {
            $noDocument = "";
            $typeDoc = "";
            switch ($item) {
                case 'persiapan_sampel':
                    $noDocument = $psh->no_document;
                    $typeDoc = "Persiapan Sampling";
                    break;
                case 'coding_sample':
                    $noDocument = explode('/', $psh->no_document);
                    $noDocument[1] = 'CS';
                    $noDocument = implode('/', $noDocument);
                    $typeDoc = "Coding Sample";
                    break;
                case 'surat_tugas_pengambilan_sampel':
                    $noDocument = explode('/', $psh->no_document);
                    $noDocument[1] = 'STPS';
                    $noDocument = implode('/', $noDocument);
                    $typeDoc = "Surat Tugas Pengambilan Sampel";
                    break;
                case 'berita_acara_sampling':
                    $noDocument = explode('/', $psh->no_document);
                    $noDocument[1] = 'BAS';
                    $noDocument = implode('/', $noDocument);
                    $typeDoc = "Berita Acara Sampling";
                    break;
            }

            $existingQr = QrDocument::where([
                'id_document' => $psh->id,
                'type_document' => $item
            ])->first();

            $qr = $existingQr ?: new QrDocument();

            $qr->id_document = $psh->id;
            $qr->type_document = $item;
            $qr->kode_qr = $this->generateQr($noDocument);
            $qr->file = str_replace("/", "_", $noDocument);
            // $qr->data = json_encode($psh->only(['no_document', 'no_quotation', 'no_order', 'periode', 'tanggal_sampling', 'nama_perusahaan']));
            $qr->data = json_encode([
                'no_document' => $noDocument,
                'type_document' => $typeDoc,
                'no_quotation' => $psh->no_quotation,
                'no_order' => $psh->no_order,
                'periode' => Carbon::parse($psh->periode)->translatedFormat('F Y'),
                'tanggal_sampling' => Carbon::parse($psh->tanggal_sampling)->translatedFormat('d F Y'),
                'nama_perusahaan' => $psh->nama_perusahaan
            ]);
            $qr->created_by = $this->karyawan;
            $qr->created_at = Carbon::now();

            $qr->save();
        }
    }

    public function injectRenderPdfOld(Request $request)
    {

        $all_psh = PersiapanSampelHeader::where('is_active', true);
        if (!empty($request->id)) {
            if (is_array($request->id)) {
                $all_psh = $all_psh->whereIn('id', $request->id);
            } else {
                $all_psh = $all_psh->where('id', $request->id);
            }
        }
        $all_psh = $all_psh->get();

        foreach ($all_psh as $psh) {
            JobTask::insert([
                'job' => 'RenderPdfPersiapanSample',
                'status' => 'processing',
                'no_document' => $psh->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $job = new RenderPdfPersiapanSample($psh->id);
            $this->dispatch($job);
        }

        return response()->json(['message' => 'Saved successfully'], 200);
    }

    public function getAnalis()
    {
        return response()->json(MasterKaryawan::whereIn('id_jabatan', [60, 61, 62, 63])->orderBy('nama_lengkap')->get(), 200);
    }

    private function compareSampleNumber($psHeader, $request)
    {
        $sampelNumbers = $psHeader->psDetail->pluck('no_sampel')->toArray();
        $missingSampleNumbers = array_diff($request->no_sampel, $sampelNumbers);
        $extraSampleNumbers = array_diff($sampelNumbers, $request->no_sampel);
        if ($missingSampleNumbers || $extraSampleNumbers)
            return true;

        return false;
    }

    private function compareByPersiapan($orderDetail, $psDetail)
    {
        $preparedBottles = isset($psDetail->parameters['air']) ? array_keys($psDetail->parameters['air']) : [];
        $requiredBottles = collect(json_decode($orderDetail->persiapan))->pluck('type_botol')->toArray();

        $missingBottles = array_diff($requiredBottles, $preparedBottles);
        $extraBottles = array_diff($preparedBottles, $requiredBottles);
        if ($missingBottles || $extraBottles)
            return true;

        return false;
    }

    private function compareByParameter($orderDetail, $psDetail)
    {
        $kategoriKey = strtolower($orderDetail->kategori_2);
        $preparedParams = isset($psDetail->parameters[$kategoriKey]) ? array_keys($psDetail->parameters[$kategoriKey]) : [];
        $requiredParams = array_map(fn($param) => explode(';', $param)[1], $orderDetail->parameter);

        $missingParams = array_diff($requiredParams, $preparedParams);
        $extraParams = array_diff($preparedParams, $requiredParams);
        if ($missingParams || $extraParams)
            return true;

        return false;
    }

    public function getUpdated(Request $request)
    {

        $psHeader = PersiapanSampelHeader::with([
            'psDetail' => fn($q) => $q->whereIn('no_sampel', $request->no_sampel),
            'orderHeader.orderDetail'
        ])
            ->where('no_quotation', $request->no_quotation)
            ->where('no_order', $request->no_order)
            ->where('is_active', 1)
            ->whereHas('psDetail', fn($q) => $q->whereIn('no_sampel', is_array($request->no_sampel) ? $request->no_sampel : [$request->no_sampel]))
            ->first();

        if (!$psHeader || !$psHeader->psDetail)
            return response()->json(['message' => 'Sampel belum disiapkan update'], 404);

        $diffSampleNumbers = $this->compareSampleNumber($psHeader, $request);
        if ($diffSampleNumbers)
            return response()->json(['message' => 'No Sampel tidak sesuai'], 500);

        foreach ($psHeader->psDetail as $psd) {
            $orderDetail = $psHeader->orderHeader->orderDetail->where('no_sampel', $psd->no_sampel)->first();

            $diffParams = $orderDetail->kategori_2 == '1-Air' ? $this->compareByPersiapan($orderDetail, $psd) : $this->compareByParameter($orderDetail, $psd);
            if ($diffParams)
                return response()->json(['message' => "Parameter tidak sesuai"], 500);
        }

        return response()->json($psHeader, 200);
    }
}
