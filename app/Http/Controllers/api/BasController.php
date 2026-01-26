<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

use App\Models\SamplingPlan;
use App\Models\Jadwal;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\PersiapanSampelHeader;
use App\Models\QrDocument;
use Carbon\Carbon;
use App\Services\MpdfService as Mpdf;

use DateTime;

class BasController extends Controller
{
    public function index(Request $request)
    {
        /* try {
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
                                'status_quotation' => $targetPlan['status_quotation'],
                                'id_sp' => $targetPlan['id'],

                            ]),
                            'is_revisi' => $item['order_header']['is_revisi'],
                            'nama_cabang' => isset($schedule['id_cabang']) ? (
                                $schedule['id_cabang'] == 4 ? 'RO-KARAWANG' :
                                ($schedule['id_cabang'] == 5 ? 'RO-PEMALANG' :
                                    ($schedule['id_cabang'] == 1 ? 'HEAD OFFICE' : 'UNKNOWN'))
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
                                } catch (\Exception $e) {
                                    return false;
                                }
                            } elseif ($colName === 'jadwal') {
                                try {
                                    $parsed = Carbon::parse($field)->format('d/m/Y');
                                    if (stripos($parsed, $colValue) === false) {
                                        return false;
                                    }
                                } catch (\Exception $e) {
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
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        } */
       try {
            $existingWork = DB::table('persiapan_sampel_header')
            ->select('no_order', 'tanggal_sampling', 'sampler_jadwal')
            ->where('is_active', true)
            ->whereBetween('tanggal_sampling', [$request->periode_awal, $request->periode_akhir])
            ->get();

            $doneList = [];
            
            // LOOPING PERTAMA: Membangun Daftar Orang yang Sudah Selesai
            foreach ($existingWork as $row) {
                // PENTING: Pecah nama di sini juga! 
                $headerSamplers = explode(',', $row->sampler_jadwal ?? '');
                foreach ($headerSamplers as $name) {
                    $cleanName = strtolower(trim($name));
                    if (empty($cleanName)) continue;
                    // Kuncinya: Order + Tanggal + Nama Orang
                    $key = sprintf('%s|%s|%s', 
                        trim($row->no_order), 
                        trim($row->tanggal_sampling), 
                        $cleanName
                    );
                    $doneList[$key] = true;
                }
            }
            // 1. Ambil Data (Eager Loading Optimized)
            $data = OrderDetail::with([
                'orderHeader' => function ($q) {
                    $q->select([
                        'id', 'tanggal_order', 'nama_perusahaan', 'konsultan', 'no_document', 
                        'alamat_sampling', 'nama_pic_order', 'nama_pic_sampling', 
                        'no_tlp_pic_sampling', 'jabatan_pic_sampling', 'jabatan_pic_order', 'is_revisi'
                    ]);
                },
                'orderHeader.samplingPlan' => function ($q) {
                    $q->select(['id', 'periode_kontrak', 'quotation_id', 'status_quotation', 'is_active'])
                    ->where('is_active', true); // Pastikan plan aktif
                },
                'orderHeader.samplingPlan.jadwal' => function ($q) {
                    $q->select([
                        'id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang',
                        // Group Concat sampler di level database agar array PHP lebih ringan
                        DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')
                    ])
                    ->where('is_active', true)
                    ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang']);
                }
            ])
            ->select(['id_order_header', 'no_order', 'kategori_1', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
            ->where('is_active', true)
            ->whereBetween('tanggal_sampling', [$request->periode_awal, $request->periode_akhir])
            ->get(); // Hati-hati, load semua ke memori

            // 2. Mapping Manual (High Performance PHP Array)
            $cabangMap = [
                1 => 'HEAD OFFICE',
                4 => 'RO-KARAWANG',
                5 => 'RO-PEMALANG'
            ];

            $groupedData = [];

            foreach ($data as $item) {
                // Early exit jika relasi tidak lengkap
                if (!$item->orderHeader || $item->orderHeader->sampling->isEmpty()) {continue;}
                $orderHeader = $item->orderHeader;
                $periode = $item->periode ?? '';
                $targetPlan = null;
                // Prioritas 1: Cari yang periodenya COCOK
                if ($periode) {
                    $targetPlan = $orderHeader->sampling->firstWhere('periode_kontrak', $periode);
                }
                
                // Prioritas 2: Jika tidak ada periode spesifik, atau tidak ketemu, ambil yang pertama
                if (!$targetPlan) {
                    $targetPlan = $orderHeader->sampling->first();
                }

                // Validasi keberadaan jadwal
                if (!$targetPlan || $targetPlan->jadwal->isEmpty()) {
                    continue;
                }

                // Cache info JSON untuk efisiensi
                $infoPendukung = json_encode([
                    'nama_pic_order'       => $orderHeader->nama_pic_order,
                    'nama_pic_sampling'    => $orderHeader->nama_pic_sampling,
                    'no_tlp_pic_sampling'  => $orderHeader->no_tlp_pic_sampling,
                    'jabatan_pic_sampling' => $orderHeader->jabatan_pic_sampling,
                    'jabatan_pic_order'    => $orderHeader->jabatan_pic_order
                ]);

                $infoSampling = json_encode([
                    'id_request'       => $targetPlan->quotation_id,
                    'status_quotation' => $targetPlan->status_quotation,
                    'id_sp' => $targetPlan->id
                ]);

                // Loop Jadwal
                foreach ($targetPlan->jadwal as $schedule) {
                    // Strict check: Tanggal jadwal HARUS sama dengan tanggal sampling di OrderDetail
                    if ($schedule->tanggal !== $item->tanggal_sampling) {
                        continue;
                    }
                     // LOGIKA FILTER DETIL (ATOMIC CHECK)
                    // 2. Cek Satu Per Satu (ABSENSI)
                    $currentSamplers = explode(',', $schedule->sampler ?? '');
                    $pendingSamplers = [];
                    foreach ($currentSamplers as $singleSampler) {
                        $cleanTargetName = strtolower(trim($singleSampler));
                        if (empty($cleanTargetName)) continue;

                        $checkKey = sprintf('%s|%s|%s', 
                            trim($item->no_order), 
                            trim($schedule->tanggal), 
                            $cleanTargetName
                        );

                        // Logic: Jika TIDAK ADA di doneList, berarti dia BELUM selesai -> Masukkan ke pending
                        if (isset($doneList[$checkKey])) {
                            $pendingSamplers[] = trim($singleSampler);
                        }
                    }
                    // 3. Keputusan Akhir untuk Row Ini
                    // Jika pending kosong, berarti SEMUA orang di jadwal ini sudah selesai -> HILANGKAN ROW
                    if (empty($pendingSamplers)) {
                        continue; 
                    }

                    // 4. Update Tampilan Sampler
                    // Jika aslinya 3 orang, tapi "Adji" sudah selesai, maka implode ulang sisa 2 orang saja.
                    // Sehingga nanti pas di Grouping, yang muncul hanya yang belum selesai.
                    $schedule->sampler = implode(',', $pendingSamplers);

                    $kategori = implode(',', json_decode($schedule->kategori, true) ?? []);
                    $namaCabang = $cabangMap[$schedule->id_cabang] ?? 'HEAD OFFICE (Default)';

                    // Key Unik untuk Grouping (Composite Key)
                    $key = $orderHeader->no_document . '|' . 
                        $item->no_order . '|' . 
                        $schedule->tanggal . '|' .
                        $schedule->jam_mulai . '|' .
                        $kategori; // Key dipersingkat agar hash lebih cepat

                    if (isset($groupedData[$key])) {
                        // Jika data sudah ada, gabungkan Sampler-nya saja
                        $existingSamplers = explode(',', $groupedData[$key]['sampler']);
                        $newSamplers = explode(',', $schedule->sampler ?? '');
                        
                        // Merge & Unique
                        $merged = array_unique(array_merge($existingSamplers, $newSamplers));
                        $groupedData[$key]['sampler'] = implode(',', array_filter($merged));
                    } else {
                        // Data Baru
                        $groupedData[$key] = [
                            'nomor_quotation'    => $orderHeader->no_document ?? '',
                            'nama_perusahaan'    => $orderHeader->nama_perusahaan ?? '',
                            'status_sampling'    => $item->kategori_1 ?? '',
                            'periode'            => $periode,
                            'jadwal'             => $schedule->tanggal,
                            'kategori'           => $kategori,
                            'sampler'            => $schedule->sampler ?? '',
                            'no_order'           => $item->no_order ?? '',
                            'alamat_sampling'    => $orderHeader->alamat_sampling ?? '',
                            'konsultan'          => $orderHeader->konsultan ?? '',
                            'info_pendukung'     => $infoPendukung,
                            'jadwal_jam_mulai'   => $schedule->jam_mulai,
                            'jadwal_jam_selesai' => $schedule->jam_selesai,
                            'info_sampling'      => $infoSampling,
                            'is_revisi'          => $orderHeader->is_revisi,
                            'nama_cabang'        => $namaCabang,
                        ];
                    }
                }
            }

            // 3. Return ke DataTables (Collection Client Side)
            // Karena data sudah berupa Array, kita bungkus dengan collect()
            return DataTables::of(collect(array_values($groupedData)))
                ->make(true);

        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine()
            ], 500);
        }
    }

    public function preview(Request $request)
    {

        try {
            if (!$request->has('no_document') || empty($request->no_document)) {
                return response()->json([
                    'data' => [],
                ], 200);
            }

            // setup data
            $jsonDecode = html_entity_decode($request->info_sampling);
            $infoSampling = json_decode($jsonDecode, true);

            $tipe = explode("/", $request->no_document);

            $request->kategori = explode(",", $request->kategori);

            // get No Sample
            $noSample = [];
            // dd($request->kategori);
            foreach ($request->kategori as $item) {
                $parts = explode(" - ", $item);
                array_push($noSample, $request->no_order . '/' . $parts[1]);
            }

            $sp = SamplingPlan::where('id', $infoSampling['id_sp'])
                ->where('quotation_id', $infoSampling['id_request'])
                ->where('status_quotation', $infoSampling['status_quotation'])
                ->where('is_active', true)
                ->first();

            if (!$sp) {
                return response()->json([
                    'message' => 'Data sampling plan tidak ditemukan.!'
                ], 401);
            } else {
                $jadwal = Jadwal::select([
                    'id_sampling',
                    'kategori',
                    'tanggal',
                    'durasi',
                    'jam_mulai',
                    'jam_selesai',
                    DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler'),
                    DB::raw('GROUP_CONCAT(id SEPARATOR ",") AS batch_id')
                ])
                    ->where('id_sampling', $sp->id)
                    ->where('tanggal', $request->tanggal_sampling)
                    ->where('is_active', true)
                    ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai'])
                    ->get()->pluck('tanggal');
            }

            if ($jadwal->isEmpty()) {
                return response()->json([
                    'message' => 'Data jadwal tidak ditemukan.!',
                ], 401);
            }
            // Kelompokkan data kategori
            $groupedData = [];
            $insample = [];
            $orderH = OrderHeader::where('no_document', $request->no_document)
                ->where('no_order', $request->no_order)->first();
            $orderD = OrderDetail::with(['codingSampling'])
                ->where('id_order_header', $orderH->id)
                ->where('no_order', $request->no_order)
                ->whereIn('no_sampel', $noSample)
                ->whereIn('tanggal_sampling', $jadwal)
                ->where('is_active', true)

                ->get();


            $tipe = explode("/", $request->no_document);
            $tahun = "20" . explode("-", $tipe[2])[0];

            // Get perdiem data

            if ($tipe[1] == "QT") {
                $perdiem = QuotationNonKontrak::select('perdiem_jumlah_orang', 'jumlah_orang_24jam')->where('no_document', $request->no_document)->first();
            } else if ($tipe[1] == "QTC") {
                $perdiem = QuotationKontrakH::select('perdiem_jumlah_orang', 'jumlah_orang_24jam')->where('no_document', $request->no_document)->first();
            }

            // sebelum
            /* $dataList = PersiapanSampelHeader::with('psDetail')->where([
                'no_order' => $request->no_order,
                'no_quotation' => $request->no_document,
                'tanggal_sampling' => $request->tanggal_sampling,
            ])
            ->where('is_active', 1)
            ->get();
            $psHeader = $dataList->first(function ($item) use ($noSample) {
                $no_sampel = json_decode($item->no_sampel, true) ?? [];
                return count(array_intersect($no_sampel, $noSample)) > 0;
            });
            */
            $psHeader = PersiapanSampelHeader::with('psDetail')->where([
                'no_order' => $request->no_order,
                'no_quotation' => $request->no_document,
                'tanggal_sampling' => $request->tanggal_sampling,
            ])
                ->where('is_active', 1)
                ->first();



            if ($psHeader) {
                $bsDocument = ($psHeader->detail_bas_documents != null) ? json_decode($psHeader->detail_bas_documents) : null;

                if ($bsDocument != null) {
                    // dd($bsDocument);

                    // Ambil bagian setelah " - " dari kategori
                    $cleanedSamples = array_map(fn($s) => explode(' - ', $s)[1] ?? null, $request->kategori);

                    // Filter nilai null jika ada, dan reset index
                    $cleanedSamples = array_values(array_filter($cleanedSamples));

                    // Sort agar urutannya konsisten
                    sort($cleanedSamples);

                    $matchedDocument = null;

                    foreach ($bsDocument as $doc) {
                        // Pastikan no_sampel tersedia dan dalam bentuk array
                        if (!isset($doc->no_sampel) || !is_array($doc->no_sampel)) {
                            continue;
                        }

                        // Sort no_sampel dokumen juga
                        $docSamples = $doc->no_sampel;
                        sort($docSamples);

                        // Bandingkan apakah sama persis (jumlah dan isi, setelah diurutkan)
                        if ($cleanedSamples === $docSamples) {
                            $matchedDocument = $doc;
                            break;
                        }
                    }

                    $bsDocument = $matchedDocument;
                }
            } else {
                $bsDocument = null;
            }

            // dd($bsDocument);

            // Get kategori data


            // Get order details untuk semua kategori sekaligus
            $data_sampling = [];
            $dat_param = [];

            foreach ($orderD as $vv) {
                $data_sampling[] = (object) [
                    'no_sample' => $vv->no_sampel,
                    'kategori_2' => $vv->kategori_2,
                    'kategori_3' => $vv->kategori_3,
                    'nama_perusahaan' => $vv->nama_perusahaan,
                    'koding_sampling' => $vv->koding_sampling,
                    'file_koding_sample' => $vv->file_koding_sampel,
                    'file_koding_sampling' => $vv->file_koding_sampling,
                    'konsultan' => $vv->orderHeader->konsultan,
                    'tanggal_sampling' => $vv->tanggal_sampling,
                    'keterangan_1' => $vv->keterangan_1,
                    'jumlah_label' => $vv->codingSampling->jumlah_label ?? null,
                    'status_sampling' => $vv->kategori_1,
                    'id' => $vv->id,
                    'id_order_header' => $vv->id_order_header,
                    'id_req_header' => $infoSampling['id_request'],
                    'id_req_detail' => $request->id_req_detail,
                    'periode_kontrak' => $vv->periode,
                    'tgl_order' => $orderH->tanggal_order,
                    'botol' => $vv->botol,
                    'parameter' => $vv->parameter,
                    'no_order' => $vv->orderHeader->no_order,
                    'no_document' => $request->no_document,
                ];

                if ($vv->codingSampling) {
                    $dat_param[] = $vv->codingSampling;
                }
            }

            $dataPdf = self::cetakBASPDF($orderH, $data_sampling, $dat_param, $bsDocument, $psHeader);
            return $dataPdf;
            // return response()->json([
            //     'data_sampling' => $data_sampling,
            //     'data_param' => $dat_param,
            // ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 401);
        }

    }

    private function cetakBASPDF($dataHeader, $dataSampling, $dataParam, $bsDocument, $psh)
    {
        try {
            if (!$psh) {
                return response()->json([
                    'message' => 'Sampel belum disiapkan, Silahkan melakukan update terlebih dahulu.!',
                ], 401);
            }

            $noDocument = explode('/', $psh->no_document);
            $noDocument[1] = 'BAS';
            $noDocument = implode('/', $noDocument);

            $qr_img = '';
            $qr = QrDocument::where('id_document', $psh->id)
                ->where('type_document', 'berita_acara_sampling')
                ->whereJsonContains('data->no_document', $noDocument)
                ->first();

            if ($qr) {
                $qr_data = json_decode($qr->data, true);
                if (isset($qr_data['no_document']) && $qr_data['no_document'] == $noDocument) {
                    $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr;
                }
            }

            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => [216, 305],
                'margin_header' => 5,
                'margin_bottom' => 3,
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P',
            );
            $pdf = new Mpdf($mpdfConfig);
            // $strReplace = Helpers::escapeStr('BAS_' . $data[0]->no_document . '_' . $data[0]->nama_perusahaan);
            // $filename = $strReplace . '.pdf';
            // $perusahaan = str_replace('-', '_', $dataHeader->nama_perusahaan);
            // $cleaned = preg_replace('/[\/\s\t\r\n]+/', '_', $perusahaan);
            // $filename = str_replace(["/", " "], "_", 'BAS_' . trim($dataHeader->no_document) . '_' . trim($cleaned) . '.pdf');
            $filename = 'BAS_' . preg_replace(
                '/[^A-Za-z0-9_-]+/',
                '_',
                trim($dataHeader->no_document) . '_' . trim($dataHeader->nama_perusahaan)
            ) . '.pdf';

            // $filename = str_replace(["/", " "], "_", 'BAS_' . trim($dataHeader->no_document) . '_' . trim($dataHeader->nama_perusahaan) . '.pdf');


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
                        'content' => '' . $qr_img . '',
                        'font-size' => 4,
                        'font-style' => 'I',
                        // 'font-style' => 'B',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'line' => -1,
                )
            );

            // Set style
            $css = '
            .custom {
                padding: 5px;
                font-size: 12px;
                font-weight: bold;
                border: 1px solid #000000;
                text-align: center;
            }
            .custom2 {
                padding-top: 8px;
                font-size: 12px;
            }
            .custom3 {
                font-size: 12px;
                padding: 10px;
                margin-bottom: 5mm;
            }
            .custom4 {
                display: flex;
                justify-content: end;
            }
            .custom5 {
                font-size: 12px;
                padding: 5px;
                border: 1px solid #000000;
                font-weight: bold;
                text-align: center;
            }
            .kolomno {
                font-size: 12px;
                border-left: 1px solid #000000;
                border-top: 1px solid #000000;
                border-bottom: 1px solid #000000;
                text-align: center;
                margin-bottom: 5mm;
            }
            .kolomttd {
                font-size: 12px;
                border-right: 1px solid #000000;
                border-top: 1px solid #000000;
                border-bottom: 1px solid #000000;
                text-align: center;
                margin-bottom: 5mm;
            }
            .kolomttd2 {
                padding: 23px;
                border: 1px solid #000000;
            }
            .kotak {
                border: 1px solid #000000;
            }
            body {
                font-size: 12px; /* Ukuran font */
                line-height: 1.5; /* Jarak antar baris */
            }
            .table {
                width: 100%;
                border-collapse: collapse;
            }
            .table td, .table th {
                padding: 8px;
                font-size: 10px;
                border: 1px solid #000;
            }
        ';
            $pdf->SetDisplayMode('fullpage');
            $pdf->setFooter($footer);

            $header = '
            <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                <tr>
                    <td class="custom5"><b>No Dokumen: ' . $noDocument . '</b></td>
                    <td class="custom3" width="40%" ></td>
                    <td class="custom5">No Order: ' . $dataHeader->no_order . '</td>
                </tr>
            </table>
            <div style="height: 40px;"></div>
            <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;margin-bottom: 40px;">
            <tr>
                <td class="custom3" colspan="2">
                Hari : ............................ Tanggal : ............................
                Bulan : ............................ Tahun : ............................
                </td>
            </tr>
            <tr>
                <td class="custom3" style="text-align: justify;" colspan="2">
                Sesuai dengan permintaan pihak pelanggan, melalui Berita Acara Sampling ini, bahwa pihak PT Inti Surya Laboratorium telah melakukan kegiatan pengambilan sampel / contoh uji (sampling) yang dilaksanakan sebagaimana rincian berikut :
                </td>
            </tr>
            <tr>
                <td class="custom3" width="120">Nama Perusahaan</td>
                <td class="custom3">: ' . $dataHeader->nama_perusahaan . '</td>
            </tr>
            <tr>
                <td class="custom3" width="120">Alamat</td>
                <td class="custom3">: ' . $dataHeader->alamat_sampling . '</td>
            </tr>
            <tr>
                <td class="custom3" rowspan="2" width="120">Nama Personil :</td>
                <td class="custom3">1. ........................................................................................... (Petugas sampling)</td>
            </tr>
            <tr>
                <td class="custom3">2. ........................................................................................... (Perwakilan Pelanggan / Perusahaan)</td>
            </tr>
            <tr>
                <td class="custom3" colspan="2">Mulai pelaksanaan pekerjaan pukul : .................. : ..................</td>
            </tr>
            <tr>
                <td class="custom3" colspan="2">Berakhir pada pukul : .................. : .................. (hari / tanggal : ............... / .............................................)</td>
            </tr>
            </table>
        ';
            $pdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
            $pdf->SetHTMLHeader($header);
            $pdf->WriteHTML('<!DOCTYPE html>
            <html>
            <head>
                <style>' . $css . '</style>
            </head>
            <body>');
            $p = 1;
            $pdf->WriteHTML('<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin-top: 12px;">');
            // dd(collect($dataSampling)->pluck('kategori_3')->toArray());
            foreach ($dataSampling as $key => $val) {
                $dat = explode("-", $val->kategori_3);
                $dat1 = $dat[0] !== '' ? $dat[1] : "";
                $pdf->WriteHTML('
            <tr>
                <td class="custom" width="10">' . $p++ . '</td>
                <td class="custom" width="120">' . $val->no_sample . '</td>
                <td class="custom" width="80" style="white-space: wrap;">' . $dat1 . '</td>
                <td class="custom" width="80" style="white-space: wrap;">' . $val->keterangan_1 . '</td>
                <td width="210" style="border: 1px solid #000000;">
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin: 8px;">
                    <tr>
                    <td style="font-size: 20px; font-weight: bold;" width="10">&#9744;</td>
                    <td class="custom2" style="font-weight: bold; text-decoration: underline;">Selesai </td>
                    </tr>
                    <tr>
                    <td style="font-size: 20px; font-weight: bold;" width="10">&#9744;</td>
                    <td class="custom2">Belum selesai / dilanjutkan pada :</td>
                    </tr>
                    <tr>
                    <td colspan="2" class="custom2">Hari /Tanggal : ....................................</td>
                    </tr>
                    <tr>
                    <td colspan="2" class="custom2">Sisa Sampling : ...............................Titik</td>
                    </tr>
                </table>
                </td>
                <td style="border: 1px solid #000000;" width="240">
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin: 8px;">
                    <tr>
                    <td style="font-size: 20px; font-weight: bold;" width="10">&#9744;</td>
                    <td class="custom2">Dibatalkan oleh pihak pelanggan</td>
                    <td class="custom2">..... Titik</td>
                    </tr>
                    <tr>
                    <td style="font-size: 20px; font-weight: bold;" width="10">&#9744;</td>
                    <td class="custom2">Terbatas / kendala waktu / cuaca</td>
                    <td class="custom2">..... Titik</td>
                    </tr>
                    <tr>
                    <td style="font-size: 20px; font-weight: bold;" width="10">&#9744;</td>
                    <td class="custom2">Titik sampling tidak / belum siap</td>
                    <td class="custom2">..... Titik</td>
                    </tr>
                    <tr>
                    <td style="font-size: 20px; font-weight: bold;" width="10">&#9744;</td>
                    <td colspan="2" class="custom2">Lainnya : ...............................................</td>
                    </tr>
                </table>
                </td>
            </tr>
            ');
            }
            $pdf->WriteHTML('</table>');

            $pdf->AddPage();
            $pdf->WriteHTML('
            <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <tr>
                <td colspan="5" style="border: 1px solid #000000; padding-bottom: 9px;">
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin-left: 12px; margin-top: 12px;">
                    <tr>
                    <td style="font-size: 14px; padding-bottom: 13px;">Catatan tambahan :</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                </table>
                </td>
            </tr>
            <tr>
                <td colspan="5" style="padding: 5px;"></td>
            </tr>
            <tr>
                <td colspan="5" style="border: 1px solid #000000;">
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin-top: 12px; margin-left: 12px;">
                    <tr>
                    <td style="font-size: 14px; padding-bottom: 13px;">Informasi-Informasi Teknis Yang Berkaitan Dengan Kegiatan Pengujian Selanjutnya : </td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                    </tr>
                </table>
                </td>
            </tr>
            <tr>
                <td colspan="5" style="padding: 5px;"></td>
            </tr>
            <tr>
                <td colspan="5" style="font-size: 14px;">Sesuai dengan rincian diatas maka pihak-pihak yang berkaitan dengan kegiatan, menyetujui adanya data dan informasi tersebut</td>
            </tr>
            <tr>
                <td colspan="5" style="padding: 5px;"></td>
            </tr>
            <tr>
                <td style="padding: 8px;"></td>
                <td style="border: 1px solid #000000;">
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin-top: 12px;">
                    <tr>
                    <td colspan="4" style="text-align: center; font-weight: bold; font-size: 14px;">
                        <span style="text-decoration: underline;">Pihak Yang Menjalankan Kegiatan</span>
                        <span style="font-style: italic;">(Sampler)</span>
                    </td>
                    </tr>
                    <tr>
                    <td colspan="2" style="font-weight: bold; font-size: 14px; text-align: center; padding: 8px;">Nama Lengkap</td>
                    <td colspan="2" style="font-weight: bold; font-size: 14px; text-align: center; padding: 8px;">Tanda Tangan</td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">1. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">2. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">3. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">4. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td style="padding: 8px;"></td>
                    </tr>
                </table>
                </td>
                <td style="padding: 8px;"></td>
                <td style="border: 1px solid #000000;">
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin-top: 12px;">
                    <tr>
                    <td colspan="4" style="text-align: center; font-weight: bold; font-size: 14px;">
                        <span style="text-decoration: underline;">Pihak Yang Menjalankan Kegiatan</span>
                        <span style="font-style: italic;">(Pelanggan)</span>
                    </td>
                    </tr>
                    <tr>
                    <td colspan="2" style="font-weight: bold; font-size: 14px; text-align: center; padding: 8px;">Nama Lengkap</td>
                    <td colspan="2" style="font-weight: bold; font-size: 14px; text-align: center; padding: 8px;">Tanda Tangan</td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">1. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">2. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">3. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">4. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td style="padding: 8px;"></td>
                    </tr>
                </table>
                </td>
                <td style="padding: 8px;"></td>
            </tr>
            </table>
        </body>
        </html>');
            $pdf->Output(public_path() . '/bas/' . $filename, 'F');
            if ($bsDocument !== null) {
                return response()->json(['status' => true, 'data' => $bsDocument], 200);
            } else {
                return response()->json(['status' => false, 'data' => $filename], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 401);
        }
    }
}
